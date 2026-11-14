<?php
/**
 * Link do orçamento (cliente, sem senha): conferir, mudar quantidades, acrescentar produtos, aprovar ou recusar.
 * Variáveis: $o, $token, $quantidades, $oferecidos, $outros, $semPrecoAgora, $minimo, $formas
 * Usa o pedido.js (soma, mínimo e condição de pagamento) — o servidor confere tudo de novo ao aprovar.
 */
$base = url('/o/' . $token);
$linha = function (array $p) use ($quantidades): string {
    $q = (int) ($quantidades[$p['id']] ?? 0);
    ob_start(); ?>
    <div class="orc-item <?= $q ? 'escolhido' : '' ?>" data-linha data-nome="<?= e(mb_strtolower(($p['codigo'] ?? '') . ' ' . $p['nome'] . ' ' . $p['categoria'])) ?>" data-preco="<?= e((string) $p['preco']) ?>">
      <div class="orc-foto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" width="64" height="64" loading="lazy"><?php endif; ?></div>
      <div class="orc-info">
        <strong><?= e($p['nome']) ?></strong>
        <small><?= !empty($p['codigo']) ? e($p['codigo']) . ' · ' : '' ?><?= e((string) $p['embalagem']) ?><?= !empty($p['terceirizado']) ? ' · com a sua marca' : '' ?></small>
        <span class="orc-preco"><?= dinheiro((float) $p['preco']) ?> <small>/un.</small></span>
      </div>
      <div class="orc-qtd">
        <div class="orc-passo">
          <button type="button" data-orc-menos aria-label="Menos <?= e($p['nome']) ?>">−</button>
          <input class="ped-qtd" type="number" inputmode="numeric" name="qtd[<?= e((string) $p['id']) ?>]" min="0" max="<?= !empty($p['terceirizado']) ? 99999 : 9999 ?>" value="<?= $q ?: '' ?>" placeholder="0" aria-label="Quantidade de <?= e($p['nome']) ?>">
          <button type="button" data-orc-mais aria-label="Mais <?= e($p['nome']) ?>">+</button>
        </div>
        <b data-total-linha><?= $q ? dinheiro($q * (float) $p['preco']) : '' ?></b>
      </div>
    </div>
    <?php return ob_get_clean();
};
?>
<form class="orc-cliente" method="post" action="<?= $base ?>/aprovar" data-pedido data-minimo="<?= e((string) $minimo) ?>" data-orcamento-cliente>
  <?= csrf_campo() ?>
  <section class="orc-capa">
    <div>
      <span class="orc-selo">ORÇAMENTO #<?= (int) $o['id'] ?></span>
      <h1><?= e($o['cliente']) ?></h1>
      <p>Preparado por <strong><?= e($o['vendedor'] ?: 'nossa equipe') ?></strong> · tabela <?= e((string) $o['tabela']) ?> · válido até <strong><?= data_br($o['validade']) ?></strong></p>
    </div>
    <p class="orc-dica">Confira os produtos, mude as quantidades se quiser (ou acrescente outros) e toque em <strong>Aprovar orçamento</strong>.</p>
  </section>

  <?php if ($o['observacoes']): ?><p class="aviso">Recado de <?= e(explode(' ', trim((string) $o['vendedor']))[0] ?: 'nossa equipe') ?>: <?= nl2br(e($o['observacoes'])) ?></p><?php endif; ?>
  <?php if ($semPrecoAgora): ?><p class="aviso atencao">Não estão mais disponíveis: <?= e(implode(', ', array_column($semPrecoAgora, 'nome'))) ?>.</p><?php endif; ?>

  <div class="orc-corpo">
    <div class="orc-listas">
      <section class="card orc-lista" aria-label="Produtos do orçamento">
        <h2>Seu orçamento</h2>
        <?php foreach ($oferecidos as $p): ?><?= $linha($p) ?><?php endforeach; ?>
      </section>

      <?php if ($outros): ?>
      <details class="card orc-lista orc-mais" data-area-filtro>
        <summary><strong>+ Adicionar mais produtos</strong> <small><?= count($outros) ?> produto(s) com o seu preço</small></summary>
        <input type="search" class="orc-busca" placeholder="Buscar produto…" aria-label="Buscar produto" data-filtro-produtos>
        <?php foreach ($outros as $p): ?><?= $linha($p) ?><?php endforeach; ?>
      </details>
      <?php endif; ?>
    </div>

    <aside class="card ped-resumo orc-resumo">
      <h2>Resumo</h2>
      <div class="ped-total"><span><b data-itens>0</b> unidade(s)</span><strong data-subtotal>R$ 0,00</strong></div>
      <div class="cat-minimo falta" data-caixa-minimo><span data-texto-minimo></span><div class="cat-minimo-barra"><i data-barra-minimo style="width:0"></i></div></div>
      <h3>Pagamento</h3>
      <?php foreach ($formas as $f): ?>
        <fieldset class="cat-forma <?= $f['bloqueio'] ? 'bloqueada' : '' ?>">
          <legend><?= e($f['nome']) ?></legend>
          <?php if ($f['bloqueio']): ?><p class="pequeno"><?= e($f['bloqueio']) ?></p><?php else: foreach ($f['condicoes'] as $cd):
            $marcado = antigo('pagamento') !== '' ? antigo('pagamento') === $f['id'] . ':' . $cd['id'] : ((int) $o['forma_pagamento_id'] === (int) $f['id'] && (int) $o['condicao_id'] === (int) $cd['id']); ?>
            <label class="opcao" data-condicao-minimo="<?= e((string) $cd['valor_minimo']) ?>">
              <input type="radio" name="pagamento" value="<?= (int) $f['id'] ?>:<?= (int) $cd['id'] ?>" required <?= $marcado ? 'checked' : '' ?>>
              <?= e($cd['nome']) ?><?= (float) $cd['desconto_pct'] > 0 ? ' · ' . number_format((float) $cd['desconto_pct'], 1, ',', '.') . '% desc.' : '' ?>
              <?= (float) $cd['valor_minimo'] > 0 ? '<small>(a partir de ' . e(dinheiro((float) $cd['valor_minimo'])) . ')</small>' : '' ?>
            </label>
          <?php endforeach; endif; ?>
        </fieldset>
      <?php endforeach; ?>
      <div class="campo"><label for="msg">Mensagem para <?= e(explode(' ', trim((string) $o['vendedor']))[0] ?: 'o vendedor') ?> <small>(obrigatória se recusar)</small></label>
        <textarea id="msg" name="mensagem" rows="3" maxlength="1000" placeholder="Ex.: pode mandar junto um mostruário?"><?= antigo('mensagem') ?></textarea></div>
      <button class="botao largo" type="submit" data-enviar>Aprovar orçamento</button>
      <button class="botao contorno largo orc-recusar" type="submit" formaction="<?= $base ?>/recusar" formnovalidate data-recusar>Não quero agora (recusar)</button>
      <p class="pequeno suave">Ao aprovar, o seu pedido vai para a nossa equipe conferir e faturar. Sem cadastro e sem senha.</p>
    </aside>
  </div>
</form>
