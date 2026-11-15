<?php
/** Lista de orçamentos. Variáveis: $aba, $busca, $orcamentos, $contagem */
use App\Core\Auth;
use App\Services\OrcamentoService as O;

$icones = asset('img/icones.svg');
$abas = ['abertos' => 'Esperando o cliente', 'aprovados' => 'Aprovados', 'encerrados' => 'Recusados e vencidos'];
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">COMERCIAL · ORÇAMENTOS</div>
    <h1>Orçamentos</h1>
    <p>Monte o orçamento, mande o link pelo WhatsApp e o cliente confere, muda e aprova. Aprovado, vira pedido sozinho.</p>
  </div>
  <div class="acoes">
    <?php if (Auth::pode('pedidos.criar')): ?>
      <a class="botao" href="<?= url('/pedidos/novo') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Novo orçamento</a>
      <a class="botao contorno" href="<?= url('/pedidos/modelos') ?>">Modelos de pedido</a>
    <?php endif; ?>
  </div>
</div>

<nav class="pr-abas orc-abas" aria-label="Orçamentos">
  <?php foreach ($abas as $k => $nome): ?><a href="<?= url('/orcamentos') ?>?aba=<?= $k ?>" class="<?= $aba === $k ? 'ativo' : '' ?>"><?= $nome ?> <small>(<?= (int) ($contagem[$k] ?? 0) ?>)</small></a><?php endforeach; ?>
</nav>

<form class="ped-busca" method="get" action="<?= url('/orcamentos') ?>" style="margin-bottom:12px">
  <input type="hidden" name="aba" value="<?= e($aba) ?>">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por cliente ou nº do orçamento…" aria-label="Buscar">
  <button class="botao contorno" type="submit">Buscar</button>
</form>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela orc-lista" data-orcamentos>
      <thead><tr><th>#</th><th>Cliente</th><th>Situação</th><th class="direita">Total</th><th>Válido até</th><th>Vendedor</th></tr></thead>
      <tbody>
      <?php foreach ($orcamentos as $o): [$sn, $sc] = O::SITUACOES[O::situacao($o)]; ?>
        <tr data-orcamento="<?= (int) $o['id'] ?>">
          <td><a href="<?= url('/orcamentos/' . (int) $o['id']) ?>"><strong>#<?= (int) $o['id'] ?></strong></a></td>
          <td><a href="<?= url('/orcamentos/' . (int) $o['id']) ?>"><?= e($o['cliente']) ?></a><small><?= e($o['cidade']) ?>/<?= e($o['uf']) ?> · <?= (int) $o['qtd_itens'] ?> produto(s)</small></td>
          <td><span class="selo <?= $sc ?>"><?= e($sn) ?></span><?= $o['cliente_alterou'] ? ' <span class="selo laranja">cliente alterou</span>' : '' ?>
            <?php if ($o['pedido_id']): ?><small><a href="<?= url('/pedidos/' . (int) $o['pedido_id']) ?>">pedido #<?= (int) $o['pedido_id'] ?></a></small><?php endif; ?>
            <?php if ($o['situacao'] === 'recusado' && $o['resposta_cliente']): ?><small>“<?= e(mb_strimwidth($o['resposta_cliente'], 0, 80, '…')) ?>”</small><?php endif; ?></td>
          <td class="direita"><?= dinheiro((float) $o['subtotal']) ?></td>
          <td><?= data_br($o['validade']) ?></td>
          <td class="pequeno"><?= pessoa($o['vendedor'] ?? null, $o['vendedor_foto'] ?? null) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orcamentos): ?><tr><td colspan="6" class="suave">Nenhum orçamento aqui. Para fazer um: <strong>Novo orçamento</strong>, escolha o cliente e os produtos e toque em <strong>Gerar orçamento</strong>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
