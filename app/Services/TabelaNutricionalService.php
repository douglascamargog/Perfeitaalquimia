<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Tabela nutricional do produto (modelo Anvisa · RDC 429/2020 e IN 75/2020), digitada na ficha técnica.
 * O %VD é calculado pelos valores diários de referência (adultos) quando não é informado à mão.
 * Sai em HTML (tela e ficha impressa), PNG (desenhado no navegador) e PDF (gerado aqui, sem biblioteca).
 */
class TabelaNutricionalService
{
    /**
     * Valores diários de referência (VDR) para adultos — IN 75/2020, Anexo II. Chave = nome sem acento, minúsculo.
     * Quantidade na mesma unidade da coluna (mg, µg ou g). Ajuste à mão o %VD na linha se precisar de outro valor.
     */
    public const VDR = [
        'valor energetico' => [2000, 'kcal'], 'carboidratos' => [300, 'g'], 'proteinas' => [50, 'g'], 'gorduras totais' => [65, 'g'],
        'gorduras saturadas' => [20, 'g'], 'fibras alimentares' => [25, 'g'], 'sodio' => [2000, 'mg'], 'acucares adicionados' => [50, 'g'],
        'calcio' => [1000, 'mg'], 'ferro' => [14, 'mg'], 'magnesio' => [420, 'mg'], 'zinco' => [11, 'mg'], 'fosforo' => [700, 'mg'],
        'potassio' => [3500, 'mg'], 'selenio' => [60, 'µg'], 'cobre' => [900, 'µg'], 'manganes' => [3, 'mg'], 'iodo' => [150, 'µg'],
        'cromo' => [35, 'µg'], 'molibdenio' => [45, 'µg'], 'fluor' => [4, 'mg'], 'colina' => [550, 'mg'],
        'vitamina a' => [800, 'µg'], 'vitamina d' => [15, 'µg'], 'vitamina e' => [15, 'mg'], 'vitamina k' => [120, 'µg'], 'vitamina c' => [100, 'mg'],
        'vitamina b1' => [1.2, 'mg'], 'tiamina' => [1.2, 'mg'], 'vitamina b2' => [1.2, 'mg'], 'riboflavina' => [1.2, 'mg'], 'niacina' => [15, 'mg'],
        'vitamina b3' => [15, 'mg'], 'acido pantotenico' => [5, 'mg'], 'vitamina b5' => [5, 'mg'], 'vitamina b6' => [1.3, 'mg'], 'biotina' => [30, 'µg'],
        'acido folico' => [400, 'µg'], 'folato' => [400, 'µg'], 'vitamina b9' => [400, 'µg'], 'vitamina b12' => [2.4, 'µg'],
    ];

    public const NOTA_PADRAO = 'Não contém quantidades significativas de valor energético, carboidratos, açúcares totais, açúcares adicionados, proteínas, gorduras totais, gorduras saturadas, gorduras trans, fibras alimentares e sódio.';
    public const RODAPE = '*Percentual de valores diários fornecidos pela porção';

    /** A tabela do produto (ou uma em branco), já com o %VD calculado em cada linha ('vd_final') */
    public static function doProduto(int $produtoId): array
    {
        $t = Banco::um('SELECT * FROM produto_nutricional WHERE produto_id = ?', [$produtoId]) ?: ['produto_id' => $produtoId, 'porcoes_embalagem' => '', 'porcao' => '',
            'porcao_coluna' => '', 'linhas' => '[]', 'nota' => self::NOTA_PADRAO, 'atualizado_em' => null];
        $t['linhas'] = array_map(fn($l) => $l + ['vd_final' => self::vd($l)], json_decode((string) $t['linhas'], true) ?: []);
        $t['coluna'] = trim((string) $t['porcao_coluna']) ?: (preg_match('/^\s*([\d.,]+\s*(g|ml|mg))/iu', (string) $t['porcao'], $m) ? $m[1] : 'Porção');
        return $t;
    }

