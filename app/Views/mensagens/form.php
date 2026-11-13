<?php
/** Cadastro de mensagem pronta com prévia ao vivo. Variáveis: $m (null = nova), $categorias, $variaveis, $valores (exemplo) */
$v = fn(string $campo, $padrao = '') => $m === null ? antigo($campo, $padrao) : e((string) ($m[$campo] ?? $padrao));
?>
<div class="cabecalho-secao"><div><div class="sobretitulo"><a href="<?= url('/mensagens') ?>">MENSAGENS PRONTAS</a></div><h1><?= $m ? 'Editar mensagem' : 'Nova mensagem' ?></h1></div></div>
<div class="pr-duas msg-editor">
  <form class="card" method="post" action="<?= url($m ? '/mensagens/' . (int) $m['id'] : '/mensagens') ?>" data-form-mensagem>
    <?= csrf_campo() ?>
    <div class="grade-campos">
      <div class="campo"><label for="titulo">Título</label><input type="text" id="titulo" name="titulo" maxlength="120" required value="<?= $v('titulo') ?>"></div>
      <div class="campo"><label for="categoria">Categoria</label><select id="categoria" name="categoria">
        <?php $atual = $m['categoria'] ?? (string) \App\Core\Sessao::antigo('categoria', ''); foreach ($categorias as $c): ?><option <?= $atual === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="campo"><label for="texto">Texto</label>
      <textarea id="texto" name="texto" rows="8" maxlength="2000" required data-texto><?= $v('texto') ?></textarea>
      <div class="ajuda">Clique numa variável para colocar no texto (onde está o cursor):</div>
      <div class="msg-variaveis">
        <?php foreach ($variaveis as $var => $desc): ?><button type="button" class="b-chip" data-variavel="<?= e($var) ?>" title="<?= e($desc) ?>"><?= e($var) ?></button><?php endforeach; ?>
      </div>
    </div>
    <div class="grade-campos">
      <div class="campo"><label for="ordem">Ordem na lista</label><input type="number" id="ordem" name="ordem" value="<?= $v('ordem', '0') ?>"></div>
      <div class="campo"><label class="opcao" style="margin-top:28px"><input type="checkbox" name="ativo" value="1" <?= ($m === null || $m['ativo']) ? 'checked' : '' ?>> Ativa (aparece na ficha)</label></div>
    </div>
    <div class="acoes-form"><button class="botao" type="submit">Salvar</button><a class="botao contorno" href="<?= url('/mensagens') ?>">Voltar</a></div>
  </form>
  <section class="card msg-previa">
    <h2>Prévia</h2>
    <p class="suave pequeno">Com um cliente de exemplo da sua carteira e o seu link de vendedor.</p>
    <div class="msg-balao" data-previa aria-live="polite"></div>
    <script type="application/json" id="valores-variaveis"><?= json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  </section>
</div>
