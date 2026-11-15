<?php
/** Editar um módulo e a prova. Variáveis: $m, $perguntas (com correta e explicacao) */
use App\Core\Visao;
use App\Services\TreinamentoService as T;

$linhas = $perguntas;
$linhas[] = ['id' => 0, 'pergunta' => '', 'opcoes' => [], 'correta' => 0, 'explicacao' => '']; // uma em branco para acrescentar
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><?= e($m['trilha_titulo']) ?></div><h1>Editar módulo</h1></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/treinamento/modulo/' . (int) $m['id']) ?>" target="_blank" rel="noopener">Ver como fica ↗</a></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'editar']) ?>
<form method="post" class="tr-editor" data-tr-editor><?= csrf_campo() ?>
  <section class="card">
    <label>Título<input type="text" name="titulo" value="<?= e($m['titulo']) ?>" maxlength="160" required></label>
    <label>Resumo (aparece na lista)<input type="text" name="resumo" value="<?= e((string) $m['resumo']) ?>" maxlength="255"></label>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <label>Tempo de leitura (min)<input type="number" name="tempo_min" value="<?= (int) $m['tempo_min'] ?>" min="1" max="120"></label>
      <label class="check"><input type="checkbox" name="ativo" value="1" <?= $m['ativo'] ? 'checked' : '' ?>> Módulo ligado</label>
    </div>
    <label>Texto do módulo
      <textarea name="conteudo" rows="18" data-tr-texto><?= e($m['conteudo']) ?></textarea></label>
    <p class="pequeno suave">Como formatar: <code>## Título</code> · <code>- item de lista</code> · <code>1. passo numerado</code> · <code>&gt; destaque</code> · <code>! atenção</code> · <code>**negrito**</code> · <code>[nome da tela](/carteira)</code> · <code>![descrição](media/academia/arquivo.webp)</code></p>
    <details><summary>Prévia de como está salvo</summary><div class="tr-conteudo"><?= T::html($m['conteudo']) ?></div></details>
  </section>
  <section class="card">
    <h2>Prova</h2>
    <p class="pequeno suave">Uma opção por linha. "Certa" é o número da linha da opção correta (1, 2, 3...). Para tirar uma pergunta, apague o texto dela.</p>
    <?php foreach ($linhas as $i => $p): ?>
      <fieldset class="tr-pergunta-editar">
        <input type="hidden" name="perguntas[<?= $i ?>][id]" value="<?= (int) $p['id'] ?>">
        <label>Pergunta <?= $i + 1 ?><?= $p['id'] ? '' : ' (nova)' ?><input type="text" name="perguntas[<?= $i ?>][pergunta]" value="<?= e($p['pergunta']) ?>" maxlength="255"></label>
        <label>Opções<textarea name="perguntas[<?= $i ?>][opcoes]" rows="4"><?= e(implode("\n", $p['opcoes'])) ?></textarea></label>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <label>Certa<input type="number" name="perguntas[<?= $i ?>][correta]" value="<?= (int) $p['correta'] + 1 ?>" min="1" max="9" style="width:80px"></label>
          <label style="flex:1">Explicação (aparece depois de responder)<input type="text" name="perguntas[<?= $i ?>][explicacao]" value="<?= e((string) $p['explicacao']) ?>" maxlength="255"></label>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </section>
  <div class="acoes"><a class="botao contorno" href="<?= url('/treinamento/editar') ?>">Voltar</a><button class="botao" type="submit">Salvar módulo</button></div>
</form>