    /** %VD: o digitado à mão ou calculado pelo VDR (vazio quando o nutriente não tem VDR) */
    public static function vd(array $l): string
    {
        $manual = trim((string) ($l['vd'] ?? ''));
        if ($manual !== '') {
            return rtrim($manual, '%');
        }
        $chave = mb_strtolower(trim(Normalizar::semAcento((string) ($l['nutriente'] ?? ''))));
        $ref = self::VDR[$chave] ?? null;
        $qtd = ProducaoService::numero((string) ($l['quantidade'] ?? ''));
        if (!$ref || $qtd <= 0) {
            return '';
        }
        $unidade = str_replace(['mcg', 'ug'], 'µg', mb_strtolower(trim((string) ($l['unidade'] ?? ''))));
        $fator = ['g' => 1e6, 'mg' => 1e3, 'µg' => 1, 'kcal' => 1][$unidade] ?? null;
        $fatorRef = ['g' => 1e6, 'mg' => 1e3, 'µg' => 1, 'kcal' => 1][$ref[1]];
        if ($fator === null || ($unidade === 'kcal') !== ($ref[1] === 'kcal')) {
            return '';
        }
        return (string) (int) round($qtd * $fator / ($ref[0] * $fatorRef) * 100);
    }

    /** Grava a tabela digitada na ficha técnica */
    public static function salvar(int $produtoId, array $d, ?int $usuarioId): void
    {
        if (!Banco::valor('SELECT 1 FROM produtos WHERE id = ?', [$produtoId])) {
            throw new RuntimeException('Produto não encontrado.');
        }
        $linhas = [];
        foreach ((array) ($d['nutriente'] ?? []) as $i => $nome) {
            $nome = mb_substr(trim((string) $nome), 0, 80);
            if ($nome === '') {
                continue;
            }
            $linhas[] = ['nutriente' => $nome, 'unidade' => mb_substr(trim((string) ($d['unidade'][$i] ?? '')), 0, 10),
                'quantidade' => mb_substr(trim((string) ($d['quantidade'][$i] ?? '')), 0, 20), 'vd' => mb_substr(trim((string) ($d['vd'][$i] ?? '')), 0, 10)];
        }
        $dados = [
            'porcoes_embalagem' => mb_substr(trim((string) ($d['porcoes_embalagem'] ?? '')), 0, 40) ?: null,
            'porcao' => mb_substr(trim((string) ($d['porcao'] ?? '')), 0, 80) ?: null,
            'porcao_coluna' => mb_substr(trim((string) ($d['porcao_coluna'] ?? '')), 0, 20) ?: null,
            'linhas' => json_encode($linhas, JSON_UNESCAPED_UNICODE),
            'nota' => trim((string) ($d['nota'] ?? '')) ?: null,
        ];
        Banco::executar('INSERT INTO produto_nutricional (produto_id, porcoes_embalagem, porcao, porcao_coluna, linhas, nota, atualizado_por, atualizado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE porcoes_embalagem = VALUES(porcoes_embalagem), porcao = VALUES(porcao),
            porcao_coluna = VALUES(porcao_coluna), linhas = VALUES(linhas), nota = VALUES(nota), atualizado_por = VALUES(atualizado_por), atualizado_em = NOW()',
            [$produtoId, $dados['porcoes_embalagem'], $dados['porcao'], $dados['porcao_coluna'], $dados['linhas'], $dados['nota'], $usuarioId]);
        Auditoria::registrar('produto', $produtoId, 'tabela_nutricional', null, $dados);
    }

    // ------------------------------------------------------------------
    // PDF (vetorial, sem biblioteca): mesma aparência da tabela da tela
    // ------------------------------------------------------------------

