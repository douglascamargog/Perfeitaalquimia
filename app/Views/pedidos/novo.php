<?php
/**
 * Novo pedido — passo 2: produtos (preço da tabela do cliente), pagamento e enviar.
 * Variáveis: $c (cliente), $tabela, $produtos, $quantidades, $minimo, $formas, $boletoBloqueado, $ultimo, $uuid,
 *            $orcamento (editando um orçamento em aberto, ou null), $modelos, $podeCampanha, $setores
 * O mesmo formulário serve para "Enviar pedido" e "Gerar orçamento" (botão com formaction).
 */
$jsonModelos = array_map(fn($m) => ['id' => (int) $m['id'], 'itens' => (object) $m['itens']], $modelos);
// Campanhas no ar para este cliente: faixas para o resumo calcular ao vivo (o servidor confere ao enviar)
$campanhas = \App\Services\CampanhaService::paraCliente($c);
$jsonFaixas = [];
foreach ($campanhas as $cp) {
    foreach ($cp['faixas'] as $f) {
        $jsonFaixas[] = ['valor' => (float) $f['a_partir_de'], 'tipo' => $f['tipo'], 'pct' => (float) $f['desconto_pct'], 'texto' => \App\Services\CampanhaService::textoFaixa($f),
            'brinde' => (string) $f['brinde'], 'soma' => (bool) (int) $cp['soma_desconto_vendedor'], 'campanha' => $cp['titulo'] ?: $cp['nome']];
    }
}
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo"><?= $orcamento ? 'ORÇAMENTO #' . (int) $orcamento['id'] . ' · EDITANDO' : 'NOVO PEDIDO OU ORÇAMENTO' ?> · TABELA <?= e(mb_strtoupper($tabela['nome'])) ?></div>
    <h1><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></h1>
    <p><?= e($c['cidade']) ?>/<?= e($c['uf']) ?> · pedido mínimo da região: <strong><?= dinheiro($minimo) ?></strong>
      <?= $boletoBloqueado ? ' · <span class="selo vermelho">Boleto bloqueado (título em aberto)</span>' : '' ?></p>
  </div>
  <div class="acoes">
    <?php if ($ultimo): ?><a class="botao contorno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $c['id'] ?>&repetir=1">↻ Repetir último pedido</a><?php endif; ?>
    <a class="botao contorno" href="<?= url('/pedidos/novo') ?>">Trocar cliente</a>
  </div>
</div>

