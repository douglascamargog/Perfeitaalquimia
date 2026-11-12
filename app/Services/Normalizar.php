<?php
namespace App\Services;

/**
 * Limpeza e padronização de dados vindos de planilhas e cadastros.
 * Usado pela importação, pelo cadastro manual e pelos dados de demonstração.
 */
class Normalizar
{
    /**
     * Apelidos de segmento → segmento padrão.
     * A planilha antiga tinha 9 jeitos diferentes de escrever; aqui todos viram um dos 4 padrões.
     * Para ensinar um apelido novo, acrescente uma palavra-chave na lista (sem acento, minúsculas).
     * A ordem importa: farmácia é testada antes de suplementos ("Farmácia com suplementação" = Farmácia).
     */
    private const SEGMENTOS = [
        'Farmácia'          => ['farmacia', 'drogaria', 'manipulacao'],
        'Suplementos'       => ['suplement', 'nutricao esportiva', 'academia'],
        'Produtos naturais' => ['natura', 'granel', 'cha', 'emporio', 'organic', 'zen'],
    ];

    /** Converte qualquer texto de segmento para um dos padrões (ou 'Outros') */
    public static function segmento(?string $texto): string
    {
        $t = self::semAcento((string) $texto);
        foreach (self::SEGMENTOS as $padrao => $chaves) {
            foreach ($chaves as $chave) {
                if ($chave !== '' && str_contains($t, $chave)) {
                    return $padrao;
                }
            }
        }
        return 'Outros';
    }

    /** Texto em minúsculas, sem acentos e com espaços simples — bom para comparar e deduplicar */
    public static function semAcento(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
            'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /** Chave para achar duplicados sem CNPJ: nome + cidade, sem acentos e sem pontuação */
    public static function chaveNomeCidade(string $nome, string $cidade): string
    {
        $limpar = fn($s) => preg_replace('/[^a-z0-9]/', '', self::semAcento($s));
        return $limpar($nome) . '|' . $limpar($cidade);
    }

    /** CNPJ só com dígitos, ou null se vazio/inválido */
    public static function cnpj(?string $cnpj): ?string
    {
        $d = preg_replace('/\D/', '', (string) $cnpj);
        return self::cnpjValido($d) ? $d : null;
    }

    /** Confere os dígitos verificadores do CNPJ */
    public static function cnpjValido(string $d): bool
    {
        if (strlen($d) !== 14 || preg_match('/^(\d)\1{13}$/', $d)) {
            return false;
        }
        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $d[$i] * $pesos[$i];
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $d[$posicao] !== $digito) {
                return false;
            }
        }
        return true;
    }

    /** Telefone só com dígitos (com DDD), sem o 55 do país. Vazio → null */
    public static function telefone(?string $telefone): ?string
    {
        // Campo com mais de um número ("(51) 3333-4444 / (51) 99999-8888"): fica com o primeiro
        foreach (preg_split('/\/|;|,|\|| e | ou /', (string) $telefone) as $parte) {
            if (strlen(preg_replace('/\D/', '', $parte)) >= 10) {
                $telefone = $parte;
                break;
            }
        }
        $d = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($d) > 13) {
            $d = substr($d, 0, 11);
        }
        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        return strlen($d) >= 10 ? $d : null;
    }

    /** Telefone para exibir: 51999990000 → (51) 99999-0000 */
    public static function telefoneFormatado(?string $d): string
    {
        $d = preg_replace('/\D/', '', (string) $d);
        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            default => $d,
        };
    }

    /** Prioridade dentro da lista configurada (padrão: a do meio) */
    public static function prioridade(?string $texto, array $permitidas): string
    {
        foreach ($permitidas as $p) {
            if (self::semAcento($p) === self::semAcento((string) $texto)) {
                return $p;
            }
        }
        return $permitidas[intdiv(count($permitidas), 2)] ?? 'Média';
    }

    /** Texto vazio vira null; corta no tamanho máximo da coluna */
    public static function texto(?string $texto, int $max = 255): ?string
    {
        $t = trim((string) $texto);
        return $t === '' ? null : mb_substr($t, 0, $max);
    }
}
