<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Especialistas do One (V15.0): um assistente por setor, com instruções e consultas próprias.
 *  - paraPessoa(): os especialistas que a pessoa vê no chat (o "One" geral + os dos setores dela; admin e diretoria veem todos).
 *  - padrao(): em qual o chat abre (o do setor principal da pessoa; senão o geral).
 */
class OneEspecialistas
{
    private static ?array $cache = null;

    /** Todos os ativos, na ordem */
    public static function ativos(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = Banco::todos('SELECT * FROM one_especialistas WHERE ativo = 1 ORDER BY ordem, nome');
            } catch (\PDOException) {
                self::$cache = []; // antes da atualização do banco
            }
        }
        return self::$cache;
    }

    public static function buscar(string $slug): ?array
    {
        foreach (self::ativos() as $e) {
            if ($e['slug'] === $slug) {
                return $e;
            }
        }
        return null;
    }

    /** Slugs das funções da pessoa logada */
    private static function funcoesDaPessoa(): array
    {
        return array_column(Banco::todos('SELECT f.slug FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ?', [(int) Auth::id()]), 'slug');
    }

    /** Vê todos? (quem administra ou a diretoria) */
    private static function veTodos(): bool
    {
        return Auth::pode('config.gerenciar') || in_array('diretoria', self::funcoesDaPessoa(), true);
    }

    /** Especialistas que aparecem no chat da pessoa */
    public static function paraPessoa(): array
    {
        $minhas = self::funcoesDaPessoa();
        $todos = self::veTodos();
        return array_values(array_filter(self::ativos(), function ($e) use ($minhas, $todos) {
            $funcoes = array_filter(array_map('trim', explode(',', (string) $e['funcoes'])));
            return $todos || !$funcoes || array_intersect($funcoes, $minhas);
        }));
    }

    /** Em qual especialista o chat abre */
    public static function padrao(): string
    {
        $minhas = self::funcoesDaPessoa();
        foreach (self::ativos() as $e) {
            if (array_intersect(array_filter(array_map('trim', explode(',', (string) $e['funcoes']))), $minhas)) {
                return $e['slug'];
            }
        }
        return 'geral';
    }

    /** O especialista pedido, se a pessoa pode usar; senão o padrão dela */
    public static function escolher(string $slug): ?array
    {
        $visiveis = array_column(self::paraPessoa(), null, 'slug');
        return $visiveis[$slug] ?? $visiveis[self::padrao()] ?? ($visiveis['geral'] ?? null);
    }

    /** Consultas liberadas para o especialista */
    public static function consultas(?array $e): ?array
    {
        return $e ? array_values(array_filter(array_map('trim', explode(',', (string) $e['consultas'])))) : null;
    }

    /** Uso do mês por especialista (Administração) */
    public static function usoDoMes(): array
    {
        return array_column(Banco::todos("SELECT COALESCE(especialista, 'geral') AS slug, COUNT(*) AS perguntas, COUNT(DISTINCT usuario_id) AS pessoas,
            COALESCE(SUM(custo_estimado), 0) AS custo FROM one_perguntas WHERE criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY slug"), null, 'slug');
    }
}
