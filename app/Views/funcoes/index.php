<?php /** Funções comerciais. Variáveis: $funcoes */ ?>
<div class="cabecalho-pagina">
  <p class="suave">Regras, filas e metas são ligadas às funções. As pessoas apenas são vinculadas a elas (em Usuários).</p>
</div>

<div class="card">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Função</th><th>Descrição</th><th>Identificador</th><th>Pessoas</th><th>Ativa</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($funcoes as $f): $form = 'funcao-' . (int) $f['id']; ?>
        <tr class="<?= $f['ativa'] ? '' : 'inativo' ?>">
          <td><input type="text" name="nome" form="<?= $form ?>" value="<?= e($f['nome']) ?>" aria-label="Nome da função" required></td>
          <td><input type="text" name="descricao" form="<?= $form ?>" value="<?= e($f['descricao']) ?>" aria-label="Descrição"></td>
          <td><code class="pequeno"><?= e($f['slug']) ?></code></td>
          <td><?= (int) $f['pessoas'] ?></td>
          <td><input type="checkbox" name="ativa" value="1" form="<?= $form ?>" <?= $f['ativa'] ? 'checked' : '' ?> aria-label="Ativa"></td>
          <td>
            <form id="<?= $form ?>" method="post" action="<?= url('/funcoes/' . (int) $f['id']) ?>"><?= csrf_campo() ?>
              <button class="botao contorno pequeno" type="submit">Salvar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="suave pequeno" style="margin:12px 0 0">O identificador é usado pelas regras do sistema e não muda quando você renomeia a função.</p>
</div>

<form method="post" action="<?= url('/funcoes') ?>" class="card" style="max-width:820px">
  <?= csrf_campo() ?>
  <h2>Nova função</h2>
  <div class="grade-campos">
    <div class="campo"><label for="nova-nome">Nome</label><input type="text" id="nova-nome" name="nome" required></div>
    <div class="campo"><label for="nova-descricao">Descrição</label><input type="text" id="nova-descricao" name="descricao"></div>
  </div>
  <button class="botao" type="submit">Criar função</button>
</form>
