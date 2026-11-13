<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Terceirização (marca própria do cliente).
 *
 * - Cliente de terceirização = quem tem produto cadastrado aqui (de QUALQUER carteira, ex.: Televendas)
 *   ou quem está numa carteira cuja função é da frente "terceirizacao" (Administração › Funções).
 * - Produto do cliente: o NOSSO produto com a marca dele (mesma ficha técnica) ou uma FÓRMULA PRÓPRIA,
 *   que vira um produto exclusivo daquele cliente (produtos.cliente_exclusivo_id, nunca no catálogo nem para outros).
 * - Rótulos: estoque por cliente × produto; entram quando chegam, saem ao concluir a ordem de produção para o cliente
 *   (1 rótulo por unidade). Sem rótulo suficiente, a ordem não conclui.
 * - Pedido: o produto terceirizado é uma linha própria do pedido (chave "t{id}"), junto com os produtos normais.
 */
class TerceirizacaoService
{
    /** Tabela de preço usada nos produtos terceirizados (a de slug "terceirizacao"; se não houver, a da última tabela ativa) */
    public static function tabelaId(): int
    {
        return (int) (Banco::valor("SELECT id FROM tabelas_preco WHERE slug = 'terceirizacao' AND ativa = 1")
            ?: Banco::valor('SELECT id FROM tabelas_preco WHERE ativa = 1 ORDER BY id DESC LIMIT 1'));
    }

