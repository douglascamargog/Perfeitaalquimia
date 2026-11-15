<?php
/** Abas do Treinamento. Variáveis: $aba */
use App\Core\Auth;

$abas = array_filter([
    'meu'     => ['Meu treinamento', '/treinamento'],
    'trilhas' => ['Todas as trilhas', '/treinamento/trilhas'],
    'produtos' => ['Biblioteca de produtos', '/academia/produtos'],
    'equipe'  => Auth::pode('treinamento.equipe') ? ['Equipe', '/treinamento/equipe'] : null,
    'editar'  => Auth::pode('academia.editar') ? ['Editar', '/treinamento/editar'] : null,
]);
?>
<nav class="abas" aria-label="Treinamento">
  <?php foreach ($abas as $chave => [$nome, $link]): ?>
    <a href="<?= url($link) ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>
