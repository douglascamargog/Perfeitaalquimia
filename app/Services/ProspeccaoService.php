<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;
use App\Models\Cliente;
use App\Models\Listas;
use RuntimeException;

/**
 * Prospecção — todo o trabalho com quem ainda não compra, num lugar só:
 *  - Para abordar: a fila única dos leads (de qualquer origem: listas importadas, base de CNPJs, site, indicação...),
 *    com conversão por origem e as abordagens da equipe;
 *  - Encontrar empresas: base pública de CNPJs (CNAEs do nosso público), marcando quem já está na carteira,
 *    e o envio das escolhidas para o funil.
 */
class ProspeccaoService
{
    public const POR_PAGINA = 50;

    /** CNAEs configurados: ['4729699' => 'Produtos naturais...', ...] */
    public static function cnaes(): array
    {
        $lista = [];
        foreach ((array) Config::get('prospeccao.cnaes', []) as $item) {
            if (preg_match('/^\s*([\d\-\/\.]{7,10})\s*(.*)$/u', (string) $item, $m)) {
                $codigo = preg_replace('/\D/', '', $m[1]);
                $lista[$codigo] = trim($m[2]) ?: $codigo;
            }
        }
        return $lista;
    }

    /** 4729699 → 4729-6/99 */
    public static function cnaeFormatado(string $c): string
    {
        return strlen($c) === 7 ? substr($c, 0, 4) . '-' . $c[4] . '/' . substr($c, 5) : $c;
    }

    /** Filtros da URL, já limpos */
    public static function filtrosDaUrl(array $g): array
    {
        $ufs = Integracoes::baseCnpj()->ufs();
        $uf = in_array(strtoupper((string) ($g['uf'] ?? '')), $ufs, true) ? strtoupper((string) $g['uf']) : ($ufs[0] ?? 'RS');
        $cnae = preg_replace('/\D/', '', (string) ($g['cnae'] ?? ''));
        return [
            'uf' => $uf, 'cidade' => trim((string) ($g['cidade'] ?? '')), 'bairro' => trim((string) ($g['bairro'] ?? '')),
            'cnae' => isset(self::cnaes()[$cnae]) ? $cnae : '', 'meses' => in_array((int) ($g['meses'] ?? 0), [3, 6, 12, 24], true) ? (int) $g['meses'] : 0,
            'q' => trim((string) ($g['q'] ?? '')), 'esconder_carteira' => ($g['esconder'] ?? '') === '1',
        ];
    }

