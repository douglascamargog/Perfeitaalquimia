<?php
/**
 * Mural de parabéns de um aniversariante (hoje). Variáveis: $a (aniversariante: id, nome, foto), $mensagens, $voltar ('inicio' | 'perfil')
 * O próprio aniversariante vê as mensagens; os colegas veem e escrevem. Quem escreveu pode apagar a sua.
 */
use App\Core\Auth;
use App\Services\EquipeService;

$euSou = (int) $a['id'] === (int) Auth::id();
?>
<div class="mural" data-mural="<?= (int) $a['id'] ?>">
  <?php if (!$euSou): ?>
    <form method="post" action="<?= url('/aniversario/' . (int) $a['id'] . '/mensagens') ?>" class="mural-form">
      <?= csrf_campo() ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
      <textarea name="texto" rows="2" maxlength="500" required placeholder="Deixe seus parabéns para <?= e(explode(' ', $a['nome'])[0]) ?>…" aria-label="Mensagem de parabéns"></textarea>
      <button class="botao pequeno" type="submit">Enviar parabéns 🎉</button>
    </form>
  <?php endif; ?>
  <?php if (!$mensagens): ?><p class="suave pequeno"><?= $euSou ? 'As mensagens dos colegas vão aparecer aqui.' : 'Seja o primeiro a dar os parabéns!' ?></p><?php endif; ?>
  <ul class="mural-lista">
    <?php foreach ($mensagens as $m): ?>
      <li data-mensagem="<?= (int) $m['id'] ?>">
        <a href="<?= url('/pessoas/' . (int) $m['autor_id']) ?>"><?= EquipeService::avatar(['nome' => $m['autor'], 'foto' => $m['autor_foto']], 32) ?></a>
        <div><strong><?= e($m['autor']) ?></strong><p><?= nl2br(e($m['texto'])) ?></p><small><?= substr($m['criado_em'], 11, 5) ?></small></div>
        <?php if ((int) $m['autor_id'] === (int) Auth::id()): ?>
          <form method="post" action="<?= url('/aniversario/mensagens/' . (int) $m['id'] . '/apagar') ?>"><?= csrf_campo() ?>
            <input type="hidden" name="voltar" value="<?= $voltar === 'perfil' ? '/pessoas/' . (int) $a['id'] : '' ?>">
            <button class="botao-icone pequeno" type="submit" title="Apagar minha mensagem" aria-label="Apagar minha mensagem">✕</button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
