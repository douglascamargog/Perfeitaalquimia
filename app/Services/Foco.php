<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;

/**
 * FOCO = o que o sistema mostra por iniciativa própria para a pessoa (cartões do Início, dicas do One,
 * sino e números vermelhos do menu). É diferente de ACESSO (o que a pessoa pode abrir).
 *
 * Vem das FUNÇÕES da pessoa (Administração › Funções). Sem função, vem do perfil.
 * No Meu perfil cada um pode escolher outras áreas. Aparece só o que está no foco E com acesso.
 */
class Foco
{
    public const AREAS = [
        'comercial' => 'Comercial (carteira, agenda, recompra, prospecção, pedidos)',
        'aprovacao' => 'Aprovação de pedidos',
        'cobranca'  => 'Cobrança',
        'marketing' => 'Marketing (posts, campanhas, e-commerce, leads)',
        'producao'  => 'Produção',
        'diretoria' => 'Diretoria (resumo da empresa: faturado, aprovação, cobrança, produção)',
    ];

    /** Padrão de quem não tem função */
    private const POR_PERFIL = ['admin' => ['diretoria'], 'gestor' => ['comercial', 'diretoria'], 'vendedor' => ['comercial'],
        'financeiro' => ['aprovacao', 'cobranca'], 'marketing' => ['marketing'], 'producao' => ['producao']];

    private static array $cache = [];

    /** Áreas que a pessoa acompanha */
    public static function areasDe(int $usuarioId): array
    {
        if (isset(self::$cache[$usuarioId])) {
            return self::$cache[$usuarioId];
        }
        $u = Banco::um('SELECT u.areas_foco, p.slug AS perfil FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.id = ?', [$usuarioId]);
        if (!$u) {
            return [];
        }
        if ($u['areas_foco'] !== null) { // escolha pessoal (Meu perfil)
            $areas = self::lista($u['areas_foco']);
        } else {
            $areas = self::dasFuncoes($usuarioId) ?: (self::POR_PERFIL[$u['perfil']] ?? []);
        }
        return self::$cache[$usuarioId] = $areas;
    }

    /** Áreas que vêm das funções da pessoa (o padrão do Meu perfil) */
    public static function dasFuncoes(int $usuarioId): array
    {
        $areas = [];
        foreach (Banco::todos('SELECT f.areas_foco FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ? AND f.ativa = 1', [$usuarioId]) as $f) {
            $areas = [...$areas, ...self::lista((string) $f['areas_foco'])];
        }
        return array_values(array_unique($areas));
    }

    /** A pessoa logada acompanha esta área? (uma das áreas, se vier mais de uma) */
    public static function tem(string ...$areas): bool
    {
        $minhas = self::areasDe((int) Auth::id());
        return (bool) array_intersect($areas, $minhas);
    }

    /** "comercial,marketing" → ['comercial', 'marketing'] (só áreas conhecidas) */
    public static function lista(string $texto): array
    {
        return array_values(array_intersect(array_map('trim', explode(',', $texto)), array_keys(self::AREAS)));
    }

    /**
     * Admin (Administração › Usuários) define o que aparece para a pessoa:
     * $seguirFuncoes = true → NULL (acompanha as funções, mesmo que elas mudem depois); senão grava as áreas marcadas.
     */
    public static function salvarEscolha(int $usuarioId, bool $seguirFuncoes, array $areas): void
    {
        $valor = $seguirFuncoes ? null : implode(',', self::lista(implode(',', $areas)));
        Banco::executar('UPDATE usuarios SET areas_foco = ? WHERE id = ?', [$valor, $usuarioId]);
        self::limparCache();
    }

    public static function limparCache(): void
    {
        self::$cache = [];
    }
}
