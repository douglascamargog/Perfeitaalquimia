<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Tela Organograma (/organograma): duas visões.
 *  - Interligações: os setores e o que um entrega para o outro (app/dados/interligacoes.php), com números vivos para a gestão.
 *  - Organização: áreas → funções → pessoas (funções e pessoas vêm do banco; a área de cada função é escolhida em Funções).
 * Nenhum nome de pessoa fica no código: tudo vem das funções e de quem está ligado a elas.
 */
class OrganogramaService
{
    /** Áreas do organograma organizacional, na ordem em que aparecem (a Diretoria fica em cima) */
    public const AREAS = [
        'diretoria' => 'Diretoria',
        'comercial' => 'Comercial',
        'marketing' => 'Marketing e E-commerce',
        'financeiro' => 'Financeiro',
        'operacao' => 'Operação e Fábrica',
    ];

    /** Interligações: setores (com o número vivo, se a pessoa pode ver), ligações e o resumo por situação */
    public static function interligacoes(): array
    {
        $d = require RAIZ . '/app/dados/interligacoes.php';
        $numeros = self::podeVerNumeros() ? self::indicadores() : [];
        foreach ($d['setores'] as $id => &$s) {
            $s['id'] = $id;
            $s['indicador'] = isset($s['numero']) ? ($numeros[$s['numero']] ?? null) : null;
        }
        unset($s);
        $resumo = ['ok' => 0, 'parcial' => 0, 'ilha' => 0];
        foreach ($d['ligacoes'] as [, , , $situacao]) {
            $resumo[$situacao] = ($resumo[$situacao] ?? 0) + 1;
        }
        return $d + ['resumo' => $resumo];
    }

    /** Os números vivos são da empresa toda: só para quem vê todas as carteiras ou a gestão */
    public static function podeVerNumeros(): bool
    {
        return Auth::pode('carteira.ver_todas') || Auth::pode('painel.ver');
    }

    /** Um número curto por setor: ['vendas' => ['valor' => 'R$ 120 mil', 'rotulo' => 'faturado no mês'], ...]. Cada um isolado: se falhar, some. */
    public static function indicadores(): array
    {
        $mes = date('Y-m-01');
        $consultas = [
            'vendas' => [fn() => self::dinheiroCurto((float) Banco::valor("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE situacao = 'faturado' AND criado_em >= ?", [$mes])), 'faturado no mês'],
            'aprovacao' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao'"), 'pedidos para aprovar'],
            'cobranca' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM cobrancas WHERE situacao = 'aberta'"), 'casos abertos'],
            'deposito' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE expedicao IN ('aguardando', 'separando', 'separado')"), 'pedidos na fila'],
            'producao' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM ordens_producao WHERE situacao = 'planejada'"), 'ordens planejadas'],
            'compras' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM compras WHERE situacao = 'comprado'"), 'compras a receber'],
            'desenvolvimento' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM dp_projetos WHERE situacao = 'andamento'"), 'projetos em andamento'],
            'marketing' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE situacao IN ('a_fazer', 'fazendo', 'aprovacao')"), 'tarefas abertas'],
            'prospeccao' => [fn() => (int) Banco::valor('SELECT COUNT(*) FROM leads_recebidos WHERE criado_em >= NOW() - INTERVAL 30 DAY'), 'leads em 30 dias'],
            'terceirizacao' => [fn() => (int) Banco::valor('SELECT COUNT(*) FROM terceirizacao_produtos'), 'produtos com a marca'],
            'tray' => [fn() => (int) Banco::valor('SELECT COUNT(*) FROM ecommerce_pedidos WHERE pago_em >= ?', [$mes]), 'pedidos pagos no mês'],
            'gestao' => [fn() => (int) Banco::valor("SELECT COUNT(*) FROM clientes WHERE tipo = 'cliente' AND excluido_em IS NULL AND ultima_compra_em >= CURDATE() - INTERVAL 90 DAY"), 'clientes ativos (90 dias)'],
        ];
        $r = [];
        foreach ($consultas as $id => [$consulta, $rotulo]) {
            try {
                $v = $consulta();
                $r[$id] = ['valor' => is_int($v) ? number_format($v, 0, ',', '.') : $v, 'rotulo' => $rotulo];
            } catch (\Throwable) {
                // tabela que ainda não existe nesta instalação: o setor fica sem número
            }
        }
        return $r;
    }

    /** "R$ 1,2 mi" / "R$ 380 mil" / "R$ 950" */
    public static function dinheiroCurto(float $v): string
    {
        if ($v >= 1000000) {
            return 'R$ ' . number_format($v / 1000000, 1, ',', '.') . ' mi';
        }
        if ($v >= 1000) {
            return 'R$ ' . number_format($v / 1000, 0, ',', '.') . ' mil';
        }
        return 'R$ ' . number_format($v, 0, ',', '.');
    }

    /**
     * Organização: [área => ['nome', 'funcoes' => [[id, nome, descricao, 'pessoas' => [...]]], 'pessoas' => n]], mais as pessoas sem função.
     * Quem é "gestor(a) do setor" (Usuários) aparece primeiro e marcado.
     */
    public static function organizacao(): array
    {
        $pessoas = [];
        foreach (Banco::todos('SELECT uf.funcao_id, uf.gestor, u.id, u.nome, u.apelido, u.foto, p.nome AS perfil
                FROM usuario_funcoes uf JOIN usuarios u ON u.id = uf.usuario_id AND u.ativo = 1 LEFT JOIN perfis p ON p.id = u.perfil_id
                ORDER BY uf.gestor DESC, u.nome') as $p) {
            $pessoas[(int) $p['funcao_id']][] = $p;
        }
        $areas = [];
        foreach (self::AREAS + ['outras' => 'Outras funções'] as $slug => $nome) {
            $areas[$slug] = ['slug' => $slug, 'nome' => $nome, 'funcoes' => [], 'pessoas' => 0];
        }
        foreach (Banco::todos('SELECT id, slug, nome, descricao, area, setor_venda FROM funcoes WHERE ativa = 1 ORDER BY ordem, nome') as $f) {
            $area = isset(self::AREAS[(string) $f['area']]) ? (string) $f['area'] : 'outras';
            $f['pessoas'] = $pessoas[(int) $f['id']] ?? [];
            $areas[$area]['funcoes'][] = $f;
            $areas[$area]['pessoas'] += count($f['pessoas']);
        }
        $semFuncao = Banco::todos('SELECT u.id, u.nome, u.apelido, u.foto, p.nome AS perfil FROM usuarios u LEFT JOIN perfis p ON p.id = u.perfil_id
            WHERE u.ativo = 1 AND NOT EXISTS (SELECT 1 FROM usuario_funcoes uf WHERE uf.usuario_id = u.id) ORDER BY u.nome');
        $total = (int) Banco::valor('SELECT COUNT(*) FROM usuarios WHERE ativo = 1');
        return ['areas' => array_filter($areas, fn($a) => $a['funcoes'] || $a['slug'] === 'diretoria'), 'sem_funcao' => $semFuncao, 'total' => $total];
    }
}
