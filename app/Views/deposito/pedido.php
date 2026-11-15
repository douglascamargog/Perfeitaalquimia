<?php
/**
 * Depósito › separar e despachar um pedido.
 * Variáveis: $p (pedido com itens, lotes e sugestões), $pode, $loteObrigatorio, $transportadoras
 */
use App\Core\Sessao;
use App\Core\Visao;
use App\Services\DepositoService as D;

[$er, $ec] = D::ETAPAS[$p['expedicao']] ?? [$p['expedicao'], 'cinza'];
$acao = fn(string $a) => url('/deposito/' . (int) $p['id'] . '/' . $a);
$editavel = $pode && in_array($p['expedicao'], ['aguardando', 'separando'], true);
$antigo = (array) Sessao::antigo('item', []);
$terceirizado = (bool) array_filter($p['itens'], fn($i) => !empty($i['terceirizacao_produto_id']));
?>
<a class="voltar-link" href="<?= url('/deposito') ?>">← Fila do depósito</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DEPÓSITO · PEDIDO #<?= (int) $p['id'] ?><?= $p['numero_nf'] ? ' · NF ' . e($p['numero_nf']) : '' ?></div>
    <?php $eco = $p['tipo_venda'] === 'ecommerce'; $prazo = $eco && $p['expedicao'] !== 'despachado' ? \App\Services\EcommercePedidoService::prazo($p['expedicao_limite']) : null; ?>
    <h1><?= $eco ? '🛒 ' . e(\App\Services\EcommercePedidoService::nomeCanal($p['canal'])) . ($p['marketplace_numero'] ? ' · ' . e($p['marketplace_numero']) : '') : e($p['cliente']) ?></h1>
    <p><span class="selo <?= $ec ?>" data-etapa><?= e($er) ?></span>
      <span class="dep-tipo-selo tipo-<?= $eco ? 'ecommerce' : ($terceirizado ? 'terceirizacao' : 'lojista') ?>"><?= $eco ? 'E-commerce' : ($terceirizado ? 'Terceirização' : 'Lojista') ?></span>
      <?= $p['expedicao_prioridade'] ? '<span class="selo vermelho">urgente</span>' : '' ?>
      <?= $p['expedicao_aguarda_produto'] ? '<span class="selo laranja">aguardando produto</span>' : '' ?>
      <?php if ($eco): ?>comprador <?= e((string) $p['comprador_nome']) ?> · <?= e(trim(($p['comprador_cidade'] ?? '') . '/' . ($p['comprador_uf'] ?? ''), '/')) ?><?= $p['separador'] ? ' · separando: ' . pessoa($p['separador'] ?? null, null, false, 18, '—') : '' ?>
      <?php else: ?><?= e($p['cidade']) ?>/<?= e($p['uf']) ?> · vendedor <?= pessoa($p['vendedor'] ?? null, $p['vendedor_foto'] ?? null, false, 18) ?><?= $p['separador'] ? ' · separando: ' . pessoa($p['separador'] ?? null, null, false, 18, '—') : '' ?><?php endif; ?></p>
    <?php if ($prazo): ?><p class="dep-prazo grande <?= $prazo[1] ?>" data-prazo>⏰ <?= e($prazo[0]) ?></p><?php endif; ?></div>
  <div class="acoes">
    <a class="botao contorno pequeno" href="<?= url('/pedidos/' . (int) $p['id']) ?>">Ver o pedido</a>
    <?php if ($pode && $p['expedicao'] !== 'despachado'): ?>
      <form method="post" action="<?= $acao('urgente') ?>"><?= csrf_campo() ?><input type="hidden" name="urgente" value="<?= $p['expedicao_prioridade'] ? '0' : '1' ?>">
        <button class="botao contorno pequeno" type="submit"><?= $p['expedicao_prioridade'] ? 'Tirar urgência' : 'Marcar urgente' ?></button></form>
    <?php endif; ?>
    <?php if ($pode && $p['expedicao'] === 'aguardando'): ?>
      <form method="post" action="<?= $acao('comecar') ?>"><?= csrf_campo() ?><button class="botao lima" type="submit">Começar a separar</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if ($terceirizado): ?><p class="aviso alerta">Pedido com produto de <strong>terceirização</strong>: vai com o rótulo do cliente.</p><?php endif; ?>
