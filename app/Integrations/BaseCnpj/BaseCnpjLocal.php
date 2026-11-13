<?php
namespace App\Integrations\BaseCnpj;

use App\Core\Banco;

/**
 * Base de CNPJs guardada no próprio banco (tabela prospeccao_empresas).
 * "Na carteira" = o CNPJ já existe em clientes (lead ou cliente, não excluído).
 */
class BaseCnpjLocal implements BaseCnpjInterface
{
    private const NA_CARTEIRA = 'EXISTS (SELECT 1 FROM clientes c WHERE c.cnpj = e.cnpj AND c.excluido_em IS NULL)';

    public function buscar(array $f, int $pagina, int $porPagina): array
    {
        [$where, $par] = $this->where($f);
        $total = (int) Banco::valor("SELECT COUNT(*) FROM prospeccao_empresas e WHERE $where", $par);
        $offset = max(0, ($pagina - 1) * $porPagina);
        $itens = Banco::todos("SELECT e.* FROM prospeccao_empresas e WHERE $where
            ORDER BY e.cidade, COALESCE(e.nome_fantasia, e.razao_social) LIMIT $porPagina OFFSET $offset", $par);
        return compact('itens', 'total');
    }

    public function cidades(string $uf, array $cnaes): array
    {
        [$where, $par] = $this->where(['uf' => $uf, 'cnaes' => $cnaes]);
        return Banco::todos("SELECT e.cidade, COUNT(*) AS empresas, SUM(" . self::NA_CARTEIRA . ") AS na_carteira
            FROM prospeccao_empresas e WHERE $where GROUP BY e.cidade ORDER BY empresas DESC", $par);
    }

    public function ufs(): array
    {
        return array_column(Banco::todos('SELECT DISTINCT uf FROM prospeccao_empresas ORDER BY uf'), 'uf');
    }

    private function where(array $f): array
    {
        $w = ["e.situacao = 'ATIVA'"];
        $par = [];
        foreach (['uf', 'cidade', 'bairro', 'cnae'] as $campo) {
            if (($f[$campo] ?? '') !== '') {
                $w[] = "e.$campo = ?";
                $par[] = $f[$campo];
            }
        }
        if (!empty($f['cnaes'])) {
            $w[] = 'e.cnae IN (' . implode(',', array_fill(0, count($f['cnaes']), '?')) . ')';
            array_push($par, ...array_values($f['cnaes']));
        }
        if ((int) ($f['meses'] ?? 0) > 0) {
            $w[] = 'e.abertura >= CURDATE() - INTERVAL ? MONTH';
            $par[] = (int) $f['meses'];
        }
        if (($f['q'] ?? '') !== '') {
            $digitos = preg_replace('/\D/', '', $f['q']);
            $w[] = strlen($digitos) >= 8 ? 'e.cnpj LIKE ?' : '(e.razao_social LIKE ? OR e.nome_fantasia LIKE ?)';
            if (strlen($digitos) >= 8) {
                $par[] = "$digitos%";
            } else {
                array_push($par, "%{$f['q']}%", "%{$f['q']}%");
            }
        }
        if (!empty($f['esconder_carteira'])) {
            $w[] = 'NOT ' . self::NA_CARTEIRA;
        }
        return [implode(' AND ', $w), $par];
    }
}
