<?php
/**
 * Página do orçamento: dados, cotações, enviar para aprovação, decidir, comprar, recebimento e histórico.
 * Variáveis: $c, $cotacoes, $historico, $anteriores, $aprovadores, $pode, $podeDecidir, $podeReceber, $materias, $projetos
 */
use App\Services\CompraService as C;
use App\Services\ProducaoService as P;

[$sn, $sc] = C::SITUACOES[$c['situacao']] ?? [$c['situacao'], 'cinza'];
$acao = fn(string $a) => url('/compras/' . (int) $c['id'] . '/' . $a);
$montando = $pode && in_array($c['situacao'], ['rascunho', 'refazer'], true);
$menor = $cotacoes ? min(array_map(fn($x) => (float) $x['valor_total'], $cotacoes)) : null;
$escolhida = $c['cotacao_id'] ? current(array_filter($cotacoes, fn($x) => (int) $x['id'] === (int) $c['cotacao_id'])) : null;
?>
<a class="voltar-link" href="<?= url('/compras') ?>">← Orçamentos</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMPRAS · <?= e(mb_strtoupper(C::TIPOS[$c['tipo']] ?? $c['tipo'])) ?> · ORÇAMENTO #<?= (int) $c['id'] ?></div>
    <h1><?= e($c['titulo']) ?></h1>
    <p><span class="selo <?= $sc ?>" data-situacao="<?= e($c['situacao']) ?>"><?= e($sn) ?></span>
      <?= e(P::qtd((float) $c['quantidade'], (string) $c['unidade'])) ?>
      <?= $c['necessario_ate'] ? ' · precisa até <strong>' . data_br($c['necessario_ate']) . '</strong>' : '' ?>
      <?= $c['projeto'] ? ' · projeto ' . e($c['projeto']) : '' ?> · pediu <?= e((string) $c['solicitante']) ?></p></div>
  <?php if ($pode && in_array($c['situacao'], ['rascunho', 'refazer', 'aprovacao', 'aprovado'], true)): ?>
    <div class="acoes"><form method="post" action="<?= $acao('cancelar') ?>" data-confirmar="Cancelar este orçamento?"><?= csrf_campo() ?><input type="hidden" name="motivo" value=""><button class="botao contorno pequeno" type="submit">Cancelar orçamento</button></form></div>
  <?php endif; ?>
</div>

<?php if ($c['motivo']): ?><p class="aviso">Para quê: <?= nl2br(e($c['motivo'])) ?></p><?php endif; ?>
<?php if ($c['situacao'] === 'refazer' && $c['decisao_comentario']): ?><p class="aviso alerta"><strong><?= e((string) $c['aprovador']) ?> pediu outra cotação:</strong> <?= e($c['decisao_comentario']) ?></p><?php endif; ?>
<?php if ($c['situacao'] === 'recusado'): ?><p class="aviso erro">Recusado por <?= e((string) $c['aprovador']) ?>: <?= e((string) $c['decisao_comentario']) ?></p><?php endif; ?>

<?php if ($podeDecidir): ?>
<section class="card cp-decidir" data-decidir>
  <h2>Sua aprovação</h2>
  <p><?= e((string) $c['solicitante']) ?> escolheu <strong><?= e($escolhida['fornecedor'] ?? '—') ?></strong> por <strong><?= $escolhida ? dinheiro((float) $escolhida['valor_total']) : '—' ?></strong>
    <?= $c['escolha_motivo'] ? '· motivo: ' . e($c['escolha_motivo']) : '' ?>. Compare as cotações abaixo.</p>
  <form method="post" action="<?= $acao('decidir') ?>" class="pd-form"><?= csrf_campo() ?>
    <label>Comentário <small>obrigatório para pedir outra cotação ou recusar</small><textarea name="comentario" rows="2"></textarea></label>
    <div class="acoes-form">
      <button class="botao lima" type="submit" name="decisao" value="aprovado">Aprovar</button>
      <button class="botao contorno" type="submit" name="decisao" value="refazer">Pedir outra cotação</button>
      <button class="botao contorno perigo" type="submit" name="decisao" value="recusado">Recusar</button>
    </div>
  </form>
