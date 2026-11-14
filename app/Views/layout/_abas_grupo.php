<?php
/**
 * Abas que juntam telas parecidas numa só entrada do menu (só aparecem as que a pessoa pode abrir).
 * Variáveis: $grupo (resultados | pessoas_acessos | configuracoes), $atual (url da aba atual)
 */
use App\Core\Auth;

$grupos = [
    'resultados' => [
        '/painel'         => ['Painel de vendas', ['painel.ver']],
        '/equipe'         => ['Equipe de vendas', ['equipe.ver']],
        '/acompanhamento' => ['Acompanhamento', ['acompanhamento.ver', 'setor.gestao', 'cobranca.gerenciar']],
    ],
    'pessoas_acessos' => [
        '/usuarios' => ['Usuários', ['usuarios.gerenciar']],
        '/funcoes'  => ['Funções', ['usuarios.gerenciar']],
        '/acessos'  => ['Acessos', ['usuarios.gerenciar']],
    ],
    'configuracoes' => [
        '/configuracoes'        => ['Regras e configurações', ['config.gerenciar']],
        '/configuracoes/listas' => ['Listas (funil, segmentos, origens)', ['config.gerenciar']],
    ],
];
$abas = array_filter($grupos[$grupo] ?? [], fn($a) => (bool) array_filter($a[1], fn($p) => Auth::pode($p)));
if (count($abas) < 2) {
    return;
}
?>
<nav class="pr-abas nao-imprimir abas-grupo" aria-label="<?= e(['resultados' => 'Resultados', 'pessoas_acessos' => 'Pessoas e acessos', 'configuracoes' => 'Configurações'][$grupo] ?? '') ?>" data-abas-grupo="<?= e($grupo) ?>">
  <?php foreach ($abas as $url => [$nome]): ?><a href="<?= url($url) ?>" class="<?= $atual === $url ? 'ativo' : '' ?>"><?= e($nome) ?></a><?php endforeach; ?>
</nav>