<?php if ($p['observacoes']): ?><p class="aviso">Observação do pedido: <?= e($p['observacoes']) ?></p><?php endif; ?>

<?php if ($editavel): ?>
<form method="post" action="<?= $acao('separar') ?>" class="card dep-separar" data-separar>
  <?= csrf_campo() ?>
  <div class="dep-leitor">
    <label for="ler-codigo">Ler código de barras <small>leitor USB/bluetooth ou digite o EAN e Enter</small></label>
    <input type="text" id="ler-codigo" inputmode="numeric" autocomplete="off" placeholder="Passe o leitor aqui…" data-leitor>
    <span class="dep-leitor-msg" data-leitor-msg role="status"></span>
  </div>
  <div class="tabela-rolagem">
    <table class="tabela dep-itens">
      <thead><tr><th>Produto</th><th class="direita">Pedido</th><th class="direita">Separado</th><th>Lote<?= $loteObrigatorio ? ' <small>(obrigatório · a validade vem do lote)</small>' : ' <small>(a validade vem do lote)</small>' ?></th></tr></thead>
      <tbody>
      <?php foreach ($p['itens'] as $i): $iid = (int) $i['id']; $old = $antigo[$iid] ?? null;
        $qtd = $old['qtd'] ?? ($i['qtd_separada'] ?? 0);
        $lotes = $old['lotes'] ?? ($i['lotes'] ? array_map(fn($l) => ['lote' => $l['lote'], 'validade' => $l['validade'], 'qtd' => $l['quantidade']], $i['lotes']) : [['lote' => '', 'validade' => '', 'qtd' => '']]); ?>
        <tr data-item="<?= $iid ?>" data-ean="<?= e((string) $i['ean']) ?>" data-pedido-qtd="<?= (int) $i['quantidade'] ?>">
          <td><?= !empty($i['codigo']) ? '<span class="cod-produto">' . e($i['codigo']) . '</span> ' : '' ?><strong><?= e($i['nome']) ?></strong>
            <small class="suave"><?= e((string) $i['embalagem']) ?><?= $i['ean'] ? ' · EAN ' . e($i['ean']) : '' ?></small>
            <?= $i['terceirizacao_produto_id'] ? '<span class="selo cinza">rótulo do cliente</span>' : '' ?></td>
          <td class="direita"><strong><?= (int) $i['quantidade'] ?></strong></td>
          <td class="direita"><input type="number" name="item[<?= $iid ?>][qtd]" min="0" max="<?= (int) $i['quantidade'] ?>" value="<?= (int) $qtd ?>" class="dep-qtd" data-qtd aria-label="Separado de <?= e($i['nome']) ?>">
            <button type="button" class="dep-tudo" data-tudo>tudo</button></td>
          <td>
            <datalist id="lotes-<?= $iid ?>"><?php foreach ($i['sugestoes'] as $s): ?><option value="<?= e($s['lote']) ?>" data-validade="<?= e((string) $s['validade']) ?>"><?= $s['validade'] ? 'vence ' . data_br($s['validade']) : '' ?></option><?php endforeach; ?></datalist>
            <div class="dep-lotes" data-lotes>
              <?php foreach (array_values($lotes) as $n => $l): ?>
                <div class="dep-lote" data-lote>
                  <input type="text" name="item[<?= $iid ?>][lotes][<?= $n ?>][lote]" value="<?= e((string) $l['lote']) ?>" list="lotes-<?= $iid ?>" maxlength="40" placeholder="Lote" aria-label="Lote" data-lote-nome>
                  <small class="dep-lote-validade" data-lote-validade><?= !empty($l['validade']) ? 'vence ' . data_br($l['validade']) : '' ?></small>
                  <input type="number" name="item[<?= $iid ?>][lotes][<?= $n ?>][qtd]" value="<?= e((string) $l['qtd']) ?>" min="0" placeholder="qtd" aria-label="Quantidade deste lote" data-lote-qtd>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($i['sugestoes']): ?><small class="suave">Sugerido: <?= e($i['sugestoes'][0]['lote']) ?><?= $i['sugestoes'][0]['validade'] ? ' (vence ' . data_br($i['sugestoes'][0]['validade']) . ')' : '' ?></small><?php endif; ?>
            <button type="button" class="dep-outro-lote" data-outro-lote>+ outro lote</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$embalagens): // nenhuma caixa cadastrada: o quadro aparece com a explicação e o atalho para cadastrar ?>
  <fieldset class="dep-embalagem" data-embalagem-vazia>
    <legend>📦 Caixas e embalagem usadas</legend>
    <p>Nenhuma caixa cadastrada ainda. Cadastre as caixas de envio em <strong>Compras › Compras da empresa › Itens comprados</strong> (tipo "Uso e consumo", categoria "Embalagem de envio"). Depois elas aparecem aqui para anotar quantas foram usadas, com baixa automática no estoque.</p>
    <?php if (\App\Core\Auth::pode('compras.itens')): ?><a class="botao contorno pequeno" href="<?= url('/financeiro/compras?aba=itens&novo=caixa') ?>">+ Cadastrar caixa</a>
    <?php else: ?><p class="suave pequeno">Peça ao Financeiro ou à Qualidade para cadastrar.</p><?php endif; ?>
  </fieldset>
  <?php else: // caixas e embalagens usadas: dão baixa no estoque (Compras da empresa › Itens)
    $antigoEmb = array_values(array_filter((array) Sessao::antigo('embalagem', []), 'is_array'));
    $linhasEmb = $antigoEmb ?: array_map(fn($u) => ['item' => $u['materia_prima_id'], 'qtd' => rtrim(rtrim(number_format((float) $u['quantidade'], 3, ',', ''), '0'), ',')], $usadas);
    if (!$linhasEmb) { $linhasEmb = [['item' => count($embalagens) === 1 ? $embalagens[0]['id'] : '', 'qtd' => '']]; } ?>
  <fieldset class="dep-embalagem" data-embalagem>
    <legend>📦 Caixas e embalagem usadas<?= $embalagemObrigatoria ? ' <small>(obrigatório para concluir)</small>' : '' ?></legend>
    <p class="ajuda">Qual caixa e quantas foram usadas para embalar este pedido. A baixa no estoque é automática.</p>
    <div class="dep-emb-linhas" data-emb-linhas>
      <?php foreach (array_values($linhasEmb) as $n => $l): ?>
        <div class="dep-emb-linha" data-emb-linha>
          <select name="embalagem[<?= $n ?>][item]" aria-label="Caixa ou embalagem">
            <option value="">Escolha a caixa…</option>
            <?php foreach ($embalagens as $em): ?><option value="<?= (int) $em['id'] ?>" <?= (string) $l['item'] === (string) $em['id'] ? 'selected' : '' ?>><?= e($em['nome']) ?> · <?= e(\App\Services\ProducaoService::qtd((float) $em['estoque'], $em['unidade'])) ?> em estoque</option><?php endforeach; ?>
          </select>
          <input type="text" inputmode="decimal" name="embalagem[<?= $n ?>][qtd]" value="<?= e((string) $l['qtd']) ?>" placeholder="qtd" aria-label="Quantidade usada" class="dep-emb-qtd">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="dep-outro-lote" data-outra-embalagem>+ outra caixa ou embalagem</button>
  </fieldset>
  <?php endif; ?>
  <fieldset class="dep-falta" data-falta-caixa>
    <legend>Se faltar produto</legend>
    <label class="caixa-marcar"><input type="radio" name="decisao" value="aguardar" checked> Aguardar o produto (o pedido fica na fila; avisa o vendedor e a Produção)</label>
    <label class="caixa-marcar"><input type="radio" name="decisao" value="despachar_sem"> Despachar sem o que falta (avisa o vendedor e o Financeiro)</label>
    <label>Observação <small>obrigatória para despachar sem o que falta</small><input type="text" name="observacao" maxlength="500" value="<?= antigo('observacao') ?>"></label>
  </fieldset>
  <div class="acoes-form">
    <button class="botao contorno" type="submit">Salvar separação</button>
    <button class="botao lima" type="submit" name="concluir" value="1">Concluir separação</button>
  </div>
