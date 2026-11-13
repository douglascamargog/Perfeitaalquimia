<?php
$podeEditar = \App\Core\Auth::pode('catalogo.editar'); // só "Ver" (Acessos): tabela sem campos nem botões
/** Produtos (catálogo). Variáveis: $busca, $tabelas, $produtos, $semPreco, $semCodigo, $previa (importação de códigos) */
$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CONTEÚDO · PRODUTOS</div><h1>Produtos</h1>
    <p>Mesmo cadastro da Academia e do catálogo. Preços e estoque vêm do ERP (somente leitura). Aqui você escolhe o que aparece no catálogo.</p></div>
  <div class="acoes">
    <?php if ($podeEditar): ?><form method="post" action="<?= url('/produtos/sincronizar') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit"><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg>Atualizar preços do ERP</button></form><?php endif; ?>
    <a class="botao contorno" href="<?= url('/catalogo') ?>" target="_blank" rel="noopener">Ver catálogo</a>
  </div>
</div>
<?php if ($semPreco): ?>
  <div class="aviso aviso" role="status" data-aviso-sem-preco><strong><?= (int) $semPreco ?> produto(s) ativo(s) sem nenhum preço.</strong>
    Eles não aparecem para o cliente no catálogo. Clique em "Atualizar preços do ERP".</div>
<?php endif; ?>

<?php if ($previa): ?>
  <section class="card" id="importar" data-previa-codigos>
    <h2>Conferir a importação de códigos</h2>
    <p><strong><?= count($previa['ok']) ?></strong> produto(s) vão receber código<?= $previa['sem_produto'] ? ' · <strong>' . count($previa['sem_produto']) . '</strong> linha(s) sem produto com esse nome' : '' ?><?= $previa['invalidos'] ? ' · <strong>' . count($previa['invalidos']) . '</strong> linha(s) com código inválido ou repetido' : '' ?>.</p>
    <div class="tabela-rolagem"><table class="tabela">
      <thead><tr><th>Código</th><th>Produto</th><th>Código atual</th><th>Código novo</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($previa['ok'], 0, 300) as $l): ?><tr><td><?= e($l['nome']) ?></td><td><?= e($l['antes'] ?? '—') ?></td><td><span class="cod-produto"><?= e($l['codigo']) ?></span></td></tr><?php endforeach; ?>
        <?php foreach ($previa['sem_produto'] as $l): ?><tr class="inativo"><td><?= e($l['nome']) ?></td><td colspan="2">não encontrei um produto com esse nome (código <?= e($l['codigo']) ?>)</td></tr><?php endforeach; ?>
        <?php foreach ($previa['invalidos'] as $l): ?><tr class="inativo"><td><?= e($l['nome']) ?></td><td colspan="2"><?= e($l['motivo']) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
    <form method="post" action="<?= url('/produtos/codigos/confirmar') ?>" class="acoes-form"><?= csrf_campo() ?>
      <button class="botao" type="submit" name="acao" value="aplicar" <?= $previa['ok'] ? '' : 'disabled' ?>>Gravar <?= count($previa['ok']) ?> código(s)</button>
      <button class="botao contorno" type="submit" name="acao" value="cancelar">Cancelar</button>
    </form>
  </section>
<?php endif; ?>

<div class="card ped-busca prod-ferramentas">
  <form method="get" action="<?= url('/produtos') ?>" class="prod-busca">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar produto ou código…" aria-label="Buscar produto"><button class="botao" type="submit">Buscar</button>
  </form>
  <?php if ($podeEditar): ?><form method="post" action="<?= url('/produtos/codigos') ?>" enctype="multipart/form-data" class="prod-importar">
    <?= csrf_campo() ?>
    <label for="planilha-codigos">Importar códigos <small class="suave">(planilha com as colunas Código e Produto<?= $semCodigo ? " · $semCodigo sem código" : '' ?>)</small></label>
    <input type="file" id="planilha-codigos" name="planilha" accept=".csv,.xlsx" required>
    <button class="botao contorno" type="submit">Conferir planilha</button>
  </form><?php endif; ?>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem tabela-tela">
    <table class="tabela prod-tabela">
      <thead><tr><th>Código</th><th>Produto</th><?php foreach ($tabelas as $t): ?><th class="direita"><?= e($t['nome']) ?></th><?php endforeach; ?><th class="direita">Estoque</th><th>Catálogo</th><th>Chamada e ordem</th></tr></thead>
      <tbody>
      <?php foreach ($produtos as $p): $form = 'prod-' . (int) $p['id']; ?>
        <tr id="produto-<?= (int) $p['id'] ?>" class="<?= $p['visivel_catalogo'] ? '' : 'inativo' ?>">
          <td><?php if ($podeEditar): ?><input type="text" name="codigo" value="<?= e($p['codigo'] ?? '') ?>" maxlength="40" form="<?= $form ?>" class="prod-codigo" placeholder="código" aria-label="Código de <?= e($p['nome']) ?>"><?php else: ?><?= $p['codigo'] ? '<span class="cod-produto">' . e($p['codigo']) . '</span>' : '—' ?><?php endif; ?></td>
          <td><div class="ped-produto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?><span><strong><?= e($p['nome']) ?></strong><small><?= e($p['categoria']) ?> · <?= e($p['embalagem']) ?></small></span></div></td>
          <?php foreach ($tabelas as $t): ?><td class="direita"><?= isset($p['precos'][$t['id']]) ? dinheiro($p['precos'][$t['id']]) : '<span class="selo laranja">sem preço</span>' ?></td><?php endforeach; ?>
          <td class="direita"><?= $p['estoque'] !== null ? (int) $p['estoque'] : '—' ?></td>
          <?php if (!$podeEditar): ?>
          <td><?= $p['visivel_catalogo'] ? 'Visível' : '<span class="suave">Oculto</span>' ?></td><td class="pequeno"><?= e($p['chamada'] ?? '') ?></td>
          <?php else: ?>
          <td><label class="opcao"><input type="checkbox" name="visivel_catalogo" value="1" form="<?= $form ?>" <?= $p['visivel_catalogo'] ? 'checked' : '' ?>> Visível</label></td>
          <td>
            <form id="<?= $form ?>" method="post" action="<?= url('/produtos/' . (int) $p['id']) ?>" class="ped-produto-form">
              <?= csrf_campo() ?><input type="hidden" name="q" value="<?= e($busca) ?>">
              <input type="text" name="chamada" value="<?= e($p['chamada']) ?>" maxlength="255" aria-label="Chamada de <?= e($p['nome']) ?>">
              <input type="number" name="ordem" value="<?= (int) $p['ordem'] ?>" aria-label="Ordem" style="width:70px">
              <button class="botao contorno pequeno" type="submit">Salvar</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
