<?php
namespace App\Services;

/**
 * Lê planilhas CSV e XLSX sem biblioteca externa.
 * Devolve uma lista de linhas, cada uma como array ['Cabeçalho' => 'valor'].
 *
 * - CSV: aceita separador ";" (Excel em português) ou ",", e arquivos em UTF-8 ou Windows-1252.
 * - XLSX: lê a PRIMEIRA aba (o XLSX é um ZIP com arquivos XML dentro).
 */
class LeitorPlanilha
{
    public const MAX_LINHAS = 50000;

    public static function ler(string $arquivo, string $formato): array
    {
        $linhas = $formato === 'xlsx' ? self::lerXlsx($arquivo) : self::lerCsv($arquivo);
        // Primeira linha = cabeçalho; as linhas totalmente vazias são ignoradas
        $cabecalho = array_map(fn($c) => trim((string) $c), array_shift($linhas) ?? []);
        $resultado = [];
        foreach ($linhas as $linha) {
            if (!array_filter($linha, fn($v) => trim((string) $v) !== '')) {
                continue;
            }
            $item = [];
            foreach ($cabecalho as $i => $nome) {
                if ($nome !== '') {
                    $item[$nome] = trim((string) ($linha[$i] ?? ''));
                }
            }
            $resultado[] = $item;
            if (count($resultado) >= self::MAX_LINHAS) {
                break;
            }
        }
        return $resultado;
    }

    private static function lerCsv(string $arquivo): array
    {
        $conteudo = (string) file_get_contents($arquivo);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo); // tira o BOM
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $primeira = strtok($conteudo, "\n");
        $separador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

        $linhas = [];
        $fluxo = fopen('php://temp', 'r+');
        fwrite($fluxo, $conteudo);
        rewind($fluxo);
        while (($linha = fgetcsv($fluxo, 0, $separador, '"', '')) !== false) {
            $linhas[] = $linha;
        }
        fclose($fluxo);
        return $linhas;
    }

    private static function lerXlsx(string $arquivo): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($arquivo) !== true) {
            throw new \RuntimeException('Não consegui abrir o arquivo XLSX.');
        }
        // Textos repetidos ficam numa tabela compartilhada (sharedStrings)
        $textos = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
            foreach ($sst->si as $si) {
                $textos[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }
        // Primeira aba
        $planilha = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($planilha === false) {
            throw new \RuntimeException('O XLSX não tem a primeira aba (sheet1).');
        }
        $xml = simplexml_load_string($planilha, 'SimpleXMLElement', LIBXML_NONET);
        $linhas = [];
        foreach ($xml->sheetData->row as $row) {
            $linha = [];
            foreach ($row->c as $c) {
                // "C5" → coluna 2 (0 = A)
                $coluna = 0;
                foreach (str_split(preg_replace('/\d/', '', (string) $c['r'])) as $letra) {
                    $coluna = $coluna * 26 + (ord($letra) - 64);
                }
                $tipo = (string) $c['t'];
                $valor = match ($tipo) {
                    's'         => $textos[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                };
                $linha[$coluna - 1] = $valor;
            }
            if ($linha) {
                $max = max(array_keys($linha));
                $linhas[] = array_replace(array_fill(0, $max + 1, ''), $linha);
            }
        }
        return $linhas;
    }
}