</form>
<?php else: ?>
<section class="card">
  <h2>Itens</h2>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th>Produto</th><th class="direita">Pedido</th><th class="direita">Separado</th><th>Lotes</th></tr></thead>
    <tbody><?php foreach ($p['itens'] as $i): ?><tr><td><?= e($i['nome']) ?></td><td class="direita"><?= (int) $i['quantidade'] ?></td>
      <td class="direita <?= $i['qtd_separada'] !== null && (int) $i['qtd_separada'] < (int) $i['quantidade'] ? 'pn-vermelho' : '' ?>"><?= $i['qtd_separada'] === null ? '—' : (int) $i['qtd_separada'] ?></td>
      <td class="pequeno"><?= e(implode(' · ', array_map(fn($l) => $l['lote'] . ($l['validade'] ? ' (vence ' . data_br($l['validade']) . ')' : '') . ' × ' . $l['quantidade'], $i['lotes']))) ?: '—' ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (!$editavel && $usadas): ?>
<section class="card dep-emb-resumo" data-embalagem-usada><h2>📦 Embalagem usada</h2>
  <p><?= implode(' · ', array_map(fn($u) => '<strong>' . e(rtrim(rtrim(number_format((float) $u['quantidade'], 3, ',', '.'), '0'), ',')) . ' ×</strong> ' . e($u['nome']), $usadas)) ?></p></section>
