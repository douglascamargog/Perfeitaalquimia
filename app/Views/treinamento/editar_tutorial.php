<?php
/** Editar o passo a passo de uma tela. Variáveis: $t (com 'passos') */
use App\Core\Visao;

$texto = implode("\n", array_map(fn($p) => (($p['alvo'] ?? '') !== '' ? $p['alvo'] . ' | ' : '') . ($p['texto'] ?? ''), $t['passos']));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">TUTORIAL DA TELA</div><h1><?= e($t['titulo']) ?></h1><p>Endereço: <code><?= e($t['rota']) ?></code></p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url($t['rota']) ?>" target="_blank" rel="noopener">Abrir a tela ↗</a></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'editar']) ?>
<form method="post" class="card tr-editor"><?= csrf_campo() ?>
  <label>Nome da tela<input type="text" name="titulo" value="<?= e($t['titulo']) ?>" maxlength="120" required></label>
  <label>Passos (um por linha)<textarea name="passos" rows="12"><?= e($texto) ?></textarea></label>
  <p class="pequeno suave">Cada linha é um passo. Para o One apontar uma parte da tela, escreva o "endereço" dela antes de uma barra: <code>[data-tutorial="filtros"] | Use os filtros para...</code>. Sem a barra, o balão aparece no meio da tela. Se a parte não estiver na tela (lista vazia, por exemplo), o balão também aparece no meio.</p>
  <label class="check"><input type="checkbox" name="ativo" value="1" <?= $t['ativo'] ? 'checked' : '' ?>> Tutorial ligado</label>
  <div class="acoes"><a class="botao contorno" href="<?= url('/treinamento/editar') ?>#tutoriais">Voltar</a><button class="botao" type="submit">Salvar</button></div>
</form>