</section>
<?php elseif ($c['situacao'] === 'aprovacao'): ?>
<p class="aviso">Esperando a aprovação de <strong><?= e((string) $c['aprovador']) ?></strong> (enviado em <?= data_br($c['enviado_em'], true) ?>).</p>
<?php endif; ?>

<section class="card sem-espaco">
  <h2 class="pr-titulo-tabela">Cotações</h2>
  <div class="tabela-rolagem">
    <table class="tabela" data-cotacoes>
      <thead><tr><th>Fornecedor</th><th class="direita">Unitário</th><th class="direita">Frete</th><th class="direita">Total</th><th>Prazo</th><th>Condição</th><th>Anexo</th><?php if ($montando): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($cotacoes as $x): $barata = $menor !== null && abs((float) $x['valor_total'] - $menor) < 0.005; ?>
        <tr class="<?= (int) $x['id'] === (int) $c['cotacao_id'] ? 'cp-escolhida' : '' ?>" data-cotacao="<?= (int) $x['id'] ?>">
          <td><strong><?= e($x['fornecedor']) ?></strong><?= $barata ? ' <span class="selo lima">mais barata</span>' : '' ?><?= (int) $x['id'] === (int) $c['cotacao_id'] ? ' <span class="selo verde">escolhida</span>' : '' ?>
            <?= $x['observacao'] ? '<small class="suave">' . e($x['observacao']) . '</small>' : '' ?></td>
          <td class="direita"><?= dinheiro((float) $x['valor_unitario']) ?></td><td class="direita"><?= dinheiro((float) $x['frete']) ?></td>
          <td class="direita"><strong><?= dinheiro((float) $x['valor_total']) ?></strong></td>
          <td><?= $x['prazo_dias'] !== null ? (int) $x['prazo_dias'] . ' dia(s)' : '—' ?></td><td class="pequeno"><?= e((string) $x['condicao']) ?></td>
          <td><?= $x['arquivo'] ? '<a href="' . url('/compras/anexo/' . (int) $x['id']) . '" target="_blank" rel="noopener" data-anexo>ver</a>' : '—' ?></td>
          <?php if ($montando): ?><td><form method="post" action="<?= $acao('remover_cotacao') ?>"><?= csrf_campo() ?><input type="hidden" name="cotacao" value="<?= (int) $x['id'] ?>"><button class="botao contorno pequeno" type="submit">Remover</button></form></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cotacoes): ?><tr><td colspan="8" class="suave">Nenhuma cotação ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($anteriores): ?><p class="pequeno suave cp-anteriores">Já pagamos: <?= e(implode(' · ', array_map(fn($a) => $a['fornecedor'] . ' ' . dinheiro((float) $a['valor_unitario']) . '/' . $a['unidade'] . ' em ' . data_br($a['recebido_em']), $anteriores))) ?></p><?php endif; ?>
</section>

