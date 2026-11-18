<?php
/**
 * Administração › Importar Nota10 (só o Admin).
 * Etapa 1: enviar o backup → ler para a área separada → conferir e ligar vendedores, tabelas, formas e segmentos.
 * Variáveis: $imp (última importação ou null), $soltos (arquivos na pasta), $limite (tamanho máximo do envio)
 *   e, depois de lido: $tabelas, $cli, $notas, $itens, $prod, $titulos, $outros, $problemas, $ligar, $usuarios, $tabelasOne, $formasOne, $segmentosOne
 */
use App\Services\Nota10\Conferencia;

$icones = asset('img/icones.svg');
$n = fn($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.');
$mb = fn($b) => number_format($b / (1 << 20), 1, ',', '.') . ' MB';
$lido = $imp && $imp['situacao'] === 'lido';
$opcoes = function (array $lista, $atual, string $vazio = 'Escolha…') {
    $h = '<option value="">' . e($vazio) . '</option>';
    foreach ($lista as $o) {
        $h .= '<option value="' . (int) $o['id'] . '"' . ((string) $atual === (string) $o['id'] ? ' selected' : '') . '>' . e($o['nome']) . '</option>';
    }
    return $h;
};
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO · IMPORTAR NOTA10</div><h1>Trazer os dados do Nota10</h1>
    <p>Envie o backup do banco do Nota10. O One lê tudo numa área separada e mostra o que veio, para você conferir antes de importar.
      Nesta etapa nada muda nos clientes, pedidos e produtos do One.</p></div>
</div>

<ol class="n10-passos" aria-label="Etapas">
  <li class="<?= !$imp ? 'atual' : 'feito' ?>"><b>1</b> Enviar o backup</li>
  <li class="<?= $imp && !$lido ? 'atual' : ($lido ? 'feito' : '') ?>"><b>2</b> Ler</li>
  <li class="<?= $lido ? 'atual' : '' ?>"><b>3</b> Conferir e ligar</li>
  <li><b>4</b> Importar <small>(próxima versão)</small></li>
</ol>

<?php if (!$imp || $imp['situacao'] === 'erro' && !$imp['posicao']): ?>
<section class="card n10-envio" data-n10-envio>
  <h2>Enviar o backup</h2>
  <p>No computador do Nota10, exporte o banco <code>nota10</code> inteiro em <strong>.sql</strong> (HeidiSQL: botão direito no banco › Exportar banco de dados como SQL;
    ou <code>mysqldump nota10 &gt; nota10.sql</code>). Pode compactar em <strong>.zip</strong> para ficar menor.</p>
  <form method="post" action="<?= url('/sistema/nota10/enviar') ?>" enctype="multipart/form-data" class="n10-form"><?= csrf_campo() ?>
    <input type="file" name="backup" accept=".sql,.zip,.gz" required aria-label="Arquivo do backup">
    <button class="botao" type="submit"><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>Enviar</button>
  </form>
  <p class="pequeno suave">Limite de envio desta hospedagem: <strong><?= e($limite) ?></strong>. Se o arquivo for maior, coloque-o na pasta
    <code>storage/nota10</code> pelo Gerenciador de arquivos do cPanel (ou FTP) e ele aparece aqui embaixo.</p>
  <?php if ($soltos): ?>
    <h3>Arquivos na pasta storage/nota10</h3>
    <ul class="n10-soltos" data-n10-soltos>
      <?php foreach ($soltos as $s): ?>
        <li><span><strong><?= e($s['nome']) ?></strong> · <?= e($mb($s['tamanho'])) ?> · <?= date('d/m/Y H:i', $s['data']) ?></span>
          <form method="post" action="<?= url('/sistema/nota10/usar-arquivo') ?>"><?= csrf_campo() ?><input type="hidden" name="arquivo" value="<?= e($s['nome']) ?>">
            <button class="botao contorno pequeno" type="submit">Usar este arquivo</button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($imp && !$lido): ?>
<section class="card n10-leitura" data-n10-leitura data-id="<?= (int) $imp['id'] ?>" data-url="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/ler') ?>">
  <h2>Ler o backup</h2>
  <p><strong><?= e($imp['nome_original']) ?></strong> · <?= e($mb((int) $imp['tamanho'])) ?> · enviado em <?= data_br($imp['criado_em'], true) ?></p>
  <div class="n10-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $imp['tamanho'] ? round($imp['posicao'] / $imp['tamanho'] * 100) : 0 ?>">
    <span data-n10-pct style="width: <?= $imp['tamanho'] ? round($imp['posicao'] / $imp['tamanho'] * 100, 1) : 0 ?>%"></span></div>
  <p class="pequeno suave" data-n10-status><?= $imp['situacao'] === 'lendo' ? 'Leitura parada no meio. Clique para continuar de onde parou.' : 'O One lê em partes; deixe esta página aberta até terminar.' ?></p>
  <?php if ($imp['erro']): ?><div class="aviso erro" data-n10-erro>Parou com erro: <?= e($imp['erro']) ?></div><?php endif; ?>
  <div class="acoes">
    <button class="botao" type="button" data-n10-ler><?= $imp['posicao'] ? 'Continuar a leitura' : 'Ler o backup' ?></button>
    <?php if ($imp['situacao'] === 'erro' || $imp['posicao']): ?>
      <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/recomecar') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit">Recomeçar do início</button></form>
    <?php endif; ?>
    <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/descartar') ?>" data-confirmar="Apagar este backup do servidor?"><?= csrf_campo() ?>
      <button class="botao contorno" type="submit">Descartar</button></form>
  </div>
</section>
<?php endif; ?>

<?php if ($lido): ?>
<section class="card n10-lido" data-n10-lido>
  <div>
    <h2>Backup lido</h2>
    <p><strong><?= e($imp['nome_original']) ?></strong> · <?= e($mb((int) $imp['tamanho'])) ?> · lido em <?= data_br($imp['lido_em'], true) ?>
      · <?= $n($imp['comandos']) ?> comando(s) usados, <?= $n($imp['ignorados']) ?> pulados (tabelas que o One não usa)</p>
  </div>
  <div class="acoes">
    <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/recomecar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Ler de novo</button></form>
    <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/descartar') ?>" data-confirmar="Apagar este backup e a área separada do servidor?"><?= csrf_campo() ?>
      <button class="botao contorno pequeno" type="submit">Descartar e enviar outro</button></form>
  </div>
</section>

<div class="n10-kpis" data-n10-numeros>
  <div class="card"><span>Cadastros</span><strong data-n10="clientes"><?= $n($cli['total'] ?? 0) ?></strong><small><?= $n($cli['com_cnpj'] ?? 0) ?> com CNPJ · <?= $n($cli['ja_no_one'] ?? 0) ?> já estão no One · <?= $n(($cli['entrega_campos'] ?? 0) + ($cli['entrega_na_obs'] ?? 0)) ?> com outro endereço de entrega</small></div>
  <div class="card"><span>Pedidos e notas</span><strong data-n10="notas"><?= $n($notas['total'] ?? 0) ?></strong>
    <small><?= !empty($notas['periodo']['de']) ? 'de ' . data_br($notas['periodo']['de']) . ' a ' . data_br($notas['periodo']['ate']) : 'sem datas' ?></small></div>
  <div class="card"><span>Itens vendidos</span><strong data-n10="itens"><?= $n($itens['total'] ?? 0) ?></strong><small><?= $n($itens['com_custo'] ?? 0) ?> com custo · <?= $n($itens['com_lote'] ?? 0) ?> com lote</small></div>
  <div class="card"><span>Títulos a receber em aberto</span><strong data-n10="abertos"><?= $n($titulos['abertos'] ?? 0) ?></strong>
    <small><?= $n($titulos['vencidos'] ?? 0) ?> vencidos · <?= dinheiro($titulos['valor_vencido'] ?? 0) ?></small></div>
  <div class="card"><span>Produtos</span><strong data-n10="produtos"><?= $n($prod['total'] ?? 0) ?></strong><small><?= $n($prod['no_one'] ?? 0) ?> já no One · <?= $n($prod['novos'] ?? 0) ?> só no Nota10</small></div>
  <div class="card"><span>Contatos e histórico</span><strong data-n10="contatos"><?= $n($outros['contatos']) ?></strong><small>pessoas das lojas · <?= $n($outros['followup']) ?> follow-ups (<?= $n($outros['agendados']) ?> agendados)</small></div>
</div>

<?php if ($problemas): ?>
<section class="card n10-bloco" data-n10-problemas>
  <h2>O que conferir</h2>
  <ul class="n10-problemas">
    <?php foreach ($problemas as [$nivel, $texto]): ?><li class="<?= $nivel ?>"><?= e($texto) ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="n10-duas">
  <section class="card n10-bloco">
    <h2>Pedidos por ano</h2>
    <p class="pequeno suave">Todos os pedidos/notas não cancelados, de qualquer operação.</p>
    <script type="application/json" id="n10-anos"><?= json_encode(array_map(fn($a) => ['ano' => (int) $a['ano'], 'valor' => (float) $a['valor'], 'qtd' => (int) $a['qtd']], $notas['por_ano'] ?? []), JSON_HEX_TAG) ?></script>
    <div class="n10-canvas"><canvas id="n10-grafico-anos" aria-label="Valor dos pedidos por ano"></canvas></div>
  </section>
  <section class="card n10-bloco" data-n10-operacoes>
    <h2>Tipo de operação</h2>
    <p class="pequeno suave">Pelo CFOP. Só "Venda" conta como faturamento e meta; bonificação e devolução ficam separadas.</p>
    <table class="tabela"><thead><tr><th>Operação</th><th class="direita">Notas</th><th class="direita">Valor</th></tr></thead><tbody>
      <?php foreach ($notas['por_operacao'] ?? [] as $o): ?><tr><td><?= e($o['rotulo']) ?></td><td class="direita"><?= $n($o['qtd']) ?></td><td class="direita"><?= dinheiro($o['valor']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div>

<form method="post" action="<?= url('/sistema/nota10/ligacoes') ?>" id="ligacoes" class="n10-ligacoes" data-n10-ligacoes><?= csrf_campo() ?>
  <div class="cabecalho-secao"><div><h2>Ligar o Nota10 ao One</h2>
    <p>Diga para onde vai cada coisa. O One já sugeriu pelo nome; confira e mude o que precisar. Pode guardar e voltar depois.</p></div></div>

  <section class="card n10-bloco" data-n10-tipos>
    <h3>Tipos de cadastro</h3>
    <p class="pequeno suave">No Nota10, clientes, fornecedores e transportadoras ficam no mesmo cadastro (campo "Tipo").</p>
    <table class="tabela"><thead><tr><th>Tipo no Nota10</th><th class="direita">Cadastros</th><th>No One vira</th></tr></thead><tbody>
      <?php foreach ($ligar['tipo_cadastro'] as $t): ?>
        <tr><td><strong><?= e($t['nome']) ?></strong></td><td class="direita"><?= $n($t['qtd']) ?></td>
          <td><select name="mapa[tipo_cadastro][<?= e($t['codigo']) ?>]" aria-label="Tipo <?= e($t['nome']) ?>">
            <?php foreach (Conferencia::TIPOS_CADASTRO as $k => $r): ?><option value="<?= $k ?>" <?= $t['destino'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="card n10-bloco" data-n10-vendedores>
    <h3>Vendedores</h3>
    <p class="pequeno suave">O representante de cada cliente e o vendedor de cada pedido no Nota10. Quem ficar sem pessoa: os clientes vão para a fila de Prospecção e Reativação, e os pedidos ficam sem vendedor.</p>
    <table class="tabela"><thead><tr><th>Vendedor no Nota10</th><th class="direita">Clientes</th><th class="direita">Pedidos</th><th>Pessoa no One</th></tr></thead><tbody>
      <?php foreach ($ligar['vendedor'] as $v): ?>
        <tr data-vendedor="<?= e($v['codigo']) ?>"><td><strong><?= e($v['nome']) ?></strong> <small class="suave">código <?= e($v['codigo']) ?></small></td>
          <td class="direita"><?= $n($v['clientes']) ?></td><td class="direita"><?= $n($v['pedidos']) ?></td>
          <td><select name="mapa[vendedor][<?= e($v['codigo']) ?>]" aria-label="Pessoa para <?= e($v['nome']) ?>"><?= $opcoes($usuarios, $v['destino'], 'Ninguém (fila de reativação)') ?></select></td></tr>
      <?php endforeach; ?>
      <?php if (!$ligar['vendedor']): ?><tr><td colspan="4" class="suave">O backup não tem vendedores nos clientes nem nos pedidos.</td></tr><?php endif; ?>
    </tbody></table>
  </section>

  <div class="n10-duas">
    <section class="card n10-bloco" data-n10-tabelas>
      <h3>Tabelas de preço</h3>
      <table class="tabela"><thead><tr><th>No Nota10</th><th>No One</th></tr></thead><tbody>
        <?php foreach ($ligar['tabela'] as $t): ?><tr><td><?= e($t['nome']) ?></td><td><select name="mapa[tabela][<?= e($t['codigo']) ?>]" aria-label="Tabela <?= e($t['nome']) ?>"><?= $opcoes($tabelasOne, $t['destino'], 'Não trazer') ?></select></td></tr><?php endforeach; ?>
        <?php if (!$ligar['tabela']): ?><tr><td colspan="2" class="suave">Sem tabelas de preço no backup.</td></tr><?php endif; ?>
      </tbody></table>
    </section>
    <section class="card n10-bloco" data-n10-formas>
      <h3>Formas de pagamento</h3>
      <table class="tabela"><thead><tr><th>No Nota10</th><th>No One</th></tr></thead><tbody>
        <?php foreach ($ligar['forma'] as $t): ?><tr><td><?= e($t['nome']) ?></td><td><select name="mapa[forma][<?= e($t['codigo']) ?>]" aria-label="Forma <?= e($t['nome']) ?>"><?= $opcoes($formasOne, $t['destino'], 'Guardar só o nome') ?></select></td></tr><?php endforeach; ?>
        <?php if (!$ligar['forma']): ?><tr><td colspan="2" class="suave">Sem formas de pagamento no backup.</td></tr><?php endif; ?>
      </tbody></table>
    </section>
  </div>

  <?php if ($ligar['segmento']): ?>
  <section class="card n10-bloco" data-n10-segmentos>
    <h3>Segmentos dos clientes</h3>
    <table class="tabela"><thead><tr><th>No Nota10</th><th class="direita">Clientes</th><th>No One</th></tr></thead><tbody>
      <?php foreach ($ligar['segmento'] as $s): ?><tr><td><?= e($s['nome']) ?></td><td class="direita"><?= $n($s['qtd']) ?></td>
        <td><select name="mapa[segmento][<?= e($s['codigo']) ?>]" aria-label="Segmento <?= e($s['nome']) ?>"><?= $opcoes($segmentosOne, $s['destino'], 'Sem segmento (validar depois)') ?></select></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>

  <div class="n10-salvar"><button class="botao" type="submit" data-n10-salvar>Guardar as ligações</button>
    <span class="pequeno suave">A importação (próxima versão) usa estas escolhas. Nada é gravado nos dados do One agora.</span></div>
</form>

<details class="card n10-bloco" data-n10-detalhes>
  <summary><strong>Detalhes do backup</strong> <span class="suave pequeno">tabelas recebidas e como o Nota10 marca os pedidos</span></summary>
  <div class="n10-duas" style="margin-top:12px">
    <table class="tabela"><thead><tr><th>Tabela do Nota10</th><th class="direita">Linhas</th></tr></thead><tbody>
      <?php foreach ($tabelas as $t => $q): ?><tr><td><code><?= e($t) ?></code></td><td class="direita"><?= $q === null ? '<span class="suave">não veio</span>' : $n($q) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div>
      <?php if (!empty($notas['situacoes'])): ?>
        <table class="tabela"><thead><tr><?php foreach (array_keys($notas['situacoes'][0]) as $k): ?><th><?= e($k === 'qtd' ? 'Pedidos' : $k) ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ($notas['situacoes'] as $l): ?><tr><?php foreach ($l as $k => $v): ?><td><?= $k === 'qtd' ? $n($v) : e($v === '' ? '(vazio)' : $v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
      <?php if (!empty($titulos['por_tipo'])): ?>
        <table class="tabela" style="margin-top:12px"><thead><tr><th>Contas (tipo)</th><th class="direita">Títulos</th><th class="direita">Valor</th></tr></thead><tbody>
          <?php foreach ($titulos['por_tipo'] as $t): ?><tr><td><?= e(['R' => 'R · a receber (vem)', 'P' => 'P · a pagar (não vem)'][strtoupper($t['tipo'])] ?? $t['tipo']) ?></td><td class="direita"><?= $n($t['qtd']) ?></td><td class="direita"><?= dinheiro((float) $t['valor']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </div>
  </div>
</details>
<?php endif; ?>
