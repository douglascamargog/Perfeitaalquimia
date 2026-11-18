<?php
/**
 * Página da tarefa do Marketing: dados, anexo, botões de situação, aprovação, encaminhar e comentários.
 * Variáveis: $t, $historico, $pode, $podeAprovar, $funcoes, $pessoas
 */
use App\Core\Auth;
use App\Services\MarketingTarefaService as T;

[$sn, $sc] = T::SITUACOES[$t['situacao']];
$acao = url('/marketing/tarefas/' . (int) $t['id']);
$botao = fn(string $a, string $rotulo, string $classe = 'contorno') => '<form method="post" action="' . $acao . '/mudar">' . csrf_campo()
    . '<input type="hidden" name="acao" value="' . $a . '"><button class="botao ' . $classe . '" type="submit" data-acao="' . $a . '">' . $rotulo . '</button></form>';
$dono = (int) $t['solicitante_id'] === Auth::id();
$imagem = $t['anexo'] && str_starts_with((string) $t['anexo_mime'], 'image/');
?>
<?php $doMarketing = Auth::pode('marketing.editar') || Auth::pode('marketing.aprovar'); ?>
<a class="voltar-link" href="<?= url($doMarketing ? '/marketing/inicio' : '/marketing/pedir') ?>">← <?= $doMarketing ? 'Início do Marketing' : 'Meus pedidos' ?></a>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">MARKETING · <?= e(mb_strtoupper((string) $t['tipo_nome'])) ?> · #<?= (int) $t['id'] ?></div>
    <h1><?= e($t['titulo']) ?></h1>
    <p><span class="selo <?= $sc ?>" data-situacao="<?= e($t['situacao']) ?>"><?= e($sn) ?></span>
      <?= $t['urgente'] ? '<span class="selo vermelho">urgente</span>' : '' ?>
      <?= e((string) $t['funcao']) ?><?= $t['responsavel'] ? ' · com ' . pessoa($t['responsavel'] ?? null, null, false, 18, '—') : '' ?>
      <?= $t['prazo'] ? ' · prazo <strong>' . data_br($t['prazo']) . '</strong>' : '' ?>
      · <?= $t['setor'] ? 'pedido de ' . pessoa($t['solicitante'] ?? null, null, false, 18, '—') . ' (' . e($t['setor']) . ')' : 'criada por ' . pessoa($t['solicitante'] ?? null, null, false, 18, '—') ?> em <?= data_br($t['criado_em']) ?></p>
  </div>
  <div class="acoes mt-botoes">
    <?php if ($pode && $t['situacao'] === 'a_fazer'): ?><?= $botao('comecar', 'Começar') ?><?php endif; ?>
    <?php if ($pode && in_array($t['situacao'], ['a_fazer', 'fazendo'], true)): ?><?= $botao('aprovacao', 'Enviar para aprovação') ?><?= $botao('concluir', 'Concluir', 'lima') ?><?php endif; ?>
    <?php if ($pode && in_array($t['situacao'], ['feito', 'cancelado'], true)): ?><?= $botao('reabrir', 'Reabrir') ?><?php endif; ?>
    <?php if (($pode || ($dono && $t['situacao'] === 'a_fazer')) && !in_array($t['situacao'], ['feito', 'cancelado'], true)): ?>
      <form method="post" action="<?= $acao ?>/mudar" data-confirmar="Cancelar esta tarefa?"><?= csrf_campo() ?><input type="hidden" name="acao" value="cancelar"><button class="botao contorno perigo" type="submit">Cancelar</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if (($pode || $dono) && in_array($t['situacao'], $pode ? ['a_fazer', 'fazendo'] : ['a_fazer'], true)): ?>
  <details class="card" data-editar-tarefa-mkt style="margin-bottom:14px">
    <summary><strong>✏️ Editar <?= $dono && !$pode ? 'o meu pedido' : 'a tarefa' ?></strong> <small class="suave">título, descrição, prazo e urgência</small></summary>
    <form method="post" action="<?= $acao ?>/editar" style="margin-top:10px"><?= csrf_campo() ?>
      <div class="grade-campos">
        <div class="campo"><label>Título<input type="text" name="titulo" value="<?= e($t['titulo']) ?>" maxlength="160" required></label></div>
        <div class="campo"><label>Prazo<input type="date" name="prazo" value="<?= e((string) $t['prazo']) ?>"></label></div>
      </div>
      <div class="campo"><label>Descrição<textarea name="descricao" rows="4" maxlength="5000"><?= e((string) $t['descricao']) ?></textarea></label></div>
      <label class="pequeno"><input type="checkbox" name="urgente" value="1" <?= $t['urgente'] ? 'checked' : '' ?>> Urgente</label>
      <div class="acoes-form"><button class="botao pequeno" type="submit">Salvar</button></div>
    </form>
  </details>
