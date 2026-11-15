<?php
/** Depósito › Recebimento: compras da Qualidade que vão chegar. Variáveis: $itens, $recebidos, $pode */
use App\Core\Visao;
use App\Services\CompraService as C;
use App\Services\ProducaoService as P;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DEPÓSITO · RECEBIMENTO</div><h1>Compras para receber</h1>
    <p>O que a Qualidade comprou e ainda não chegou. Ao registrar a chegada, matéria-prima e rótulo entram sozinhos no estoque da Produção.</p></div>
</div>
<?= Visao::renderizar('deposito/_abas', ['atual' => '/deposito/recebimento']) ?>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela" data-receber>
      <thead><tr><th>O que chega</th><th>Fornecedor</th><th class="direita">Comprado</th><th>Previsão</th><?php if ($pode): ?><th class="nao-imprimir">Chegou</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($itens as $c): $atrasada = $c['previsto_em'] && $c['previsto_em'] < date('Y-m-d'); ?>
        <tr data-compra="<?= (int) $c['id'] ?>">
          <td><a href="<?= url('/compras/' . (int) $c['id']) ?>"><strong><?= e($c['titulo']) ?></strong></a>
            <small class="suave"><?= e(C::TIPOS[$c['tipo']] ?? $c['tipo']) ?><?= $c['materia_prima_id'] ? ' · entra no estoque' : '' ?> · pediu <?= pessoa($c['solicitante'] ?? null, null, false, 18, '—') ?></small></td>
          <td><?= e((string) $c['fornecedor']) ?></td>
          <td class="direita"><?= e(P::qtd((float) $c['quantidade'], (string) $c['unidade'])) ?></td>
          <td class="<?= $atrasada ? 'pn-vermelho' : '' ?>"><?= $c['previsto_em'] ? data_br($c['previsto_em']) . ($atrasada ? ' · atrasada' : '') : '—' ?></td>
          <?php if ($pode): ?><td class="nao-imprimir">
            <form method="post" action="<?= url('/deposito/recebimento/' . (int) $c['id']) ?>" class="te-mov" enctype="multipart/form-data"><?= csrf_campo() ?>
              <input type="text" name="quantidade" inputmode="decimal" required value="<?= e(rtrim(rtrim(number_format((float) $c['quantidade'], 3, ',', ''), '0'), ',')) ?>" aria-label="Quantidade que chegou de <?= e($c['titulo']) ?>">
              <input type="text" name="nf" maxlength="30" placeholder="nº da NF" aria-label="Número da nota fiscal" style="max-width:110px">
              <label class="botao contorno pequeno" title="Anexar a nota fiscal (PDF, imagem ou XML)">📎 NF<input type="file" name="arquivo_nf" accept="application/pdf,image/*,.xml,text/xml" hidden></label>
              <?php if ($c['materia_prima_id'] && ($c['mp_tipo'] ?? 'materia_prima') === 'materia_prima'): // lote do fabricante (a OP puxa daqui) ?>
                <input type="text" name="lote" maxlength="60" placeholder="lote do fabricante" aria-label="Lote do fabricante" style="max-width:150px" data-lote-recebimento>
                <label class="pequeno">validade <input type="date" name="validade" aria-label="Validade do lote"></label>
                <label class="pequeno">fabricação <input type="date" name="fabricacao" aria-label="Fabricação do lote"></label>
                <input type="text" name="fabricante" maxlength="120" placeholder="fabricante" aria-label="Fabricante" style="max-width:140px">
              <?php endif; ?>
              <input type="text" name="observacao" maxlength="255" placeholder="obs. (conferência…)" aria-label="Observação">
              <button class="botao contorno pequeno" type="submit">Chegou</button></form></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="5" class="suave">Nada para receber agora.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php if ($recebidos): ?>
<section class="card">
  <h2>Últimos recebimentos</h2>
  <ul class="ac-lista"><?php foreach ($recebidos as $r): ?>
    <li><time><?= data_br($r['recebido_em'], true) ?></time> <strong><?= e($r['titulo']) ?></strong> · <?= e(P::qtd((float) $r['recebido_qtd'], (string) $r['unidade'])) ?> · <?= pessoa($r['recebedor'] ?? null, null, false, 18, '—') ?></li>
  <?php endforeach; ?></ul>
</section>
<?php endif; ?>
