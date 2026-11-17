<?php
/**
 * Administração › Especialistas do One: o jeito de trabalhar de cada assistente, quem abre direto nele e o uso do mês.
 * Variáveis: $lista, $funcoes, $uso, $comIa
 */
$nomesFuncao = array_column($funcoes, 'nome', 'slug');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO · ONE</div><h1>Especialistas do One</h1>
    <p>Cada setor tem um especialista no chat do One, com o seu jeito de trabalhar e só as consultas do setor (sempre com o acesso da própria pessoa).
      <?= $comIa ? '✨ A IA está ligada.' : 'A IA está desligada: os especialistas respondem pelas aulas do Treinamento. Ligue em Configurações › One (IA).' ?></p></div>
</div>
<?php foreach ($lista as $e): $u = $uso[$e['slug']] ?? null; $minhas = array_filter(explode(',', (string) $e['funcoes'])); ?>
  <section class="card" id="esp-<?= e($e['slug']) ?>" data-especialista="<?= e($e['slug']) ?>" style="margin-bottom:14px">
    <form method="post" action="<?= url('/administracao/especialistas/' . (int) $e['id']) ?>"><?= csrf_campo() ?>
      <header style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <h2 style="margin:0;font-size:1.05rem"><?= e($e['icone']) ?> <?= e($e['nome']) ?>
          <small class="suave"><?= $u ? (int) $u['perguntas'] . ' pergunta(s) de ' . (int) $u['pessoas'] . ' pessoa(s) este mês · US$ ' . number_format((float) $u['custo'], 2, ',', '.') : 'sem uso este mês' ?></small></h2>
        <?php if ($e['slug'] !== 'geral'): ?><label class="pequeno"><input type="checkbox" name="ativo" value="1" <?= $e['ativo'] ? 'checked' : '' ?>> ativo</label><?php endif; ?>
      </header>
      <div class="grade-2" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;margin-top:10px">
        <label>Nome<input type="text" name="nome" value="<?= e($e['nome']) ?>" maxlength="60"></label>
        <label>Para que serve (aparece ao passar o mouse)<input type="text" name="descricao" value="<?= e($e['descricao']) ?>" maxlength="200"></label>
      </div>
      <?php if ($e['slug'] !== 'geral'): ?>
        <fieldset style="margin-top:10px;border:0;padding:0"><legend class="pequeno"><strong>Abre direto para quem é das funções</strong> (admin e diretoria veem todos)</legend>
          <div style="display:flex;flex-wrap:wrap;gap:8px 14px">
            <?php foreach ($nomesFuncao as $slug => $nome): ?><label class="pequeno"><input type="checkbox" name="funcoes[]" value="<?= e($slug) ?>" <?= in_array($slug, $minhas, true) ? 'checked' : '' ?>> <?= e($nome) ?></label><?php endforeach; ?>
          </div>
        </fieldset>
      <?php endif; ?>
      <label style="margin-top:10px;display:block">Jeito de trabalhar (instruções para a IA)
        <textarea name="instrucoes" rows="9" data-instrucoes><?= e((string) $e['instrucoes']) ?></textarea></label>
      <label style="margin-top:10px;display:block">Exemplos de pergunta (um por linha)
        <textarea name="sugestoes" rows="3"><?= e(str_replace('|', "\n", (string) $e['sugestoes'])) ?></textarea></label>
      <p class="pequeno suave">Consultas que ele usa: <?= e(str_replace(',', ', ', (string) $e['consultas'])) ?><?= $e['editado_em'] ? ' · editado em ' . data_br($e['editado_em'], true) . ' (as atualizações do One não trocam o texto)' : '' ?></p>
      <button class="botao pequeno" type="submit">Salvar</button>
    </form>
  </section>
<?php endforeach; ?>