    /** Conteúdo do arquivo PDF da tabela */
    public static function pdf(array $t): string
    {
        $W = 300; // largura da tabela em pontos (~10,6 cm)
        $m = 8;   // margem interna
        $txt = fn(string $s) => '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $s)) . ')';
        $largura = function (string $s, float $tam, bool $negrito) { // largura aproximada (Helvetica)
            // largura média da Helvetica: maiúsculas e % são mais largas que minúsculas
            $soma = 0.0;
            foreach (mb_str_split($s) as $c) {
                $soma += match (true) {
                    $c === ' ' || $c === '.' || $c === ',' || $c === '(' || $c === ')' || $c === 'i' || $c === 'l' || $c === '*' => 0.3,
                    $c === '%' || $c === 'M' || $c === 'W' || $c === 'm' || $c === 'w' => 0.9,
                    ctype_upper(Normalizar::semAcento($c)) => 0.7,
                    ctype_digit($c) => 0.56,
                    default => 0.53,
                };
            }
            return $soma * $tam * ($negrito ? 1.08 : 1.0);
        };
        $quebrar = function (string $texto, float $tam, float $max) use ($largura) {
            $linhas = [];
            $atual = '';
            foreach (preg_split('/\s+/', trim($texto)) as $p) {
                $teste = $atual === '' ? $p : "$atual $p";
                if ($largura($teste, $tam, false) > $max && $atual !== '') {
                    $linhas[] = $atual;
                    $atual = $p;
                } else {
                    $atual = $teste;
                }
            }
            if ($atual !== '') {
                $linhas[] = $atual;
            }
            return $linhas;
        };
        $nota = $quebrar((string) ($t['nota'] ?: self::NOTA_PADRAO), 9, $W - 2 * $m);
        $rodape = $quebrar(self::RODAPE, 9, $W - 2 * $m);
        $altura = 34 + 34 + 20 + count($t['linhas']) * 18 + count($nota) * 11 + 10 + count($rodape) * 11 + 12;
        $H = $altura + 20;
        $ops = [];
        $y = $H - 10; // topo da tabela
        $x0 = 10;
        $c1 = $x0 + $W * 0.62; // começo da coluna da porção
        $c2 = $x0 + $W * 0.82; // começo da coluna %VD
        $ret = fn(float $x, float $yy, float $w, float $h, float $e) => sprintf("%.2f w %.2f %.2f %.2f %.2f re S", $e, $x, $yy, $w, $h);
        $lin = fn(float $xa, float $ya, float $xb, float $yb, float $e) => sprintf("%.2f w %.2f %.2f m %.2f %.2f l S", $e, $xa, $ya, $xb, $yb);
        $escreve = fn(float $x, float $yy, string $s, float $tam, bool $negrito = false) => sprintf('BT /%s %.1f Tf %.2f %.2f Td %s Tj ET', $negrito ? 'F2' : 'F1', $tam, $x, $yy, $txt($s));
        $direita = fn(float $xd, float $yy, string $s, float $tam, bool $negrito = false) => $escreve($xd - $largura($s, $tam, $negrito), $yy, $s, $tam, $negrito);
        $ops[] = $ret($x0, $y - $altura, $W, $altura, 1.6);
        // título
        $ops[] = $escreve($x0 + $m, $y - 24, 'INFORMAÇÃO NUTRICIONAL', 17, true);
        $y -= 32;
        $ops[] = $lin($x0, $y, $x0 + $W, $y, 1.2);
        $ops[] = $escreve($x0 + $m, $y - 13, 'Porções por embalagem: ' . $t['porcoes_embalagem'], 11);
        $ops[] = $escreve($x0 + $m, $y - 27, 'Porção: ' . $t['porcao'], 11);
        $y -= 34;
        $ops[] = $lin($x0, $y, $x0 + $W, $y, 2.4);
        // cabeçalho das colunas
        $ops[] = $direita($c2 - 10, $y - 14, (string) $t['coluna'], 11, true);
        $ops[] = $direita($x0 + $W - 12, $y - 14, '%VD*', 11, true);
        $ops[] = $lin($c1, $y, $c1, $y - 20, 0.8);
        $ops[] = $lin($c2, $y, $c2, $y - 20, 0.8);
        $y -= 20;
        $ops[] = $lin($x0, $y, $x0 + $W, $y, 1);
        foreach ($t['linhas'] as $l) {
            $nome = $l['nutriente'] . ($l['unidade'] !== '' ? ' (' . $l['unidade'] . ')' : '');
            $ops[] = $escreve($x0 + $m, $y - 13, $nome, 10.5);
            $ops[] = $direita($c2 - 10, $y - 13, (string) $l['quantidade'], 10.5);
            $ops[] = $direita($x0 + $W - 12, $y - 13, (string) $l['vd_final'], 10.5);
            $ops[] = $lin($c1, $y, $c1, $y - 18, 0.8);
            $ops[] = $lin($c2, $y, $c2, $y - 18, 0.8);
            $y -= 18;
            $ops[] = $lin($x0, $y, $x0 + $W, $y, 0.8);
        }
        $y -= 3;
        foreach ($nota as $linha) {
            $y -= 11;
            $ops[] = $escreve($x0 + $m, $y, $linha, 9);
        }
        $y -= 6;
        $ops[] = $lin($x0, $y, $x0 + $W, $y, 1);
        foreach ($rodape as $linha) {
            $y -= 11;
            $ops[] = $escreve($x0 + $m, $y, $linha, 9);
        }
        $conteudo = implode("\n", $ops);
        $largPag = $W + 20;
        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $largPag $H] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . strlen($conteudo) . " >>\nstream\n$conteudo\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($objs as $i => $o) {
            $pos[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($pos as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        return $pdf . 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
