<?php
namespace App\Services;

use App\Core\Config;

/**
 * Cores do sistema (Meu perfil › Aparência).
 *
 * Cada pessoa pode escolher a cor PRIMÁRIA (menu, topo, botões, títulos) e a SECUNDÁRIA (destaques:
 * item ativo, selos, números). Sem escolha, vale o padrão da empresa (Configurações › visual.*).
 *
 * A partir das duas cores, este arquivo calcula os tons que o CSS usa (variáveis --primaria, --primaria-escura...)
 * e a cor do texto que fica legível por cima de cada uma (branco ou escuro, pela regra de contraste WCAG).
 * O mesmo cálculo existe em public/assets/js/aparencia.js para a prévia ao vivo.
 */
class Aparencia
{
    /** Combinações prontas: [nome, primária, secundária] */
    public const COMBINACOES = [
        'perfeita'  => ['Perfeita Alquimia', '#025150', '#5cd4b6'],
        'logo'      => ['Petróleo + verde da logo', '#025150', '#15a454'],
        'classico'  => ['Clássico One', '#14251b', '#caff4d'],
        'oceano'    => ['Oceano', '#0b3c5d', '#5bc0eb'],
        'terra'     => ['Terra', '#5a3825', '#e8a33d'],
        'grafite'   => ['Grafite', '#2b2f33', '#9ad0c2'],
    ];

    public const PADRAO_PRIMARIA = '#025150';
    public const PADRAO_SECUNDARIA = '#5cd4b6';

    /** Cores da empresa (o admin muda em Configurações) */
    public static function padrao(): array
    {
        return [
            self::valida((string) Config::get('visual.cor_primaria', self::PADRAO_PRIMARIA)) ?? self::PADRAO_PRIMARIA,
            self::valida((string) Config::get('visual.cor_secundaria', self::PADRAO_SECUNDARIA)) ?? self::PADRAO_SECUNDARIA,
        ];
    }

    /** Cores desta pessoa (ou as da empresa) */
    public static function daPessoa(?array $usuario): array
    {
        [$p, $s] = self::padrao();
        return [
            self::valida((string) ($usuario['cor_primaria'] ?? '')) ?? $p,
            self::valida((string) ($usuario['cor_secundaria'] ?? '')) ?? $s,
        ];
    }

    /** "#AbC123" → "#abc123"; qualquer outra coisa → null */
    public static function valida(string $cor): ?string
    {
        $cor = strtolower(trim($cor));
        return preg_match('/^#[0-9a-f]{6}$/', $cor) ? $cor : null;
    }

    /** Bloco <style> com as variáveis (vai no <head>, depois dos CSS) */
    public static function estilo(?array $usuario = null, bool $daEmpresa = false): string
    {
        [$p, $s] = $daEmpresa ? self::padrao() : self::daPessoa($usuario);
        $linhas = [];
        foreach (self::variaveis($p, $s) as $nome => $valor) {
            $linhas[] = "$nome:$valor";
        }
        return '<style id="cores-one">:root{' . implode(';', $linhas) . '}</style>';
    }

    /** Todas as variáveis de cor calculadas a partir da primária e da secundária */
    public static function variaveis(string $p, string $s): array
    {
        $branco = '#ffffff';
        $preto = '#000000';
        // Primária escura: títulos e botões sobre branco → precisa de contraste 4,5:1 com o branco
        $escura = self::mistura($p, $preto, 0.18);
        for ($i = 0; $i < 12 && self::contraste($escura, $branco) < 4.5; $i++) {
            $escura = self::mistura($escura, $preto, 0.15);
        }
        // Secundária escura: contornos e foco (3:1 com o branco)
        $secEscura = self::mistura($s, $preto, 0.15);
        for ($i = 0; $i < 12 && self::contraste($secEscura, $branco) < 3; $i++) {
            $secEscura = self::mistura($secEscura, $preto, 0.15);
        }
        return [
            '--primaria'          => $p,
            '--primaria-texto'    => self::textoSobre($p),
            '--primaria-escura'   => $escura,
            '--primaria-clara'    => self::mistura($p, $branco, 0.92),
            '--primaria-suave'    => self::mistura($p, $branco, 0.82),
            '--secundaria'        => $s,
            '--secundaria-texto'  => self::textoSobre($s),
            '--secundaria-escura' => $secEscura,
            '--secundaria-clara'  => self::mistura($s, $branco, 0.78),
            '--fundo'             => self::mistura($p, '#f6f7f6', 0.955),
        ];
    }

    /** Branco ou quase-preto, o que tiver mais contraste com o fundo */
    public static function textoSobre(string $fundo): string
    {
        return self::contraste($fundo, '#ffffff') >= self::contraste($fundo, '#10201d') ? '#ffffff' : '#10201d';
    }

    /** Mistura $a com $b: $quanto = 0 → só $a, 1 → só $b */
    public static function mistura(string $a, string $b, float $quanto): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        $m = fn($x, $y) => (int) round($x + ($y - $x) * $quanto);
        return sprintf('#%02x%02x%02x', $m($r1, $r2), $m($g1, $g2), $m($b1, $b2));
    }

    /** Contraste WCAG entre duas cores (1 a 21) */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminancia(string $cor): float
    {
        $canal = function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($cor);
        return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
    }

    private static function rgb(string $cor): array
    {
        $h = ltrim($cor, '#');
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }
}
