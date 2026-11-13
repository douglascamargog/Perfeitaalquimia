<?php
/** Meu mês › Meus clientes (só a carteira da pessoa). Variáveis: $clientes (melhores, faixas, parados) */
$wa = fn(?string $n) => $n ? 'https://wa.me/55' . preg_replace('/\D/', '', $n) : null;
?>
<div class="mm-clientes">
  <section class="card">
    <h2>Seus melhores clientes</h2>
    <p class="suave pequeno">Os que mais compraram de você nos últimos 12 meses (pedidos faturados).</p>
    <div class="tabela-rolagem">
      <table class="tabela" data-melhores>
        <thead><tr><th>#</th><th>Cliente</th><th class="direita">Comprou</th><th class="direita">Pedidos</th><th>Última compra</th></tr></thead>
        <tbody>
        <?php foreach ($clientes['melhores'] as $i => $c): ?>
          <tr><td><b class="eq-posicao <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></b></td>
            <td><button type="button" class="link-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><strong><?= e($c['nome']) ?></strong></button><small class="suave"><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></small></td>
            <td class="direita"><strong><?= dinheiro($c['valor']) ?></strong></td><td class="direita"><?= (int) $c['pedidos'] ?></td>
            <td class="pequeno"><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$clientes['melhores']): ?><tr><td colspan="5" class="suave">Ainda sem pedidos faturados nos últimos 12 meses.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <h2>Sem comprar faz tempo</h2>
    <div class="mm-faixas">
      <?php foreach ($clientes['faixas'] as $dias => $qtd): ?><div><strong><?= (int) $qtd ?></strong><span><?= (int) $dias ?>+ dias</span></div><?php endforeach; ?>
    </div>
    <div class="tabela-rolagem">
      <table class="tabela" data-parados>
        <thead><tr><th>Cliente</th><th class="direita">Dias sem compra</th><th>Última compra</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clientes['parados'] as $c): ?>
          <tr><td><button type="button" class="link-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><strong><?= e($c['nome']) ?></strong></button><small class="suave"><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></small></td>
            <td class="direita"><strong class="<?= (int) $c['dias'] >= 90 ? 'pn-vermelho' : '' ?>"><?= (int) $c['dias'] ?></strong></td>
            <td class="pequeno"><?= data_br($c['ultima_compra_em']) ?></td>
            <td><?php if ($link = $wa($c['whatsapp'] ?: $c['telefone'])): ?><a class="botao contorno pequeno" href="<?= e($link) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$clientes['parados']): ?><tr><td colspan="4" class="suave">Nenhum cliente seu parado. 👏</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?= \App\Core\Visao::renderizar('carteira/_drawer') ?>
