<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;

/**
 * Setores de venda: funções marcadas como "É um setor de venda" (Administração › Funções),
 * como Televendas, Externo, Terceirização e Prospecção e Reativação.
 *
 * Cada setor tem a sua carteira (clientes.funcao_id) e o seu grupo no menu lateral, com as
 * abas de venda (Carteira, Meu mês, Agenda, Pedidos, Recompra) filtradas por ele.
 * O setor em que a pessoa está fica na sessão (vem do ?setor= dos links do menu).
 * O setor só FILTRA mais: as permissões continuam as mesmas (vendedor vê só a própria carteira).
 */
class Setores
{
    private static ?array $cache = null;

    /** Todos os setores ativos, na ordem das funções: [slug => [id, slug, nome, icone]] */
    public static function todos(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Banco::todos('SELECT id, slug, nome, icone FROM funcoes WHERE setor_venda = 1 AND ativa = 1 ORDER BY ordem, nome') as $f) {
                self::$cache[$f['slug']] = $f + ['icone' => $f['icone'] ?: 'pessoas'];
            }
        }
        return self::$cache;
    }

    /**
     * Setores que a pessoa vê no menu: quem vê todas as carteiras vê todos;
     * os outros, só os das suas funções. Quem não trabalha em setor de venda não vê setores.
     */
    public static function daPessoa(): array
    {
        $todos = self::todos();
        if (!Auth::logado() || Auth::pode('carteira.ver_todas')) {
            return $todos;
        }
        $minhas = array_column(Auth::usuario()['funcoes'] ?? [], 'slug');
        $meus = array_intersect_key($todos, array_flip($minhas));
        // Vendedor sem função de setor: vê todos (para não ficar sem menu). Quem não vende (Produção, Marketing...): nenhum.
        return $meus ?: (Auth::pode('carteira.ver_propria') ? $todos : []);
    }

    /** Guarda o setor escolhido no menu (?setor=). Setor que a pessoa não vê é ignorado; ?setor= vazio limpa. */
    public static function lerDaUrl(): void
    {
        if (!isset($_GET['setor']) || !Auth::logado()) {
            return;
        }
        $slug = (string) $_GET['setor'];
        if ($slug === '') {
            Sessao::remover('setor');
        } elseif (isset(self::daPessoa()[$slug])) {
            Sessao::set('setor', $slug);
        }
    }

    /** O setor atual (array) ou null = todos os setores (escolhido pelos links do menu) */
    public static function atual(): ?array
    {
        $slug = (string) Sessao::get('setor', '');
        return $slug !== '' ? (self::daPessoa()[$slug] ?? null) : null;
    }

    /** Id da função do setor atual (para filtrar clientes, pedidos, agenda e recompra) */
    public static function funcaoIdAtual(): ?int
    {
        $s = self::atual();
        return $s ? (int) $s['id'] : null;
    }
}