<?php endif; ?>

<?php if ($podeAprovar && $t['situacao'] === 'aprovacao'): ?>
<section class="card mt-decidir" id="decidir" data-decidir>
  <h2>Sua aprovação</h2>
  <form method="post" action="<?= $acao ?>/mudar" class="pd-form"><?= csrf_campo() ?>
    <label>Comentário <small>obrigatório para pedir ajuste</small><textarea name="comentario" rows="2"></textarea></label>
    <div class="acoes-form"><button class="botao lima" type="submit" name="acao" value="aprovar">Aprovar</button><button class="botao contorno" type="submit" name="acao" value="devolver">Pedir ajuste</button></div>
  </form>
</section>
<?php endif; ?>

<div class="mt-pagina">
  <section class="card">
    <h2>Detalhes</h2>
    <div class="mt-descricao"><?= $t['descricao'] ? nl2br(e($t['descricao'])) : '<span class="suave">Sem detalhes.</span>' ?></div>
    <?php if ($t['anexo']): ?>
      <h3>Anexo</h3>
      <?php if ($imagem): ?><a href="<?= $acao ?>/anexo" target="_blank" rel="noopener"><img class="mt-anexo-img" src="<?= $acao ?>/anexo" alt="Anexo: <?= e((string) $t['anexo_nome']) ?>"></a><?php endif; ?>
      <p><a href="<?= $acao ?>/anexo" target="_blank" rel="noopener" data-anexo><?= e((string) $t['anexo_nome']) ?></a></p>
    <?php endif; ?>

    <h3>Comentários e histórico</h3>
    <form method="post" action="<?= $acao ?>/comentar" class="pd-form mt-comentar"><?= csrf_campo() ?>
      <textarea name="texto" rows="2" maxlength="1000" placeholder="<?= $dono && !$pode ? 'Mande um recado para o Marketing…' : 'Escreva um comentário (quem pediu é avisado)…' ?>" required></textarea>
      <button class="botao contorno" type="submit">Comentar</button>
    </form>
    <ul class="ac-lista" data-historico><?php foreach ($historico as $h): ?><li><time><?= data_br($h['criado_em'], true) ?></time> <strong><?= pessoa($h['usuario'] ?? null, null, false, 18, 'Sistema') ?>:</strong> <?= e($h['texto']) ?></li><?php endforeach; ?></ul>
  </section>

  <?php if ($pode): ?>
  <aside class="card">
    <h2>Encaminhar</h2>
    <form method="post" action="<?= $acao ?>/encaminhar" class="pd-form" data-encaminhar><?= csrf_campo() ?>
      <label>Função<select name="funcao_slug"><?php foreach ($funcoes as $f): ?><option value="<?= e($f['slug']) ?>" <?= $f['slug'] === $t['funcao_slug'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Quem faz<select name="responsavel_id"><option value="">Quem pegar primeiro</option><?php foreach ($pessoas as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $t['responsavel_id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Prazo<input type="date" name="prazo" value="<?= e((string) $t['prazo']) ?>"></label>
      <button class="botao contorno" type="submit">Salvar</button>
    </form>
  </aside>
  <?php endif; ?>
</div>
