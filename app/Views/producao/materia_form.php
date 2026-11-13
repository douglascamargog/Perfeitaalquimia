<?php
/** Cadastro de matéria-prima (nova). Variáveis: $unidades */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">PRODUÇÃO · MATÉRIAS-PRIMAS</div><h1>Nova matéria-prima</h1></div></div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/materias']) ?>
<form class="card pr-form" method="post" action="<?= url('/producao/materias') ?>">
  <?= csrf_campo() ?>
  <?= Visao::renderizar('producao/_materia_campos', ['m' => null, 'unidades' => $unidades]) ?>
  <div class="campo"><label for="estoque_inicial">Estoque atual (opcional)</label>
    <input type="text" id="estoque_inicial" name="estoque_inicial" inputmode="decimal" value="<?= antigo('estoque_inicial') ?>">
    <div class="ajuda">Entra no histórico como ajuste de inventário "Estoque inicial do cadastro".</div></div>
  <div class="acoes-form"><button class="botao" type="submit">Cadastrar</button><a class="botao contorno" href="<?= url('/producao/materias') ?>">Voltar</a></div>
</form>
