<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;

/**
 * Equipe de vendas e "Meus clientes" do Meu mês.
 * Frente de venda = a da função da CARTEIRA do cliente (funcoes.frente: externo, televendas, terceirizacao),
 * a mesma regra do Painel de vendas (filtro por função). Só pedidos faturados contam.
 */
class EquipeVendasService
{
    public const FRENTES = ['externo' => 'Externos', 'televendas' => 'Televendas', 'terceirizacao' => 'Terceirização'];

    /** Números de cada frente no mês: faturado, meta (soma das metas das funções), pedidos, ticket, clientes */
    public static function frentes(string $mes): array
    {
        [$de, $ate] = ["$mes-01", date('Y-m-t', strtotime("$mes-01"))];
        $fat = [];
        foreach (Banco::todos("SELECT f.frente, COALESCE(SUM(p.total), 0) AS faturado, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS compraram
                FROM pedidos p JOIN clientes c ON c.id = p.cliente_id JOIN funcoes f ON f.id = c.funcao_id
                WHERE p.situacao = 'faturado' AND p.faturado_em >= ? AND p.faturado_em < ? + INTERVAL 1 DAY AND f.frente IS NOT NULL
                GROUP BY f.frente", [$de, $ate]) as $l) {
            $fat[$l['frente']] = $l;
        }
        $metas = array_column(Banco::todos("SELECT f.frente, SUM(m.valor) AS meta FROM metas m JOIN funcoes f ON f.id = m.alvo_id
                WHERE m.tipo = 'funcao' AND m.mes = ? AND f.frente IS NOT NULL GROUP BY f.frente", [$mes]), 'meta', 'frente');
        $dias = (int) (Config::get('carteira.faixas_sem_compra', [30, 60, 90])[1] ?? 60);
        $carteira = [];
        foreach (Banco::todos("SELECT f.frente, COUNT(*) AS clientes, SUM(c.ultima_compra_em < CURDATE() - INTERVAL ? DAY) AS parados
                FROM clientes c JOIN funcoes f ON f.id = c.funcao_id
                WHERE c.excluido_em IS NULL AND c.tipo = 'cliente' AND f.frente IS NOT NULL GROUP BY f.frente", [$dias]) as $l) {
            $carteira[$l['frente']] = $l;
        }
        $saida = [];
        foreach (self::FRENTES as $chave => $nome) {
            $f = $fat[$chave] ?? ['faturado' => 0, 'pedidos' => 0, 'compraram' => 0];
            $meta = (float) ($metas[$chave] ?? 0);
            $saida[$chave] = [
                'nome' => $nome, 'faturado' => (float) $f['faturado'], 'pedidos' => (int) $f['pedidos'], 'compraram' => (int) $f['compraram'],
                'ticket' => $f['pedidos'] ? (float) $f['faturado'] / (int) $f['pedidos'] : 0.0, 'meta' => $meta,
                'percentual' => $meta > 0 ? (float) $f['faturado'] / $meta * 100 : null,
                'clientes' => (int) ($carteira[$chave]['clientes'] ?? 0), 'parados' => (int) ($carteira[$chave]['parados'] ?? 0), 'dias_parado' => $dias,
            ];
        }
        return $saida;
    }

    /** Ranking de vendedores no mês (todos veem, com nome), com a frente de cada um e o % da meta individual */
    public static function ranking(string $mes, string $frente = ''): array
    {
        [$de, $ate] = ["$mes-01", date('Y-m-t', strtotime("$mes-01"))];
        $linhas = Banco::todos(
            "SELECT u.id, u.nome, u.apelido, u.foto,
                    (SELECT f.frente FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id AND f.frente IS NOT NULL ORDER BY f.ordem, f.id LIMIT 1) AS frente,
                    COALESCE(SUM(p.total), 0) AS faturado, COUNT(p.id) AS pedidos,
                    (SELECT m.valor FROM metas m WHERE m.tipo = 'vendedor' AND m.alvo_id = u.id AND m.mes = ?) AS meta
               FROM usuarios u
               LEFT JOIN pedidos p ON p.vendedor_id = u.id AND p.situacao = 'faturado' AND p.faturado_em >= ? AND p.faturado_em < ? + INTERVAL 1 DAY
              WHERE u.ativo = 1 AND EXISTS (SELECT 1 FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id AND f.frente IS NOT NULL)
              GROUP BY u.id ORDER BY faturado DESC, u.nome",
            [$mes, $de, $ate]
        );
        foreach ($linhas as &$l) {
            $l['faturado'] = (float) $l['faturado'];
            $l['percentual'] = $l['meta'] > 0 ? $l['faturado'] / (float) $l['meta'] * 100 : null;
        }
        unset($l);
        return $frente !== '' ? array_values(array_filter($linhas, fn($l) => $l['frente'] === $frente)) : $linhas;
    }

    /** Faturado por frente nos últimos 6 meses (gráfico) */
    public static function porMes(string $mes, int $meses = 6): array
    {
        $inicio = date('Y-m-01', strtotime("$mes-01 -" . ($meses - 1) . ' months'));
        $dados = [];
        foreach (Banco::todos("SELECT DATE_FORMAT(p.faturado_em, '%Y-%m') AS mes, f.frente, SUM(p.total) AS valor
                FROM pedidos p JOIN clientes c ON c.id = p.cliente_id JOIN funcoes f ON f.id = c.funcao_id
                WHERE p.situacao = 'faturado' AND p.faturado_em >= ? AND p.faturado_em < ? + INTERVAL 1 MONTH AND f.frente IS NOT NULL
                GROUP BY mes, f.frente", [$inicio, "$mes-01"]) as $l) {
            $dados[$l['frente']][$l['mes']] = (float) $l['valor'];
        }
        $rotulos = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $rotulos[] = date('Y-m', strtotime("$mes-01 -$i months"));
        }
        $series = [];
        foreach (self::FRENTES as $chave => $nome) {
            $series[] = ['nome' => $nome, 'valores' => array_map(fn($m) => $dados[$chave][$m] ?? 0, $rotulos)];
        }
        return ['meses' => $rotulos, 'series' => $series];
    }

    // ------------------------------------------------------------------
    // Meu mês › Meus clientes (só a carteira da pessoa)
    // ------------------------------------------------------------------

    /** Os que mais compraram nos últimos 12 meses (pedidos em que a pessoa é a vendedora) */
    public static function melhores(int $usuarioId, int $limite = 10, ?string $mes = null): array
    {
        $f = $mes ? ['de' => "$mes-01", 'ate' => date('Y-m-t', strtotime("$mes-01"))] : ['de' => date('Y-m-d', strtotime('-12 months')), 'ate' => date('Y-m-d')];
        $linhas = VendasService::ranking($f + ['vendedor' => $usuarioId], 'cliente', $limite);
        foreach ($linhas as &$l) {
            $l['ultima_compra_em'] = Banco::valor('SELECT ultima_compra_em FROM clientes WHERE id = ?', [$l['id']]);
        }
        return $linhas;
    }

    /** Clientes da carteira da pessoa sem comprar há mais de X dias (o mais parado primeiro) */
    public static function semComprar(int $usuarioId, int $dias = 30, int $limite = 60, ?int $setor = null): array
    {
        $doSetor = $setor ? ' AND c.funcao_id = ' . (int) $setor : ''; // setor de venda escolhido no menu
        return Banco::todos("SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.cidade, c.uf, c.telefone, c.whatsapp, c.ultima_compra_em,
                DATEDIFF(CURDATE(), c.ultima_compra_em) AS dias
            FROM clientes c WHERE c.responsavel_id = ? AND c.excluido_em IS NULL AND c.tipo = 'cliente'
              AND c.ultima_compra_em IS NOT NULL AND c.ultima_compra_em < CURDATE() - INTERVAL ? DAY$doSetor
            ORDER BY c.ultima_compra_em ASC LIMIT " . (int) $limite, [$usuarioId, $dias]);
    }
}
