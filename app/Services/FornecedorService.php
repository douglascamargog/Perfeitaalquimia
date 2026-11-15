<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Fornecedores (V11.0): junta o que já está no sistema — os fornecedores de cada item (mp_fornecedores)
 * e as compras lançadas (NFs) — numa página por fornecedor: o que ele fornece, preços, compras, prazo e documentos.
 * O fornecedor é identificado pelo NOME (como está nos itens e nas NFs); maiúsculas e espaços não importam.
 */
class FornecedorService
{
    private static function chave(string $nome): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $nome)));
    }

    /** Lista de fornecedores com itens, gasto e última compra (12 meses) */
    public static function lista(string $busca = ''): array
    {
        $lista = [];
        foreach (Banco::todos('SELECT fornecedor, MAX(cnpj) AS cnpj, COUNT(DISTINCT materia_prima_id) AS itens, MAX(ultima_compra_em) AS ultima,
                SUM(principal) AS principal_de, AVG(prazo_dias) AS prazo FROM mp_fornecedores WHERE ativo = 1 GROUP BY fornecedor') as $f) {
            $k = self::chave($f['fornecedor']);
            $lista[$k] = ['nome' => trim($f['fornecedor']), 'cnpj' => $f['cnpj'], 'itens' => (int) $f['itens'], 'principal_de' => (int) $f['principal_de'],
                'ultima' => $f['ultima'], 'prazo' => $f['prazo'] !== null ? (float) $f['prazo'] : null, 'gasto' => 0.0, 'nfs' => 0];
        }
        foreach (Banco::todos("SELECT fornecedor, MAX(cnpj) AS cnpj, COUNT(*) AS nfs, SUM(valor_total) AS gasto, MAX(emissao) AS ultima
                FROM compras_lancadas WHERE emissao >= CURDATE() - INTERVAL 12 MONTH GROUP BY fornecedor") as $c) {
            $k = self::chave($c['fornecedor']);
            $lista[$k] ??= ['nome' => trim($c['fornecedor']), 'cnpj' => null, 'itens' => 0, 'principal_de' => 0, 'ultima' => null, 'prazo' => null, 'gasto' => 0.0, 'nfs' => 0];
            $lista[$k]['gasto'] += (float) $c['gasto'];
            $lista[$k]['nfs'] += (int) $c['nfs'];
            $lista[$k]['cnpj'] = $lista[$k]['cnpj'] ?: $c['cnpj'];
            $lista[$k]['ultima'] = max((string) $lista[$k]['ultima'], (string) $c['ultima']) ?: null;
        }
        if ($busca !== '') {
            $b = self::chave($busca);
            $lista = array_filter($lista, fn($f) => str_contains(self::chave($f['nome']), $b) || str_contains((string) $f['cnpj'], preg_replace('/\D/', '', $busca) ?: '#'));
        }
        usort($lista, fn($a, $b) => [$b['gasto'], $b['itens']] <=> [$a['gasto'], $a['itens']]);
        return array_values($lista);
    }

    /** Tudo de um fornecedor (ou null se não existe no sistema) */
    public static function ver(string $nome): ?array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }
        $linhas = Banco::todos('SELECT f.*, m.nome AS item, m.unidade, m.tipo, m.categoria FROM mp_fornecedores f JOIN materias_primas m ON m.id = f.materia_prima_id
            WHERE LOWER(TRIM(f.fornecedor)) = LOWER(TRIM(?)) AND f.ativo = 1 ORDER BY m.nome', [$nome]);
        $compras = Banco::todos('SELECT c.*, (SELECT COUNT(*) FROM compras_lancadas_itens i WHERE i.lancamento_id = c.id) AS qtd_itens
            FROM compras_lancadas c WHERE LOWER(TRIM(c.fornecedor)) = LOWER(TRIM(?)) ORDER BY c.emissao DESC, c.id DESC LIMIT 60', [$nome]);
        if (!$linhas && !$compras) {
            return null;
        }
        // contato: o primeiro preenchido entre os itens (e o CNPJ das NFs)
        $contato = ['cnpj' => null, 'contato' => null, 'telefone' => null, 'email' => null];
        foreach ($linhas as $l) {
            foreach ($contato as $c => $v) {
                $contato[$c] = $v ?: ($l[$c] ?? null);
            }
        }
        foreach ($compras as $c) {
            $contato['cnpj'] = $contato['cnpj'] ?: $c['cnpj'];
        }
        // cada item: o preço dele aqui × o menor preço entre todos os fornecedores do item
        foreach ($linhas as &$l) {
            $menor = Banco::um('SELECT fornecedor, MIN(ultimo_preco) AS preco FROM mp_fornecedores WHERE materia_prima_id = ? AND ativo = 1 AND ultimo_preco > 0
                GROUP BY fornecedor ORDER BY preco LIMIT 1', [$l['materia_prima_id']]);
            $l['outros'] = (int) Banco::valor('SELECT COUNT(*) FROM mp_fornecedores WHERE materia_prima_id = ? AND ativo = 1 AND id <> ?', [$l['materia_prima_id'], $l['id']]);
            $l['menor_preco'] = $menor ? (float) $menor['preco'] : null;
            $l['mais_barato'] = $l['ultimo_preco'] !== null && $menor && (float) $l['ultimo_preco'] <= (float) $menor['preco'] + 0.00001;
            $l['diferenca'] = $l['ultimo_preco'] && $menor && (float) $menor['preco'] > 0 ? ((float) $l['ultimo_preco'] / (float) $menor['preco'] - 1) * 100 : null;
        }
        unset($l);
        // números dos últimos 12 meses
        $desde = date('Y-m-d', strtotime('-12 months'));
        $doAno = array_filter($compras, fn($c) => $c['emissao'] >= $desde);
        $datas = array_unique(array_column($compras, 'emissao'));
        sort($datas);
        $intervalos = [];
        for ($i = 1; $i < count($datas); $i++) {
            $intervalos[] = (strtotime($datas[$i]) - strtotime($datas[$i - 1])) / 86400;
        }
        $porMes = [];
        for ($i = 11; $i >= 0; $i--) {
            $porMes[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = 0.0;
        }
        foreach ($doAno as $c) {
            $m = substr($c['emissao'], 0, 7);
            if (isset($porMes[$m])) {
                $porMes[$m] += (float) $c['valor_total'];
            }
        }
        $prazos = array_filter(array_column($linhas, 'prazo_dias'), fn($p) => $p !== null);
        $ids = array_map('intval', array_column($linhas, 'id'));
        $lanc = array_map('intval', array_column($compras, 'id'));
        $docs = [];
        if ($ids || $lanc) {
            $w = [];
            if ($ids) { $w[] = 'd.fornecedor_id IN (' . implode(',', $ids) . ')'; }
            if ($lanc) { $w[] = 'd.lancamento_id IN (' . implode(',', $lanc) . ')'; }
            $docs = Banco::todos('SELECT d.*, m.nome AS item FROM mp_documentos d JOIN materias_primas m ON m.id = d.materia_prima_id
                WHERE d.arquivado_em IS NULL AND (' . implode(' OR ', $w) . ') ORDER BY d.criado_em DESC LIMIT 40');
        }
        return [
            'nome' => $linhas[0]['fornecedor'] ?? $compras[0]['fornecedor'], 'contato' => $contato, 'itens' => $linhas, 'compras' => $compras, 'documentos' => $docs,
            'numeros' => [
                'gasto_12m' => array_sum(array_map(fn($c) => (float) $c['valor_total'], $doAno)),
                'compras_12m' => count($doAno),
                'ticket' => $doAno ? array_sum(array_map(fn($c) => (float) $c['valor_total'], $doAno)) / count($doAno) : 0,
                'ultima' => $compras[0]['emissao'] ?? (max(array_column($linhas, 'ultima_compra_em') ?: [null])),
                'intervalo' => $intervalos ? (int) round(array_sum($intervalos) / count($intervalos)) : null,
                'prazo' => $prazos ? (int) round(array_sum($prazos) / count($prazos)) : null,
                'mais_barato_em' => count(array_filter($linhas, fn($l) => $l['mais_barato'] && $l['outros'] > 0)),
                'comparaveis' => count(array_filter($linhas, fn($l) => $l['outros'] > 0)),
            ],
            'por_mes' => $porMes,
        ];
    }
}