    /** Empresas da página, com a situação de cada uma na carteira */
    public static function buscar(array $f, int $pagina): array
    {
        $r = Integracoes::baseCnpj()->buscar($f + ['cnaes' => array_keys(self::cnaes())], max(1, $pagina), self::POR_PAGINA);
        $cnpjs = array_column($r['itens'], 'cnpj');
        $naCarteira = $enviadas = [];
        if ($cnpjs) {
            $marcas = implode(',', array_fill(0, count($cnpjs), '?'));
            foreach (Banco::todos("SELECT c.id, c.cnpj, c.tipo, u.nome AS responsavel FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id
                    WHERE c.excluido_em IS NULL AND c.cnpj IN ($marcas)", $cnpjs) as $c) {
                $naCarteira[$c['cnpj']] = $c;
            }
            foreach (Banco::todos("SELECT cnpj, MAX(criado_em) AS em FROM prospeccao_envios WHERE cnpj IN ($marcas) GROUP BY cnpj", $cnpjs) as $e) {
                $enviadas[$e['cnpj']] = $e['em'];
            }
        }
        foreach ($r['itens'] as &$e) {
            $e['cliente'] = $naCarteira[$e['cnpj']] ?? null;
            $e['enviada_em'] = $enviadas[$e['cnpj']] ?? null;
        }
        unset($e);
        $r['pagina'] = max(1, $pagina);
        $r['paginas'] = max(1, (int) ceil($r['total'] / self::POR_PAGINA));
        return $r;
    }

    /** Números do topo e a cobertura por cidade (quanto da base já está na carteira) */
    public static function resumo(string $uf): array
    {
        $cidades = Integracoes::baseCnpj()->cidades($uf, array_keys(self::cnaes()));
        $empresas = array_sum(array_column($cidades, 'empresas'));
        $na = array_sum(array_column($cidades, 'na_carteira'));
        $base = Integracoes::baseCnpj()->buscar(['uf' => $uf, 'cnaes' => array_keys(self::cnaes()), 'meses' => 12], 1, 1);
        foreach ($cidades as &$c) {
            $c['empresas'] = (int) $c['empresas'];
            $c['na_carteira'] = (int) $c['na_carteira'];
            $c['cobertura'] = $c['empresas'] ? round($c['na_carteira'] / $c['empresas'] * 100, 1) : 0;
        }
        unset($c);
        return ['empresas' => $empresas, 'na_carteira' => $na, 'cobertura' => $empresas ? round($na / $empresas * 100, 1) : 0,
            'novas_12m' => $base['total'], 'cidades' => $cidades];
    }

    // ------------------------------------------------------------------
    // Para abordar (fila única de prospecção)
    // ------------------------------------------------------------------

    /** Filtros da fila (origem, cidade, prioridade, responsável — este só para quem vê todas as carteiras) */
    public static function filtrosFila(array $g): array
    {
        $num = fn($v) => ctype_digit((string) $v) ? (string) $v : '';
        return Cliente::filtrosDe([
            'tipo' => 'lead', 'origem' => $num($g['origem'] ?? ''), 'cidade' => (string) ($g['cidade'] ?? ''),
            'prioridade' => in_array($g['prioridade'] ?? '', Listas::prioridades(), true) ? $g['prioridade'] : '',
            'responsavel' => \App\Core\Auth::pode('carteira.ver_todas') ? $num($g['responsavel'] ?? '') : '',
        ]);
    }

    /**
     * Painel da fila. $f = filtrosFila(). Respeita a carteira de cada um (vendedor vê só os dele).
     * Retorna números, conversão por origem, funil, cidades, próximos para abordar (paginado) e abordagens da equipe.
     */
    public static function painelFila(array $f, int $pagina): array
    {
        [$where, $par] = Cliente::montarWhere($f);
        $numeros = Banco::um("SELECT COUNT(*) AS leads, SUM(c.contatado = 0) AS aguardando,
                SUM(e.nome IN ('Em negociação', 'Proposta enviada', 'Aguardando retorno')) AS negociacao
            FROM clientes c LEFT JOIN funil_etapas e ON e.id = c.etapa_id WHERE $where", $par);
        $abordados = (int) Banco::valor("SELECT COUNT(DISTINCT c.id) FROM clientes c JOIN cliente_eventos ev ON ev.cliente_id = c.id
            WHERE $where AND ev.tipo IN ('contato','ligacao','whatsapp','visita','email') AND ev.ocorrido_em >= CURDATE() - INTERVAL 6 DAY", $par);

        // Conversão por origem: considera leads E clientes da mesma origem (quem virou cliente saiu da fila)
        $fOrigem = $f;
        $fOrigem['tipo'] = '';
        [$w2, $p2] = Cliente::montarWhere($fOrigem);
        $origens = Banco::todos("SELECT COALESCE(o.nome, 'Sem origem') AS origem, c.origem_id, COUNT(*) AS total,
                SUM(c.tipo = 'lead') AS leads, SUM(c.tipo = 'lead' AND c.contatado = 0) AS aguardando,
                SUM(c.contatado = 1 OR c.tipo = 'cliente') AS abordados, SUM(c.tipo = 'cliente') AS clientes
            FROM clientes c LEFT JOIN origens o ON o.id = c.origem_id WHERE $w2 GROUP BY c.origem_id, o.nome ORDER BY total DESC", $p2);
        foreach ($origens as &$o) {
            $o['conversao'] = $o['total'] ? round($o['clientes'] / $o['total'] * 100, 1) : 0;
        }
        unset($o);
        $clientes = array_sum(array_column($origens, 'clientes'));

        $etapas = array_column(Cliente::contarPor($f, 'etapa'), 'total', 'chave');
        $funil = [];
        foreach (Listas::etapas() as $e) {
            if ($e['tipo'] === 'aberta') {
                $funil[] = ['id' => (int) $e['id'], 'nome' => $e['nome'], 'cor' => $e['cor'], 'total' => (int) ($etapas[$e['id']] ?? 0)];
            }
        }

        // Próximos para abordar: ainda sem contato, prioridade alta primeiro, depois os mais antigos na fila
        $porPagina = 20;
        $total = (int) $numeros['aguardando'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $prioridades = implode(',', array_map(fn($x) => Banco::pdo()->quote($x), Listas::prioridades()));
        $proximos = Banco::todos("SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.bairro, c.prioridade, c.telefone, c.whatsapp,
                c.criado_em, o.nome AS origem, s.nome AS segmento, u.nome AS responsavel
            FROM clientes c LEFT JOIN origens o ON o.id = c.origem_id LEFT JOIN segmentos s ON s.id = c.segmento_id LEFT JOIN usuarios u ON u.id = c.responsavel_id
            WHERE $where AND c.contatado = 0 ORDER BY FIELD(c.prioridade, $prioridades), c.criado_em, c.id
            LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $par);

        // Abordagens da equipe nos últimos 7 dias (só para quem vê todas as carteiras)
        $equipe = [];
        if (\App\Core\Auth::pode('carteira.ver_todas')) {
            $equipe = Banco::todos("SELECT u.nome, COUNT(*) AS contatos, COUNT(DISTINCT ev.cliente_id) AS lojas
                FROM cliente_eventos ev JOIN clientes c ON c.id = ev.cliente_id JOIN usuarios u ON u.id = ev.usuario_id
                WHERE c.excluido_em IS NULL AND c.tipo = 'lead' AND ev.tipo IN ('contato','ligacao','whatsapp','visita','email')
                  AND ev.ocorrido_em >= CURDATE() - INTERVAL 6 DAY GROUP BY u.id, u.nome ORDER BY contatos DESC");
        }
        return [
            'leads' => (int) $numeros['leads'], 'aguardando' => $total, 'negociacao' => (int) $numeros['negociacao'],
            'abordados_7d' => $abordados, 'clientes' => $clientes,
            'origens' => $origens, 'funil' => $funil, 'cidades' => Cliente::contarPor($f, 'cidade', 10),
            'proximos' => $proximos, 'pagina' => $pagina, 'paginas' => $paginas, 'equipe' => $equipe,
        ];
    }

    /** Quantos leads aguardam o primeiro contato na carteira da pessoa (Início e One) */
    public static function qtdAguardando(): int
    {
        [$where, $par] = Cliente::montarWhere(Cliente::filtrosDe(['tipo' => 'lead']));
        return (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE $where AND c.contatado = 0", $par);
    }

    /**
     * Envia os CNPJs escolhidos para o funil como leads.
     * $responsavelId null = fila da função de novos leads (regra da carteira).
     * Retorna ['criados' => n, 'existentes' => n, 'ids' => [...]]
     */
    public static function enviarParaFunil(array $cnpjs, ?int $responsavelId, int $usuarioId): array
    {
        $cnpjs = array_values(array_unique(array_filter(array_map(fn($c) => preg_replace('/\D/', '', (string) $c), $cnpjs), fn($c) => strlen($c) === 14)));
        if (!$cnpjs) {
            throw new RuntimeException('Marque pelo menos uma empresa.');
        }
        if (count($cnpjs) > 200) {
            throw new RuntimeException('Envie no máximo 200 empresas por vez.');
        }
        $origem = 'Prospecção automática';
        $etapa = (string) Config::get('prospeccao.etapa_inicial', 'Não abordado');
        $segmentos = ['4771701' => 'Farmácia', '4771702' => 'Farmácia', '4644301' => 'Farmácia', '4729699' => 'Produtos naturais'];
        $criados = $existentes = 0;
        $ids = [];
        foreach ($cnpjs as $cnpj) {
            $e = Banco::um('SELECT * FROM prospeccao_empresas WHERE cnpj = ?', [$cnpj]);
            if (!$e) {
                continue;
            }
            if (Banco::valor('SELECT 1 FROM clientes WHERE cnpj = ? AND excluido_em IS NULL', [$cnpj])) {
                $existentes++;
                continue;
            }
            $id = Banco::transacao(function () use ($e, $responsavelId, $origem, $etapa, $segmentos, $usuarioId) {
                $tel = Normalizar::telefone($e['telefone']);
                $id = CarteiraService::criar([
                    'tipo' => 'lead', 'razao_social' => $e['razao_social'], 'nome_fantasia' => $e['nome_fantasia'], 'cnpj' => $e['cnpj'],
                    'endereco' => $e['endereco'], 'bairro' => $e['bairro'], 'cep' => $e['cep'], 'cidade' => $e['cidade'], 'uf' => $e['uf'],
                    'telefone' => $tel, 'whatsapp' => $tel && strlen($tel) === 11 ? $tel : null, 'email' => $e['email'],
                    'segmento' => $segmentos[$e['cnae']] ?? 'Outros', 'origem' => $origem, 'etapa' => $etapa,
                    'origem_detalhe' => 'CNAE ' . self::cnaeFormatado($e['cnae']) . ' · ' . ($e['cnae_descricao'] ? mb_substr($e['cnae_descricao'], 0, 120) : ''),
                    'validacao' => 'Dados da base pública de CNPJs: confirmar telefone e contato',
                    'responsavel_id' => $responsavelId,
                ], $usuarioId, 'Enviada pela Prospecção (base de CNPJs)');
                Banco::inserir('prospeccao_envios', ['cnpj' => $e['cnpj'], 'cliente_id' => $id, 'usuario_id' => $usuarioId]);
                return $id;
            });
            $ids[] = $id;
            $criados++;
        }
        if ($criados) {
            Auditoria::registrar('prospeccao', null, 'enviar_funil', null, ['criados' => $criados, 'existentes' => $existentes,
                'responsavel_id' => $responsavelId]);
        }
        return compact('criados', 'existentes', 'ids');
    }
}
