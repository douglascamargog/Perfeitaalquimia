<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Csrf;

/**
 * App do vendedor externo (PWA offline).
 *  - pacote(): tudo o que o celular precisa guardar para vender sem internet (só a carteira da pessoa).
 *  - sincronizar(): recebe a fila do celular (pedidos com uuid e visitas) e devolve o resultado de cada item.
 *    Pedido com uuid já recebido devolve o pedido existente (nunca duplica).
 * As regras do pedido são as MESMAS do catálogo e do sistema (PedidoService).
 */
class AppSync
{
    /** Clientes que esta pessoa pode atender pelo app */
    private static function escopo(): array
    {
        return Auth::pode('carteira.ver_todas') ? ['1 = 1', []] : ['c.responsavel_id = ?', [Auth::id()]];
    }

    public static function pacote(): array
    {
        [$w, $par] = self::escopo();
        $tolerancia = (int) Config::get('recompra.tolerancia_dias', 3);
        $clientes = Banco::todos("SELECT c.id, c.razao_social, c.nome_fantasia, c.cnpj, c.endereco, c.bairro, c.cidade, c.uf, c.telefone, c.whatsapp,
                c.tabela_preco_id, c.credito_situacao, c.ultima_compra_em, c.intervalo_medio_dias, c.tipo,
                (c.intervalo_medio_dias > 0 AND c.ultima_compra_em + INTERVAL (c.intervalo_medio_dias + $tolerancia) DAY < CURDATE()) AS recompra_atrasada,
                EXISTS (SELECT 1 FROM cobrancas cb WHERE cb.cliente_id = c.id AND cb.situacao = 'aberta') AS em_cobranca
            FROM clientes c WHERE c.excluido_em IS NULL AND $w ORDER BY COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social)", $par);
        $padrao = (int) Banco::valor('SELECT id FROM tabelas_preco WHERE ativa = 1 ORDER BY id LIMIT 1');
        $bloquearBoleto = (bool) Config::get('cobranca.bloquear_boleto', true);
        foreach ($clientes as &$c) {
            $c['tabela_preco_id'] = (int) ($c['tabela_preco_id'] ?: $padrao);
            $c['boleto_bloqueado'] = $bloquearBoleto && ($c['credito_situacao'] === 'atraso' || $c['em_cobranca']);
            $c['recompra_atrasada'] = (bool) $c['recompra_atrasada'];
            unset($c['credito_situacao'], $c['em_cobranca']);
        }
        unset($c);
        $tabelas = array_values(array_unique(array_column($clientes, 'tabela_preco_id'))) ?: [$padrao];
        $precos = [];
        foreach (Banco::todos('SELECT produto_id, tabela_preco_id, preco FROM produto_precos WHERE tabela_preco_id IN (' . implode(',', array_map('intval', $tabelas)) . ')') as $p) {
            $precos[$p['tabela_preco_id']][$p['produto_id']] = (float) $p['preco'];
        }
        $produtos = Banco::todos('SELECT p.id, p.codigo, p.nome, p.embalagem, c.nome AS categoria, m.arquivo AS imagem FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
            WHERE p.ativo = 1 AND p.visivel_catalogo = 1 ORDER BY p.nome');
        foreach ($produtos as &$p) {
            $p['imagem'] = $p['imagem'] ? url('/' . $p['imagem']) : null;
        }
        unset($p);
        $formas = Banco::todos('SELECT id, slug, nome, bloqueia_com_atraso FROM formas_pagamento WHERE ativa = 1 ORDER BY ordem, id');
        foreach ($formas as &$f) {
            $f['condicoes'] = Banco::todos('SELECT id, nome, desconto_pct, valor_minimo FROM condicoes_pagamento WHERE forma_pagamento_id = ? AND ativa = 1 ORDER BY valor_minimo, id', [$f['id']]);
        }
        unset($f);
        $minimos = [];
        foreach (Banco::todos('SELECT ru.uf, COALESCE(ru.pedido_minimo, r.pedido_minimo) AS minimo FROM regiao_ufs ru JOIN regioes r ON r.id = ru.regiao_id') as $m) {
            $minimos[$m['uf']] = (float) $m['minimo'];
        }
        // Produtos com a marca do cliente (Terceirização), só dos clientes desta carteira: {cliente_id: [{chave, nome, ...}]}
        $marca = [];
        $ids = array_column($clientes, 'id');
        if ($ids) {
            foreach (Banco::todos('SELECT tp.id, tp.cliente_id, tp.nome_rotulo, tp.codigo_cliente, pr.codigo, pr.embalagem, COALESCE(tp.preco, pp.preco) AS preco
                    FROM terceirizacao_produtos tp JOIN produtos pr ON pr.id = tp.produto_id
                    LEFT JOIN produto_precos pp ON pp.produto_id = tp.produto_id AND pp.tabela_preco_id = ?
                    WHERE tp.ativo = 1 AND tp.cliente_id IN (' . implode(',', array_map('intval', $ids)) . ')', [\App\Services\TerceirizacaoService::tabelaId()]) as $t) {
                if ($t['preco'] !== null) {
                    $marca[$t['cliente_id']][] = ['chave' => 't' . $t['id'], 'nome' => $t['nome_rotulo'], 'codigo' => $t['codigo_cliente'] ?: $t['codigo'],
                        'embalagem' => $t['embalagem'], 'preco' => (float) $t['preco']];
                }
            }
        }
        $u = Auth::usuario();
        return [
            'marca' => $marca,
            'gerado_em' => date('c'), 'vendedor' => ['id' => (int) $u['id'], 'nome' => $u['nome']], 'csrf' => Csrf::token(),
            'dias_cache' => (int) Config::get('app.dias_cache', 7), 'clientes' => $clientes, 'produtos' => $produtos, 'precos' => $precos,
            'tabelas' => Banco::todos('SELECT id, nome FROM tabelas_preco WHERE ativa = 1'), 'formas' => $formas, 'minimos' => $minimos,
            'whatsapp_empresa' => preg_replace('/\D/', '', (string) Config::get('empresa.whatsapp', '')),
        ];
    }

    /**
     * Processa a fila do celular. $itens: [{tipo: 'pedido'|'visita', uuid, cliente_id, itens:{produto_id: qtd}, pagamento:'forma:cond', observacoes, resumo, data}]
     * Devolve [uuid => {ok, pedido_id?, situacao?, mensagem}]
     */
    public static function sincronizar(array $itens): array
    {
        [$w, $par] = self::escopo();
        $resultado = [];
        foreach (array_slice($itens, 0, 200) as $item) {
            $uuid = strtolower((string) ($item['uuid'] ?? ''));
            if (!PedidoService::uuidValido($uuid)) {
                continue;
            }
            $cliente = Banco::um("SELECT c.* FROM clientes c WHERE c.id = ? AND c.excluido_em IS NULL AND $w", [(int) ($item['cliente_id'] ?? 0), ...$par]);
            if (!$cliente) {
                $resultado[$uuid] = ['ok' => false, 'mensagem' => 'Cliente fora da sua carteira (ou excluído). Atualize os dados do app.'];
                continue;
            }
            if (($item['tipo'] ?? '') === 'visita') {
                $resultado[$uuid] = self::visita($cliente, $item, $uuid);
                continue;
            }
            [$forma, $condicao] = array_map('intval', explode(':', ((string) ($item['pagamento'] ?? '')) . ':0'));
            [$id, $erros] = PedidoService::criar($cliente, [
                'itens' => array_map('intval', (array) ($item['itens'] ?? [])), 'forma_pagamento_id' => $forma, 'condicao_id' => $condicao,
                'observacoes' => (string) ($item['observacoes'] ?? ''), 'origem' => 'app', 'usuario_id' => Auth::id(), 'vendedor_id' => Auth::id(), 'uuid' => $uuid,
            ]);
            $resultado[$uuid] = $erros
                ? ['ok' => false, 'mensagem' => implode(' ', $erros)]
                : ['ok' => true, 'pedido_id' => $id, 'situacao' => (string) Banco::valor('SELECT situacao FROM pedidos WHERE id = ?', [$id]), 'mensagem' => "Pedido #$id recebido."];
        }
        return $resultado;
    }

    /** Visita registrada no celular → contato na linha do tempo (o uuid evita registrar duas vezes) */
    private static function visita(array $cliente, array $item, string $uuid): array
    {
        if (Banco::valor("SELECT 1 FROM cliente_eventos WHERE cliente_id = ? AND JSON_UNQUOTE(JSON_EXTRACT(dados_json, '$.uuid')) = ?", [$cliente['id'], $uuid])) {
            return ['ok' => true, 'mensagem' => 'Visita já registrada.'];
        }
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($item['data'] ?? '')) && $item['data'] <= date('Y-m-d') ? $item['data'] : date('Y-m-d');
        CarteiraService::registrarContato((int) $cliente['id'], $data, 'Visita presencial', mb_substr((string) ($item['resumo'] ?? ''), 0, 2000) ?: 'Visita registrada pelo app', (int) Auth::id());
        // Marca o evento recém-criado com o uuid do celular
        Banco::executar("UPDATE cliente_eventos SET dados_json = JSON_OBJECT('uuid', ?, 'origem', 'app') WHERE cliente_id = ? AND canal = 'Visita presencial' AND dados_json IS NULL ORDER BY id DESC LIMIT 1", [$uuid, $cliente['id']]);
        return ['ok' => true, 'mensagem' => 'Visita registrada.'];
    }

    /** Situação dos pedidos do celular (pelos uuids) */
    public static function situacao(array $uuids): array
    {
        $uuids = array_values(array_filter(array_map('strtolower', $uuids), [PedidoService::class, 'uuidValido']));
        if (!$uuids) {
            return [];
        }
        [$w, $par] = self::escopo();
        $linhas = Banco::todos('SELECT p.uuid, p.id, p.situacao, p.numero_nf, p.motivo_recusa, p.total FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
            WHERE p.uuid IN (' . implode(',', array_fill(0, count($uuids), '?')) . ") AND (p.vendedor_id = ? OR $w)", [...array_slice($uuids, 0, 300), Auth::id(), ...$par]);
        $saida = [];
        foreach ($linhas as $l) {
            $saida[$l['uuid']] = ['pedido_id' => (int) $l['id'], 'situacao' => $l['situacao'], 'numero_nf' => $l['numero_nf'], 'motivo' => $l['motivo_recusa'], 'total' => (float) $l['total']];
        }
        return $saida;
    }
}
