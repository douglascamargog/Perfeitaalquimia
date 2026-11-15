<?php
/**
 * Página do orçamento (equipe): itens, enviar o link pelo WhatsApp, copiar, baixar imagem, editar, cancelar e histórico.
 * Variáveis: $o, $itens, $historico, $situacao, $link, $mensagem, $pagamento, $pedido
 */
use App\Core\Config;
use App\Services\OrcamentoService as O;

[$sn, $sc] = O::SITUACOES[$situacao];
$aberto = in_array($situacao, ['aberto', 'visto'], true);
$whats = preg_replace('/\D/', '', (string) ($o['whatsapp'] ?: $o['telefone']));
$whats = $whats !== '' && !str_starts_with($whats, '55') ? '55' . $whats : $whats;
$waLink = 'https://wa.me/' . $whats . '?text=' . rawurlencode($mensagem);
// Dados da imagem (desenhada no navegador pelo orcamento.js)
$imagem = [
    'numero' => (int) $o['id'], 'cliente' => $o['cliente'], 'cidade' => $o['cidade'] . '/' . $o['uf'], 'vendedor' => (string) $o['vendedor'],
    'validade' => data_br($o['validade']), 'data' => data_br($o['criado_em']), 'empresa' => (string) Config::get('empresa.nome', 'Perfeita Alquimia'),
    'logo' => asset('img/logo.png'), 'total' => dinheiro((float) $o['subtotal']), 'pagamento' => $pagamento ? trim($pagamento['forma'] . ' ' . ($pagamento['condicao'] ?? '')) : '',
    'itens' => array_map(fn($i) => ['codigo' => (string) $i['codigo'], 'nome' => $i['nome'], 'qtd' => (int) $i['quantidade'],
        'preco' => dinheiro((float) $i['preco_unitario']), 'total' => dinheiro((float) $i['total'])], $itens),
];
?>
<a class="voltar-link" href="<?= url('/orcamentos') ?>">← Orçamentos</a>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">ORÇAMENTO #<?= (int) $o['id'] ?> · TABELA <?= e(mb_strtoupper((string) $o['tabela'])) ?></div>
    <h1><?= e($o['cliente']) ?></h1>
    <p><span class="selo <?= $sc ?>" data-situacao="<?= e($situacao) ?>"><?= e($sn) ?></span>
      <?= $o['cliente_alterou'] ? '<span class="selo laranja">cliente alterou</span>' : '' ?>
      <?= e($o['cidade']) ?>/<?= e($o['uf']) ?> · criado em <?= data_br($o['criado_em']) ?> por <?= pessoa($o['vendedor'] ?? null, $o['vendedor_foto'] ?? null, false, 18) ?> · válido até <strong><?= data_br($o['validade']) ?></strong>
      <?= $o['visto_em'] ? ' · cliente abriu em ' . data_br($o['visto_em'], true) : '' ?></p>
  </div>
  <div class="acoes">
    <a class="botao contorno pequeno" href="<?= url('/carteira/lista') ?>?abrir=<?= (int) $o['cliente_id'] ?>">Ficha do cliente</a>
    <?php if ($aberto): ?>
      <a class="botao contorno pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $o['cliente_id'] ?>&orcamento=<?= (int) $o['id'] ?>" data-editar>Editar ou transformar em pedido</a>
      <form method="post" action="<?= url('/orcamentos/' . (int) $o['id'] . '/cancelar') ?>" data-confirmar="Cancelar este orçamento? O link do cliente para de funcionar."><?= csrf_campo() ?><button class="botao contorno pequeno perigo" type="submit">Cancelar</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if ($pedido): ?><p class="aviso sucesso">Virou o <a href="<?= url('/pedidos/' . (int) $pedido['id']) ?>"><strong>pedido #<?= (int) $pedido['id'] ?></strong></a> · <?= dinheiro((float) $pedido['total']) ?><?= $o['resposta_cliente'] ? ' · mensagem do cliente: “' . e($o['resposta_cliente']) . '”' : '' ?></p><?php endif; ?>
