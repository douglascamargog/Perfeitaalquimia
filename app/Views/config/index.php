<?php /** Configurações gerais. Variáveis: $grupos ['Grupo' => [itens]] */ ?>
<div class="cabecalho-pagina">
  <p class="suave">Regras comerciais do sistema. Toda alteração fica registrada na Auditoria.</p>
  <a class="botao contorno" href="<?= url('/configuracoes/listas') ?>">Etapas, segmentos e origens</a>
</div>

<form method="post" action="<?= url('/configuracoes') ?>">
  <?= csrf_campo() ?>
  <?php foreach ($grupos as $grupo => $itens): ?>
    <section class="card">
      <h2><?= e($grupo) ?></h2>
      <?php foreach ($itens as $item): $id = 'cfg-' . (int) $item['id']; $nome = 'config[' . e($item['chave']) . ']'; ?>
        <div class="config-item">
          <div>
            <label for="<?= $id ?>"><?= e($item['rotulo']) ?></label>
            <?php if ($item['descricao']): ?><div class="ajuda"><?= e($item['descricao']) ?></div><?php endif; ?>
          </div>
          <div>
            <?php if ($item['tipo'] === 'booleano'): ?>
              <label class="opcao"><input type="checkbox" id="<?= $id ?>" name="<?= $nome ?>" value="1" <?= $item['valor'] === '1' ? 'checked' : '' ?>> Sim</label>
            <?php else: ?>
              <?php if ($item['tipo'] === 'texto' && mb_strlen((string) $item['valor']) > 80): ?>
                <textarea id="<?= $id ?>" name="<?= $nome ?>" rows="3"><?= e($item['valor']) ?></textarea>
              <?php else: ?>
                <input type="<?= $item['tipo'] === 'numero' ? 'number' : 'text' ?>" id="<?= $id ?>" name="<?= $nome ?>" value="<?= e($item['valor']) ?>" step="any">
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
  <button class="botao" type="submit">Salvar configurações</button>
</form>