<form class="ped-novo" method="post" action="<?= url('/pedidos') ?>" data-pedido data-minimo="<?= e((string) $minimo) ?>">
  <?= csrf_campo() ?>
  <input type="hidden" name="cliente_id" value="<?= (int) $c['id'] ?>">
  <input type="hidden" name="uuid" value="<?= e($uuid) ?>">
  <?php if ($orcamento): ?><input type="hidden" name="orcamento_id" value="<?= (int) $orcamento['id'] ?>"><?php endif; ?>
  <?php if ($orcamento): ?><p class="aviso ped-aviso-orc">Você está mexendo no <a href="<?= url('/orcamentos/' . (int) $orcamento['id']) ?>">orçamento #<?= (int) $orcamento['id'] ?></a>:
    <strong>Atualizar orçamento</strong> mantém o mesmo link do cliente; <strong>Enviar pedido</strong> transforma ele em pedido agora.</p><?php endif; ?>
  <?php if (!empty($corrigir)): ?><input type="hidden" name="corrigir_id" value="<?= (int) $corrigir['id'] ?>">
    <p class="aviso ped-aviso-orc" data-corrigindo>Você está corrigindo o <a href="<?= url('/pedidos/' . (int) $corrigir['id']) ?>">pedido #<?= (int) $corrigir['id'] ?></a>:
      ajuste os itens e clique em <strong>Enviar pedido</strong>. O pedido novo substitui o antigo, que fica cancelado (o Financeiro aprova o novo).</p><?php endif; ?>
  <section class="card ped-modelos" data-modelos='<?= e(json_encode($jsonModelos)) ?>'>
    <div class="ped-modelos-usar">
      <label for="usar-modelo"><strong>Usar um modelo</strong> <small>soma as quantidades do modelo ao que já está escolhido</small></label>
      <div class="ped-modelos-linha">
        <select id="usar-modelo" data-modelo-escolha>
          <option value="">— escolha um modelo pronto</option>
          <?php foreach (['campanha' => 'Campanhas da equipe', 'pessoal' => 'Meus modelos'] as $tipo => $grupo): $doTipo = array_filter($modelos, fn($m) => $m['tipo'] === $tipo); if (!$doTipo) continue; ?>
            <optgroup label="<?= $grupo ?>"><?php foreach ($doTipo as $m): ?>
              <option value="<?= (int) $m['id'] ?>" title="<?= e(implode(', ', $m['nomes'] ?? [])) ?>"><?= e($m['nome']) ?> · <?= count($m['itens']) ?> produto(s)<?= $m['setor'] ? ' · ' . e($m['setor']) : '' ?><?= $m['validade_ate'] ? ' · até ' . data_br($m['validade_ate']) : '' ?></option>
            <?php endforeach; ?></optgroup>
          <?php endforeach; ?>
        </select>
        <button type="button" class="botao contorno" data-modelo-aplicar>Aplicar</button>
        <a class="botao contorno pequeno" href="<?= url('/pedidos/modelos') ?>">Gerenciar modelos</a>
      </div>
      <p class="pequeno suave" data-modelo-msg role="status"><?= $modelos ? '' : 'Nenhum modelo ainda: escolha os produtos e use "Salvar como modelo".' ?></p>
    </div>
    <details class="ped-modelos-salvar" data-salvar-modelo>
      <summary>Salvar como modelo</summary>
      <div class="ped-modelos-campos">
        <label>Nome do modelo<input type="text" name="modelo_nome" maxlength="120" placeholder="Ex.: Primeiro pedido — loja pequena"></label>
        <?php if ($podeCampanha): ?>
          <label>Para quem<select name="modelo_tipo"><option value="pessoal">Só para mim</option><option value="campanha">Campanha para a equipe</option></select></label>
          <label>Setor <small>campanha</small><select name="modelo_funcao"><option value="">Todos os setores</option><?php foreach ($setores as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?></option><?php endforeach; ?></select></label>
          <label>Vale até <small>campanha</small><input type="date" name="modelo_validade"></label>
        <?php endif; ?>
        <button class="botao contorno" type="submit" formaction="<?= url('/pedidos/modelos') ?>" formnovalidate data-salvar-modelo-botao>Salvar modelo</button>
      </div>
      <p class="pequeno suave">Guarda os produtos e as quantidades escolhidos (sem preço: o preço é sempre o da tabela do cliente).</p>
    </details>
  </section>
  <section class="card ped-produtos">
    <div class="ped-produtos-topo">
      <input type="search" placeholder="Filtrar por produto ou código…" aria-label="Filtrar produtos" data-filtro-produtos>
      <label class="opcao"><input type="checkbox" data-so-escolhidos> Só os escolhidos</label>
    </div>
    <div class="tabela-rolagem">
      <table class="tabela ped-tabela">
        <thead><tr><th>Produto</th><th class="direita">Preço</th><th>Qtd.</th><th>Desc. %</th><th class="direita">Total</th></tr></thead>
        <tbody>
        <?php foreach ($produtos as $p): $q = (int) ($quantidades[$p['id']] ?? 0); ?>
          <tr data-linha class="<?= !empty($p['terceirizado']) ? 'ped-linha-marca' : '' ?>" data-nome="<?= e(mb_strtolower(($p['codigo'] ?? '') . ' ' . $p['nome'] . ' ' . $p['categoria'])) ?>" data-preco="<?= e((string) $p['preco']) ?>">
            <td><div class="ped-produto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?>
              <span><strong><?= !empty($p['codigo']) ? '<span class="cod-produto">' . e($p['codigo']) . '</span> ' : '' ?><?= e($p['nome']) ?></strong><small><?= !empty($p['terceirizado']) ? '<span class="selo-marca">marca do cliente</span> ' . (int) $p['rotulos'] . ' rótulo(s) · ' : '' ?><?= e($p['embalagem']) ?><?= $p['estoque'] !== null ? ' · estoque ' . (int) $p['estoque'] : '' ?></small></span></div></td>
            <td class="direita"><?php if (!empty($p['preco_cheio'])): ?><s class="ped-preco-cheio"><?= dinheiro((float) $p['preco_cheio']) ?></s><span class="ped-promo">-<?= e(\App\Services\CampanhaService::pct((float) $p['promo_pct'])) ?></span><br><?php endif; ?><?= dinheiro((float) $p['preco']) ?></td>
            <td><input class="ped-qtd" type="number" name="qtd[<?= e((string) $p['id']) ?>]" min="0" max="<?= !empty($p['terceirizado']) ? 99999 : 9999 ?>" value="<?= $q ?: '' ?>" placeholder="0" aria-label="Quantidade de <?= e($p['nome']) ?>"></td>
            <td><input class="ped-desc" type="text" inputmode="decimal" name="desconto[<?= e((string) $p['id']) ?>]" value="<?= e((string) (\App\Core\Sessao::antigo('desconto', [])[$p['id']] ?? '')) ?>" placeholder="0" aria-label="Desconto (%) em <?= e($p['nome']) ?>"></td>
            <td class="direita" data-total-linha><?= $q ? dinheiro($q * (float) $p['preco']) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside class="card ped-resumo">
    <h2>Resumo</h2>
    <?php if ($campanhas): ?>
      <div class="ped-campanha" data-campanha='<?= e(json_encode($jsonFaixas, JSON_UNESCAPED_UNICODE)) ?>'>
        <small>Campanha<?= count($campanhas) > 1 ? 's' : '' ?>: <?= e(implode(', ', array_map(fn($cp) => $cp['titulo'] ?: $cp['nome'], $campanhas))) ?></small>
        <strong data-campanha-msg></strong>
        <span data-campanha-ganhou hidden></span>
        <div class="ped-campanha-barra"><i data-campanha-barra style="width:0"></i></div>
      </div>
    <?php endif; ?>
    <div class="ped-total"><span><b data-itens>0</b> item(ns)</span><strong data-subtotal>R$ 0,00</strong></div>
    <div class="ped-desconto" data-desconto-max="<?= e((string) (float) \App\Core\Config::get('pedidos.desconto_max_pct', 10)) ?>">
      <label>Desconto no pedido (%)<input type="text" inputmode="decimal" name="desconto_pedido" value="<?= antigo('desconto_pedido') ?>" placeholder="0" data-desconto-pedido></label>
      <p class="ped-desconto-resumo" data-desconto-resumo hidden></p>
      <p class="ped-desconto-resumo campanha" data-desconto-campanha hidden></p>
    </div>
    <div class="cat-minimo falta" data-caixa-minimo><span data-texto-minimo></span><div class="cat-minimo-barra"><i data-barra-minimo style="width:0"></i></div></div>
    <h3>Pagamento</h3>
    <?php foreach ($formas as $f): ?>
      <fieldset class="cat-forma <?= $f['bloqueio'] ? 'bloqueada' : '' ?>">
        <legend><?= e($f['nome']) ?></legend>
        <?php if ($f['bloqueio']): ?><p class="pequeno"><?= e($f['bloqueio']) ?></p><?php else: foreach ($f['condicoes'] as $cd): ?>
          <label class="opcao" data-condicao-minimo="<?= e((string) $cd['valor_minimo']) ?>">
            <input type="radio" name="pagamento" value="<?= (int) $f['id'] ?>:<?= (int) $cd['id'] ?>" required <?= antigo('pagamento') === $f['id'] . ':' . $cd['id'] ? 'checked' : '' ?>>
            <?= e($cd['nome']) ?><?= (float) $cd['desconto_pct'] > 0 ? ' · ' . number_format((float) $cd['desconto_pct'], 1, ',', '.') . '% desc.' : '' ?>
            <?= (float) $cd['valor_minimo'] > 0 ? '<small>(a partir de ' . e(dinheiro((float) $cd['valor_minimo'])) . ')</small>' : '' ?>
          </label>
        <?php endforeach; endif; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="campo"><label for="obs">Observação</label><textarea id="obs" name="observacoes" rows="2" maxlength="1000"><?= antigo('observacoes') ?></textarea></div>
    <button class="botao largo" type="submit" data-enviar>Enviar pedido</button>
    <p class="pequeno suave">Vai ao ERP como orçamento e para a aprovação do Financeiro.</p>
    <div class="ped-ou"><span>ou</span></div>
    <button class="botao lima largo" type="submit" formaction="<?= url('/orcamentos') ?>" formnovalidate data-gerar-orcamento><?= $orcamento ? 'Atualizar orçamento' : 'Gerar orçamento para o cliente' ?></button>
    <p class="pequeno suave">O cliente recebe um link pelo WhatsApp, confere, muda o que quiser e aprova. Aprovado, vira pedido sozinho.</p>
  </aside>
</form>
