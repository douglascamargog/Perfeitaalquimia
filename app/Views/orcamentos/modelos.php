<?php
/** Modelos de pedido. Variáveis: $modelos, $podeCampanha */
?>
<a class="voltar-link" href="<?= url('/orcamentos') ?>">← Orçamentos</a>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">COMERCIAL · MODELOS DE PEDIDO</div>
    <h1>Modelos de pedido</h1>
    <p>Listas prontas de produtos e quantidades. No <strong>Novo pedido</strong>, escolha "Usar um modelo" e depois ajuste.
      Para criar um: monte as quantidades no Novo pedido e use <strong>Salvar como modelo</strong>.
      <?= $podeCampanha ? 'Como gestão, você também cria <strong>campanhas</strong> para a equipe.' : 'As campanhas são criadas pela gestão.' ?></p>
  </div>
  <div class="acoes"><a class="botao" href="<?= url('/pedidos/novo') ?>">Novo pedido</a></div>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela orc-lista" data-modelos-lista>
      <thead><tr><th>Modelo</th><th>Tipo</th><th>Produtos</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($modelos as $m): $pode = $m['tipo'] === 'pessoal' || $podeCampanha; ?>
        <tr data-modelo="<?= (int) $m['id'] ?>">
          <td><strong><?= e($m['nome']) ?></strong><?= $m['descricao'] ? '<small>' . e($m['descricao']) . '</small>' : '' ?></td>
          <td><?php if ($m['tipo'] === 'campanha'): ?><span class="selo lima">Campanha</span><small><?= e($m['setor'] ?: 'todos os setores') ?><?= $m['validade_ate'] ? ' · até ' . data_br($m['validade_ate']) : '' ?> · por <?= pessoa($m['autor'] ?? null, null, false, 18, '—') ?></small>
            <?php else: ?><span class="selo cinza">Meu</span><?php endif; ?></td>
          <td class="pequeno"><?= e(implode(' · ', $m['nomes'] ?? [])) ?: '—' ?></td>
          <td><?php if ($pode): ?><form method="post" action="<?= url('/pedidos/modelos/' . (int) $m['id'] . '/remover') ?>" data-confirmar="Tirar o modelo &quot;<?= e($m['nome']) ?>&quot;?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Tirar</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$modelos): ?><tr><td colspan="4" class="suave">Nenhum modelo ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