    /** Condição SQL: o cliente (alias c) é de terceirização */
    public const EH_CLIENTE = "(EXISTS (SELECT 1 FROM terceirizacao_produtos tp WHERE tp.cliente_id = c.id AND tp.ativo = 1)
        OR c.funcao_id IN (SELECT id FROM funcoes WHERE frente = 'terceirizacao'))";

    /** Clientes de terceirização com os números da lista */
    public static function clientes(string $busca = ''): array
    {
        $p = [];
        $w = 'c.excluido_em IS NULL AND ' . self::EH_CLIENTE;
        if ($busca !== '') {
            $w .= ' AND (c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR c.cnpj LIKE ? OR c.cidade LIKE ?)';
            $t = '%' . $busca . '%';
            array_push($p, $t, $t, '%' . preg_replace('/\D/', '', $busca) . '%', $t);
        }
        $de = date('Y-m-d', strtotime('-12 months'));
        return Banco::todos(
            "SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.ultima_compra_em, f.nome AS carteira, f.frente, u.nome AS responsavel,
                    (SELECT COUNT(*) FROM terceirizacao_produtos tp WHERE tp.cliente_id = c.id AND tp.ativo = 1) AS produtos,
                    (SELECT COUNT(*) FROM terceirizacao_produtos tp WHERE tp.cliente_id = c.id AND tp.ativo = 1 AND tp.rotulos_estoque <= tp.rotulos_minimo) AS rotulos_alerta,
                    (SELECT COALESCE(SUM(i.total), 0) FROM pedido_itens i JOIN pedidos pd ON pd.id = i.pedido_id
                      WHERE pd.cliente_id = c.id AND i.terceirizacao_produto_id IS NOT NULL AND pd.situacao = 'faturado' AND pd.faturado_em >= ?) AS faturado_terc
               FROM clientes c LEFT JOIN funcoes f ON f.id = c.funcao_id LEFT JOIN usuarios u ON u.id = c.responsavel_id
              WHERE $w ORDER BY COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social)",
            array_merge([$de], $p)
        );
    }

    /** Produtos de um cliente (ou de todos), com estoque de rótulos e preço */
    public static function produtos(?int $clienteId = null, bool $soAtivos = true): array
    {
        $w = ['1 = 1'];
        $p = [self::tabelaId()];
        if ($clienteId) {
            $w[] = 'tp.cliente_id = ?';
            $p[] = $clienteId;
        }
        if ($soAtivos) {
            $w[] = 'tp.ativo = 1';
        }
        return Banco::todos(
            'SELECT tp.*, pr.nome AS produto_nome, pr.codigo AS produto_codigo, pr.embalagem, pr.cliente_exclusivo_id,
                    COALESCE(tp.preco, pp.preco) AS preco_final,
                    COALESCE(NULLIF(c.nome_fantasia, ""), c.razao_social) AS cliente_nome,
                    (SELECT COALESCE(SUM(o.quantidade), 0) FROM ordens_producao o WHERE o.cliente_id = tp.cliente_id AND o.produto_id = tp.produto_id AND o.situacao = "planejada") AS em_ordens
               FROM terceirizacao_produtos tp
               JOIN produtos pr ON pr.id = tp.produto_id
               JOIN clientes c ON c.id = tp.cliente_id
               LEFT JOIN produto_precos pp ON pp.produto_id = tp.produto_id AND pp.tabela_preco_id = ?
              WHERE ' . implode(' AND ', $w) . ' ORDER BY cliente_nome, tp.nome_rotulo',
            $p
        );
    }

    public static function produto(int $id): ?array
    {
        $l = array_values(array_filter(self::produtos(null, false), fn($x) => (int) $x['id'] === $id));
        return $l[0] ?? null;
    }

    /**
     * Cadastra/edita o produto de um cliente.
     * $d: cliente_id, tipo ('nosso' | 'proprio'), produto_id (nosso), nome_rotulo, codigo_cliente, preco, rotulos_minimo, observacoes
     *     fórmula própria: embalagem (o produto exclusivo é criado aqui; a ficha técnica se cadastra na Produção)
     */
    public static function salvarProduto(?int $id, array $d, ?int $usuarioId): int
    {
        $clienteId = (int) ($d['cliente_id'] ?? 0);
        $nome = trim((string) ($d['nome_rotulo'] ?? ''));
        if (!$id && !Banco::valor('SELECT 1 FROM clientes WHERE id = ? AND excluido_em IS NULL', [$clienteId])) {
            throw new RuntimeException('Escolha o cliente.');
        }
        if ($nome === '' || mb_strlen($nome) > 160) {
            throw new RuntimeException('Informe o nome do produto como vai no rótulo do cliente.');
        }
        $preco = trim((string) ($d['preco'] ?? ''));
        $preco = $preco === '' ? null : ProducaoService::numero($preco);
        if ($preco !== null && $preco < 0) {
            throw new RuntimeException('O preço não pode ser negativo.');
        }
        $dados = [
            'nome_rotulo' => $nome,
            'codigo_cliente' => mb_substr(trim((string) ($d['codigo_cliente'] ?? '')), 0, 40) ?: null,
            'preco' => $preco,
            'rotulos_minimo' => max(0, (int) ($d['rotulos_minimo'] ?? 0)),
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 500) ?: null,
            'ativo' => isset($d['ativo']) ? (int) (bool) $d['ativo'] : 1,
        ];
        return Banco::transacao(function () use ($id, $d, $dados, $clienteId, $usuarioId, $nome) {
            if ($id) {
                $antes = Banco::um('SELECT * FROM terceirizacao_produtos WHERE id = ?', [$id]);
                if (!$antes) {
                    throw new RuntimeException('Produto não encontrado.');
                }
                Banco::atualizar('terceirizacao_produtos', $id, $dados);
                // Fórmula própria: o nome do produto exclusivo acompanha o nome do rótulo
                Banco::executar('UPDATE produtos SET nome = ? WHERE id = ? AND cliente_exclusivo_id IS NOT NULL', [$nome, $antes['produto_id']]);
                Auditoria::registrar('terceirizacao_produto', $id, 'editar', $antes, $dados);
                return $id;
            }
            if (($d['tipo'] ?? 'nosso') === 'proprio') {
                // Fórmula própria: produto exclusivo deste cliente (fora do catálogo), com ficha técnica na Produção
                $produtoId = Banco::inserir('produtos', [
                    'slug' => 'exclusivo-' . $clienteId . '-' . bin2hex(random_bytes(4)), 'nome' => $nome,
                    'embalagem' => mb_substr(trim((string) ($d['embalagem'] ?? '')), 0, 120) ?: null,
                    'ativo' => 1, 'visivel_catalogo' => 0, 'cliente_exclusivo_id' => $clienteId, 'ordem' => 9999, 'ficha_json' => '{}',
                ]);
                // Código próprio da fábrica para a fórmula exclusiva (o código do cliente fica em codigo_cliente)
                Banco::executar('UPDATE produtos SET codigo = ? WHERE id = ?', ['EX-' . $produtoId, $produtoId]);
            } else {
                $produtoId = (int) ($d['produto_id'] ?? 0);
                if (!Banco::valor('SELECT 1 FROM produtos WHERE id = ? AND ativo = 1 AND cliente_exclusivo_id IS NULL', [$produtoId])) {
                    throw new RuntimeException('Escolha o nosso produto que o cliente vai fazer com a marca dele.');
                }
            }
            if (Banco::valor('SELECT 1 FROM terceirizacao_produtos WHERE cliente_id = ? AND produto_id = ?', [$clienteId, $produtoId])) {
                throw new RuntimeException('Este cliente já tem esse produto cadastrado.');
            }
            $novo = Banco::inserir('terceirizacao_produtos', $dados + ['cliente_id' => $clienteId, 'produto_id' => $produtoId]);
            Auditoria::registrar('terceirizacao_produto', $novo, 'criar', null, $dados + ['cliente_id' => $clienteId, 'produto_id' => $produtoId, 'tipo' => $d['tipo'] ?? 'nosso']);
            return $novo;
        });
    }

    /** Entrada (rótulos que chegaram) ou ajuste (contagem). Devolve o saldo novo. */
    public static function movimentarRotulos(int $tpId, string $tipo, int $quantidade, string $observacao, ?int $usuarioId, ?int $ordemId = null, ?string $quando = null): int
    {
        if (!in_array($tipo, ['entrada', 'consumo', 'ajuste'], true)) {
            throw new RuntimeException('Movimento inválido.');
        }
        return Banco::transacao(function () use ($tpId, $tipo, $quantidade, $observacao, $usuarioId, $ordemId, $quando) {
            $atual = Banco::valor('SELECT rotulos_estoque FROM terceirizacao_produtos WHERE id = ? FOR UPDATE', [$tpId]);
            if ($atual === null || $atual === false) {
                throw new RuntimeException('Produto do cliente não encontrado.');
            }
            if ($tipo === 'entrada' && $quantidade < 1) {
                throw new RuntimeException('Informe quantos rótulos chegaram.');
            }
            // ajuste: $quantidade é a CONTAGEM; o movimento é a diferença
            $mov = $tipo === 'ajuste' ? $quantidade - (int) $atual : $quantidade;
            if ($tipo === 'ajuste' && $quantidade < 0) {
                throw new RuntimeException('A contagem não pode ser negativa.');
            }
            $saldo = (int) $atual + $mov;
            if ($saldo < 0) {
                throw new RuntimeException('Rótulos insuficientes.');
            }
            Banco::executar('UPDATE terceirizacao_produtos SET rotulos_estoque = ? WHERE id = ?', [$saldo, $tpId]);
            Banco::inserir('terceirizacao_rotulos_mov', array_filter([
                'terceirizacao_produto_id' => $tpId, 'tipo' => $tipo, 'quantidade' => $mov, 'saldo_apos' => $saldo,
                'ordem_producao_id' => $ordemId, 'observacao' => mb_substr(trim($observacao), 0, 255) ?: null,
                'usuario_id' => $usuarioId, 'criado_em' => $quando,
            ], fn($v) => $v !== null));
            if ($quando === null && $tipo !== 'consumo') {
                Auditoria::registrar('rotulos', $tpId, $tipo, ['saldo' => (int) $atual], ['saldo' => $saldo, 'observacao' => $observacao]);
            }
            return $saldo;
        });
    }

    public static function movimentos(?int $tpId = null, int $limite = 60): array
    {
        return Banco::todos(
            'SELECT m.*, tp.nome_rotulo, COALESCE(NULLIF(c.nome_fantasia, ""), c.razao_social) AS cliente_nome, u.nome AS usuario
               FROM terceirizacao_rotulos_mov m JOIN terceirizacao_produtos tp ON tp.id = m.terceirizacao_produto_id
               JOIN clientes c ON c.id = tp.cliente_id LEFT JOIN usuarios u ON u.id = m.usuario_id
              ' . ($tpId ? 'WHERE m.terceirizacao_produto_id = ' . (int) $tpId : '') . ' ORDER BY m.id DESC LIMIT ' . (int) $limite
        );
    }

    /** Produto do cliente usado numa ordem de produção (ou null se a ordem não é de terceirização) */
    public static function daOrdem(array $op): ?array
    {
        if (empty($op['cliente_id'])) {
            return null;
        }
        return Banco::um('SELECT * FROM terceirizacao_produtos WHERE cliente_id = ? AND produto_id = ?', [$op['cliente_id'], $op['produto_id']]) ?: null;
    }

    /** Rótulos em alerta: abaixo do mínimo ou que não dão para as ordens planejadas */
    public static function alertas(): array
    {
        return array_values(array_filter(self::produtos(), fn($p) => (int) $p['rotulos_estoque'] <= (int) $p['rotulos_minimo']
            || (int) $p['rotulos_estoque'] < (int) $p['em_ordens']));
    }

    /** Linhas terceirizadas que o cliente pode pedir: [chave "t{id}" => produto com preço] */
    public static function linhasDoPedido(int $clienteId): array
    {
        $linhas = [];
        foreach (self::produtos($clienteId) as $p) {
            if ($p['preco_final'] !== null) {
                $linhas['t' . $p['id']] = $p;
            }
        }
        return $linhas;
    }

    /** Clientes que fazem um produto (para a ordem de produção) */
    public static function clientesDoProduto(int $produtoId): array
    {
        return Banco::todos('SELECT tp.cliente_id AS id, tp.rotulos_estoque, tp.nome_rotulo, COALESCE(NULLIF(c.nome_fantasia, ""), c.razao_social) AS nome
            FROM terceirizacao_produtos tp JOIN clientes c ON c.id = tp.cliente_id WHERE tp.produto_id = ? AND tp.ativo = 1 ORDER BY nome', [$produtoId]);
    }

    /** Faturado em produtos terceirizados (todas as carteiras) no período */
    public static function faturado(string $de, string $ate): float
    {
        return (float) Banco::valor("SELECT COALESCE(SUM(i.total), 0) FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id
            WHERE i.terceirizacao_produto_id IS NOT NULL AND p.situacao = 'faturado' AND p.faturado_em BETWEEN ? AND ?", [$de . ' 00:00:00', $ate . ' 23:59:59']);
    }
}
