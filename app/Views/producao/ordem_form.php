<?php
/** Nova ordem de produção com prévia do consumo. Variáveis: $produtos, $produtoId */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo"><a href="<?= url('/producao/ordens') ?>">ORDENS DE PRODUÇÃO</a></div><h1>Nova ordem de produção</h1>
  <p>Só aparecem os produtos que já têm ficha técnica. A ordem fica <strong>planejada</strong>; o estoque muda ao concluir.</p></div></div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/ordens']) ?>
<div class="pr-duas">
  <form class="card pr-form" method="post" action="<?= url('/producao/ordens') ?>" data-form-ordem data-previa-url="<?= url('/producao/ordens/previa') ?>">
    <?= csrf_campo() ?>
    <div class="campo"><label for="produto_id">Produto</label>
      <select id="produto_id" name="produto_id" required data-produto><option value="">Escolha…</option>
        <?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $produtoId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select>
      <div class="ajuda">Produto sem ficha? <a href="<?= url('/producao/fichas?ficha=sem') ?>">Cadastre a ficha técnica</a>.</div></div>
    <div class="grade-campos">
      <div class="campo"><label for="quantidade">Quantidade (unidades)</label><input id="quantidade" name="quantidade" type="number" min="1" max="1000000" required value="<?= antigo('quantidade') ?>" data-quantidade></div>
      <div class="campo"><label for="previsto_para">Previsto para</label><input id="previsto_para" name="previsto_para" type="date" value="<?= antigo('previsto_para') ?>"></div>
      <div class="campo"><label for="lote">Lote</label><input type="text" id="lote" name="lote" maxlength="40" value="<?= antigo('lote', 'L' . date('ymd')) ?>"></div>
      <div class="campo"><label for="validade">Validade</label><input id="validade" name="validade" type="date" value="<?= antigo('validade', date('Y-m-d', strtotime('+24 months'))) ?>"></div>
    </div>
    <div class="campo"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="2"><?= antigo('observacoes') ?></textarea></div>
    <div class="acoes-form"><button class="botao" type="submit">Planejar ordem</button><a class="botao contorno" href="<?= url('/producao/ordens') ?>">Voltar</a></div>
  </form>
  <section class="card" data-previa aria-live="polite">
    <h2>Prévia do consumo</h2>
    <p class="suave" data-previa-vazia>Escolha o produto e a quantidade para ver quanto de cada matéria-prima vai ser usado.</p>
    <div data-previa-conteudo hidden>
      <table class="tabela pr-tabela"><thead><tr><th>Matéria-prima</th><th class="direita">Vai usar</th><th class="direita">Em estoque</th><th class="direita">Falta</th></tr></thead><tbody data-previa-linhas></tbody></table>
      <p data-previa-resumo></p>
    </div>
  </section>
</div>
