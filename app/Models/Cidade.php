<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Cidades com coordenadas. Se a cidade ainda não existe, é criada sem coordenadas
 * (o mapa então usa o ponto da UF até alguém preencher lat/lng).
 */
class Cidade
{
    private static array $cache = [];

    /** Devolve o id da cidade (cria se não existir) */
    public static function idOuCriar(?string $nome, ?string $uf): ?int
    {
        $nome = trim((string) $nome);
        $uf = strtoupper(trim((string) $uf));
        if ($nome === '' || strlen($uf) !== 2) {
            return null;
        }
        $chave = $uf . '|' . mb_strtolower($nome);
        if (!isset(self::$cache[$chave])) {
            $id = Banco::valor('SELECT id FROM cidades WHERE uf = ? AND nome = ?', [$uf, $nome]);
            self::$cache[$chave] = $id ? (int) $id : Banco::inserir('cidades', ['nome' => $nome, 'uf' => $uf]);
        }
        return self::$cache[$chave];
    }

    /** Centro aproximado de cada UF (usado quando a cidade não tem coordenadas) */
    public const CENTRO_UF = [
        'AC' => [-9.0, -70.5], 'AL' => [-9.6, -36.6], 'AM' => [-4.0, -63.0], 'AP' => [1.0, -51.8], 'BA' => [-12.5, -41.7],
        'CE' => [-5.3, -39.3], 'DF' => [-15.8, -47.9], 'ES' => [-19.6, -40.6], 'GO' => [-16.0, -49.6], 'MA' => [-5.0, -45.3],
        'MG' => [-18.5, -44.6], 'MS' => [-20.5, -54.6], 'MT' => [-12.6, -55.9], 'PA' => [-4.0, -52.5], 'PB' => [-7.2, -36.6],
        'PE' => [-8.4, -37.9], 'PI' => [-7.4, -42.6], 'PR' => [-24.6, -51.6], 'RJ' => [-22.3, -42.7], 'RN' => [-5.8, -36.6],
        'RO' => [-10.9, -62.8], 'RR' => [2.0, -61.4], 'RS' => [-29.7, -53.2], 'SC' => [-27.3, -50.4], 'SE' => [-10.6, -37.4],
        'SP' => [-22.2, -48.7], 'TO' => [-10.2, -48.3],
    ];
}