<?php if ($montando): ?>
<div class="ac-duas cp-duas">
  <section class="card">
    <h2>Adicionar cotação</h2>
    <form method="post" action="<?= $acao('cotacao') ?>" enctype="multipart/form-data" class="pd-form"><?= csrf_campo() ?>
      <label>Fornecedor<input type="text" name="fornecedor" maxlength="160" required></label>
      <div class="pd-duas"><label>Valor unitário (R$)<input type="text" name="valor_unitario" inputmode="decimal" required></label><label>Frete (R$)<input type="text" name="frete" inputmode="decimal" value="0"></label></div>
      <div class="pd-duas"><label>Prazo (dias)<input type="number" name="prazo_dias" min="0"></label><label>Condição<input type="text" name="condicao" maxlength="120" placeholder="Ex.: boleto 28 dias"></label></div>
      <label>Observação<input type="text" name="observacao" maxlength="500"></label>
      <label>Orçamento do fornecedor <small>PDF, JPG ou PNG (opcional)</small><input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png"></label>
      <button class="botao contorno" type="submit">Adicionar cotação</button>
    </form>
  </section>
  <section class="card">
    <h2>Enviar para aprovação</h2>
    <?php if ($cotacoes): ?>
    <form method="post" action="<?= $acao('enviar') ?>" class="pd-form" data-enviar><?= csrf_campo() ?>
      <label>Cotação escolhida<select name="cotacao" required><?php foreach ($cotacoes as $x): ?>
        <option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === (int) $c['cotacao_id'] ? 'selected' : '' ?>><?= e($x['fornecedor']) ?> · <?= dinheiro((float) $x['valor_total']) ?></option><?php endforeach; ?></select></label>
      <label>Motivo da escolha <small>obrigatório se não for a mais barata</small><input type="text" name="motivo_escolha" maxlength="300" value="<?= e((string) $c['escolha_motivo']) ?>"></label>
      <label>Quem aprova<select name="aprovador" required><option value="">— escolha a pessoa</option>
        <?php $grupo = null; foreach ($aprovadores as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $c['aprovador_id'] ? 'selected' : '' ?>><?= e($a['nome']) ?> · <?= e($a['perfil']) ?><?= $a['sugerido'] ? ' ★' : '' ?></option>
        <?php endforeach; ?></select></label>
      <button class="botao lima" type="submit">Enviar para aprovação</button>
    </form>
    <?php else: ?><p class="suave">Adicione pelo menos uma cotação.</p><?php endif; ?>
  </section>
</div>
<details class="card cp-editar"><summary>Editar os dados do orçamento</summary>
  <form method="post" action="<?= $acao('salvar') ?>" class="pd-form"><?= csrf_campo() ?>
    <?= \App\Core\Visao::renderizar('compras/_campos', compact('c', 'materias', 'projetos')) ?>
    <div class="acoes-form"><button class="botao contorno" type="submit">Salvar</button></div>
  </form>
</details>
<?php endif; ?>

<?php if ($pode && $c['situacao'] === 'aprovado'): ?>
<section class="card">
  <h2>Comprar</h2>
  <p>Aprovado por <?= e((string) $c['aprovador']) ?>. Faça a compra com <strong><?= e($escolhida['fornecedor'] ?? '') ?></strong> e registre aqui: o Depósito vai receber.</p>
  <form method="post" action="<?= $acao('comprar') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
    <label>Chega até<input type="date" name="previsto" value="<?= e(date('Y-m-d', strtotime('+' . (int) ($escolhida['prazo_dias'] ?? 7) . ' days'))) ?>"></label>
    <button class="botao lima" type="submit">Comprei</button></form>
</section>
<?php elseif ($c['situacao'] === 'comprado'): ?>
<p class="aviso">Comprado em <?= data_br($c['comprado_em']) ?><?= $c['previsto_em'] ? ' · chega até ' . data_br($c['previsto_em']) : '' ?>. O recebimento é registrado no <a href="<?= url('/deposito/recebimento') ?>">Depósito › Recebimento</a>.</p>
<?php elseif ($c['situacao'] === 'recebido'): ?>
<p class="aviso sucesso">Chegou em <?= data_br($c['recebido_em'], true) ?>: <?= e(P::qtd((float) $c['recebido_qtd'], (string) $c['unidade'])) ?> · recebido por <?= e((string) $c['recebedor']) ?><?= $c['materia_prima_id'] ? ' (entrou no estoque)' : '' ?>.</p>
<?php endif; ?>

<section class="card">
  <h2>Histórico</h2>
  <ul class="ac-lista"><?php foreach ($historico as $h): ?><li><time><?= data_br($h['criado_em'], true) ?></time> <strong><?= e($h['usuario'] ?? 'Sistema') ?>:</strong> <?= e($h['texto']) ?></li><?php endforeach; ?></ul>
</section>
