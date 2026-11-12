<?php
namespace App\Models;

use App\Core\Banco;
use App\Core\Config;

/**
 * Listas configuráveis usadas em filtros e formulários (segmentos, etapas, origens, cidades, UFs...).
 * Guardadas em memória durante a requisição para não consultar o banco várias vezes.
 */
class Listas
{
    private static array $cache = [];

    private static function lembrar(string $chave, callable $buscar): array
    {
        return self::$cache[$chave] ??= $buscar();
    }

    /** Limpa a memória (usado depois de alterar alguma lista) */
    public static function esquecer(): void
    {
        self::$cache = [];
    }

    public static function segmentos(bool $somenteAtivos = true): array
    {
        return self::lembrar("seg$somenteAtivos", fn() => Banco::todos('SELECT * FROM segmentos ' . ($somenteAtivos ? 'WHERE ativo = 1 ' : '') . 'ORDER BY ordem, nome'));
    }

    public static function etapas(bool $somenteAtivas = true): array
    {
        return self::lembrar("eta$somenteAtivas", fn() => Banco::todos('SELECT * FROM funil_etapas ' . ($somenteAtivas ? 'WHERE ativa = 1 ' : '') . 'ORDER BY ordem, id'));
    }

    public static function origens(bool $somenteAtivas = true): array
    {
        return self::lembrar("ori$somenteAtivas", fn() => Banco::todos('SELECT * FROM origens ' . ($somenteAtivas ? 'WHERE ativo = 1 ' : '') . 'ORDER BY nome'));
    }

    public static function regioes(): array
    {
        return self::lembrar('reg', fn() => Banco::todos('SELECT * FROM regioes ORDER BY id'));
    }

    public static function tabelasPreco(): array
    {
        return self::lembrar('tab', fn() => Banco::todos('SELECT * FROM tabelas_preco WHERE ativa = 1 ORDER BY id'));
    }

    public static function prioridades(): array
    {
        return Config::get('carteira.prioridades', ['Alta', 'Média', 'Baixa']);
    }

    /** As 27 UFs do Brasil */
    public static function ufs(): array
    {
        return ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
    }

    /** UFs que têm clientes cadastrados (para o filtro) */
    public static function ufsComClientes(): array
    {
        return self::lembrar('ufc', fn() => array_column(Banco::todos('SELECT DISTINCT uf FROM clientes WHERE excluido_em IS NULL AND uf IS NOT NULL ORDER BY uf'), 'uf'));
    }

    /** Cidades que têm clientes, opcionalmente de uma UF */
    public static function cidadesComClientes(string $uf = ''): array
    {
        return self::lembrar("cid$uf", fn() => Banco::todos(
            'SELECT DISTINCT cidade, uf FROM clientes WHERE excluido_em IS NULL AND cidade IS NOT NULL' . ($uf !== '' ? ' AND uf = ?' : '') . ' ORDER BY cidade',
            $uf !== '' ? [$uf] : []
        ));
    }

    /** Pessoas que podem ser responsáveis por clientes (usuários ativos com código de vendedor ou função) */
    public static function vendedores(): array
    {
        return self::lembrar('ven', fn() => Banco::todos(
            'SELECT DISTINCT u.id, u.nome FROM usuarios u
               LEFT JOIN usuario_funcoes uf ON uf.usuario_id = u.id
              WHERE u.ativo = 1 AND (u.codigo_vendedor IS NOT NULL OR uf.funcao_id IS NOT NULL)
              ORDER BY u.nome'
        ));
    }

    /** Procura um item de lista pelo nome (sem diferenciar maiúsculas/acentos) e devolve o id */
    public static function idPorNome(array $lista, ?string $nome, string $campo = 'nome'): ?int
    {
        $alvo = \App\Services\Normalizar::semAcento((string) $nome);
        foreach ($lista as $item) {
            if (\App\Services\Normalizar::semAcento($item[$campo]) === $alvo) {
                return (int) $item['id'];
            }
        }
        return null;
    }

    /** Região (id) de uma UF */
    public static function regiaoDaUf(?string $uf): ?int
    {
        $mapa = self::lembrar('ruf', fn() => array_column(Banco::todos('SELECT uf, regiao_id FROM regiao_ufs'), 'regiao_id', 'uf'));
        return isset($mapa[$uf]) ? (int) $mapa[$uf] : null;
    }
}