<?php if ($situacao === 'recusado'): ?><p class="aviso erro">O cliente recusou: “<?= e((string) $o['resposta_cliente']) ?>”. Que tal ajustar e mandar outro orçamento?</p><?php endif; ?>
<?php if ($situacao === 'expirado'): ?><p class="aviso atencao">Este orçamento venceu em <?= data_br($o['validade']) ?>: o link não abre mais. Faça um novo, se o cliente ainda tiver interesse.</p><?php endif; ?>

<div class="orc-pagina">
  <section class="card sem-espaco">
    <div class="tabela-rolagem">
      <table class="tabela" data-itens-orcamento>
        <thead><tr><th>Produto</th><th class="direita">Qtd.</th><th class="direita">Preço</th><th class="direita">Total</th></tr></thead>
        <tbody>
        <?php foreach ($itens as $i): $mudou = $i['quantidade_cliente'] !== null; ?>
          <tr>
            <td><?= $i['codigo'] ? '<span class="cod-produto">' . e($i['codigo']) . '</span> ' : '' ?><strong><?= e($i['nome']) ?></strong></td>
            <td class="direita"><?php if ($mudou): ?><span class="orc-riscado"><?= (int) $i['quantidade'] ?></span> <span class="orc-mudou" title="O cliente mudou"><?= (int) $i['quantidade_cliente'] ?></span><?php else: ?><?= (int) $i['quantidade'] ?><?php endif; ?></td>
            <td class="direita"><?= dinheiro((float) $i['preco_unitario']) ?></td>
            <td class="direita"><?= dinheiro((float) $i['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="orc-total"><span><?= count($itens) ?> produto(s)<?= $pagamento ? ' · sugerido: ' . e(trim($pagamento['forma'] . ' ' . ($pagamento['condicao'] ?? ''))) : '' ?></span><strong><?= dinheiro((float) $o['subtotal']) ?></strong></div>
    <?php if ($o['observacoes']): ?><p class="pequeno" style="padding:0 12px 12px">Recado para o cliente: <?= e($o['observacoes']) ?></p><?php endif; ?>
  </section>

  <aside class="card orc-enviar" data-orcamento-enviar data-enviado-url="<?= url('/orcamentos/' . (int) $o['id'] . '/enviado') ?>" data-imagem='<?= e(json_encode($imagem, JSON_UNESCAPED_UNICODE)) ?>'>
    <h2>Enviar para o cliente</h2>
    <?php if ($aberto): ?>
      <p class="pequeno suave">O cliente abre o link no celular, sem senha, confere os preços da tabela dele, muda o que quiser e aprova.</p>
      <div class="orc-link"><input type="text" readonly value="<?= e($link) ?>" aria-label="Link do orçamento" data-link><button class="botao contorno pequeno" type="button" data-copiar>Copiar</button></div>
      <div class="orc-botoes">
        <a class="botao lima" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer" data-whatsapp>Enviar pelo WhatsApp<?= $whats === '' ? ' (escolher contato)' : '' ?></a>
        <button class="botao contorno" type="button" data-baixar-imagem>Baixar imagem do orçamento</button>
        <a class="botao contorno" href="<?= e($link) ?>" target="_blank" rel="noopener">Ver como o cliente vê</a>
      </div>
      <details><summary class="pequeno">Mensagem que vai junto</summary><p class="orc-mensagem"><?= e($mensagem) ?></p></details>
    <?php else: ?>
      <p class="pequeno suave">O link não está mais aberto para o cliente.</p>
      <div class="orc-botoes"><button class="botao contorno" type="button" data-baixar-imagem>Baixar imagem do orçamento</button></div>
    <?php endif; ?>
    <h3>Histórico</h3>
    <ul class="ac-lista" data-historico><?php foreach ($historico as $h): ?><li><time><?= data_br($h['criado_em'], true) ?></time> <strong><?= $h['pelo_cliente'] ? 'Cliente' : pessoa($h['usuario'] ?? null, null, false, 18, 'Sistema') ?>:</strong> <?= e($h['texto']) ?></li><?php endforeach; ?></ul>
  </aside>
</div>
<canvas data-canvas-orcamento hidden></canvas>
