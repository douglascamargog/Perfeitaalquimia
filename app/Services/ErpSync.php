<?php
namespace App\Services;

use App\Core\Banco;
use App\Integrations\Integracoes;

/**
 * Sincronização com o ERP (Omie; simulado no protótipo). Roda pelo cron/sincronizar_erp.php.
 *  - Preços das 3 tabelas e estoque → tabelas locais (o catálogo lê daqui, rápido).
 *  - Pedidos que ficaram sem envio → envia de novo (o uuid evita duplicar).
 *  - Pedidos aprovados → consulta se foram faturados (NF) e atualiza a ficha do cliente.
 */
class ErpSync
{
    /** Produto local pelo código do ERP (omie_codigo) ou, no simulado, pelo slug */
    private static function mapaProdutos(): array
    {
        $mapa = [];
        foreach (Banco::todos('SELECT id, slug, omie_codigo FROM produtos') as $p) {
            $mapa[$p['slug']] = (int) $p['id'];
            if ($p['omie_codigo']) {
                $mapa[$p['omie_codigo']] = (int) $p['id'];
            }
        }
        return $mapa;
    }

    /**
     * Código dos produtos vindo do ERP (campo "codigo" do Omie). Só preenche quem ainda não tem código:
     * o código digitado na tela Produtos não é sobrescrito. O ERP simulado não manda código (para não confundir com o real).
     */
    public static function codigos(): int
    {
        if (Integracoes::erpSimulado()) {
            return 0;
        }
        $produtos = self::mapaProdutos();
        $n = 0;
        foreach (Integracoes::erp()->listarProdutos() as $p) {
            $id = $produtos[$p['omie_codigo'] ?? ''] ?? null;
            $codigo = trim((string) ($p['codigo'] ?? ''));
            if ($id && $codigo !== '' && !Banco::valor('SELECT 1 FROM produtos WHERE codigo = ?', [$codigo])) {
                $n += Banco::executar("UPDATE produtos SET codigo = ? WHERE id = ? AND (codigo IS NULL OR codigo = '')", [mb_substr($codigo, 0, 40), $id]);
            }
        }
        return $n;
    }

    public static function precos(): int
    {
        $produtos = self::mapaProdutos();
        $tabelas = array_column(Banco::todos('SELECT id, slug FROM tabelas_preco'), 'id', 'slug');
        $n = 0;
        foreach (Integracoes::erp()->listarPrecos() as $p) {
            $produto = $produtos[$p['omie_codigo_produto']] ?? null;
            $tabela = $tabelas[$p['tabela']] ?? null;
            if (!$produto || !$tabela) {
                continue;
            }
            Banco::executar('INSERT INTO produto_precos (produto_id, tabela_preco_id, preco) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE preco = VALUES(preco)', [$produto, $tabela, round((float) $p['preco'], 2)]);
            $n++;
        }
        return $n;
    }

    public static function estoque(): int
    {
        $produtos = self::mapaProdutos();
        $n = 0;
        foreach (Integracoes::erp()->listarEstoque() as $e) {
            if ($produto = $produtos[$e['omie_codigo_produto']] ?? null) {
                Banco::executar('INSERT INTO produto_estoque (produto_id, quantidade) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)',
                    [$produto, (int) $e['quantidade']]);
                $n++;
            }
        }
        return $n;
    }

    /** Reenvia pendentes e confere faturamento dos aprovados. Devolve [enviados, faturados] */
    public static function pedidos(): array
    {
        $enviados = 0;
        foreach (Banco::todos("SELECT id FROM pedidos WHERE omie_codigo IS NULL AND situacao IN ('aguardando_aprovacao', 'aprovado') ORDER BY id LIMIT 200") as ['id' => $id]) {
            $enviados += PedidoService::enviarAoErp((int) $id) ? 1 : 0;
        }
        $faturados = 0;
        foreach (Banco::todos("SELECT * FROM pedidos WHERE situacao = 'aprovado' AND omie_codigo IS NOT NULL ORDER BY id LIMIT 200") as $p) {
            try {
                $r = Integracoes::erp()->consultarPedido((string) $p['omie_codigo']);
            } catch (\Throwable $erro) {
                error_log('Consultar pedido ' . $p['id'] . ': ' . $erro->getMessage());
                continue;
            }
            if (!empty($r['faturado'])) {
                PedidoService::marcarFaturado($p, (string) $r['numero_nf']);
                $faturados++;
            }
        }
        return [$enviados, $faturados];
    }

    public static function tudo(): array
    {
        [$enviados, $faturados] = self::pedidos();
        $r = ['codigos' => self::codigos(), 'precos' => self::precos(), 'estoque' => self::estoque(), 'enviados' => $enviados, 'faturados' => $faturados];
        // Guarda a última sincronização para a tela Administração › Integração Omie
        @file_put_contents(self::ARQUIVO_ULTIMA, json_encode($r + ['em' => date('Y-m-d H:i:s')]));
        return $r;
    }

    private const ARQUIVO_ULTIMA = RAIZ . '/storage/logs/erp_ultima_sincronizacao.json';

    /** Resultado da última sincronização (cron ou botão), ou null */
    public static function ultima(): ?array
    {
        return is_file(self::ARQUIVO_ULTIMA) ? (json_decode((string) file_get_contents(self::ARQUIVO_ULTIMA), true) ?: null) : null;
    }
}