<?php endif; ?>

<?php if ($p['expedicao'] === 'separado'): ?>
<section class="card dep-despachar">
  <h2>Despachar</h2>
  <?php if ($p['falta']): ?><p class="aviso alerta">Vai sem alguns itens: <?= e((string) $p['expedicao_obs']) ?></p><?php endif; ?>
  <?php if ($pode): ?>
  <form method="post" action="<?= $acao('despachar') ?>" class="pd-form dep-despacho"><?= csrf_campo() ?>
    <label>Transportadora<input type="text" name="transportadora" list="transportadoras" required maxlength="80" value="<?= antigo('transportadora') ?>"></label>
    <datalist id="transportadoras"><?php foreach ($transportadoras as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    <label>Volumes<input type="number" name="volumes" min="1" value="<?= antigo('volumes', (string) max(1, $caixas)) ?>" required><?php if ($caixas): ?><small class="suave"><?= $caixas ?> caixa(s) na separação</small><?php endif; ?></label>
    <label>Peso (kg)<input type="text" name="peso" inputmode="decimal" value="<?= antigo('peso') ?>"></label>
    <label>Código de rastreio<input type="text" name="rastreio" maxlength="80" value="<?= antigo('rastreio') ?>"></label>
    <label class="dep-despacho-obs">Observação<input type="text" name="observacao" maxlength="500"></label>
    <div class="acoes-form"><button class="botao lima" type="submit">Despachar pedido</button></div>
  </form>
  <?php endif; ?>
</section>
<?php elseif ($p['expedicao'] === 'despachado'): ?>
<section class="card"><h2>Despachado</h2>
  <dl class="pd-dados">
    <div><dt>Quando</dt><dd><?= data_br($p['despachado_em'], true) ?> · <?= pessoa($p['despachante'] ?? null, null, false, 18, '') ?></dd></div>
    <div><dt>Transportadora</dt><dd><?= e($p['transportadora']) ?> · <?= (int) $p['volumes'] ?> volume(s)<?= $p['peso_kg'] ? ' · ' . rtrim(rtrim(number_format((float) $p['peso_kg'], 3, ',', '.'), '0'), ',') . ' kg' : '' ?></dd></div>
    <div><dt>Rastreio</dt><dd><?= e($p['rastreio'] ?: '—') ?></dd></div>
    <?php if ($p['expedicao_obs']): ?><div><dt>Observação</dt><dd><?= e($p['expedicao_obs']) ?></dd></div><?php endif; ?>
  </dl>
</section>
<?php endif; ?>
