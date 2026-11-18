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
  <li class="<?= $imp && !empty($imp['importado_em']) ? 'feito' : ($lido && !empty($imp['etapa']) ? 'atual' : '') ?>"><b>4</b> Importar</li>
</ol>

<?php if (!$imp || $imp['situacao'] === 'erro' && !$imp['posicao'] || !empty($imp['importado_em'])): ?>
<section class="card n10-envio" data-n10-envio>
  <h2><?= !empty($imp['importado_em']) ? 'Enviar um backup novo (ex.: o do dia da virada)' : 'Enviar o backup' ?></h2>
  <?php if (!empty($imp['importado_em'])): ?><p class="pequeno suave">Importar de novo não duplica: o One completa o que faltou, atualiza os pedidos que mudaram no Nota10
    (ganharam NF, foram despachados ou cancelados) e completa os cadastros sem apagar o que a equipe preencheu.</p><?php endif; ?>
  <p>No computador do Nota10, exporte o banco <code>nota10</code> inteiro (HeidiSQL: botão direito no banco › Exportar banco de dados como SQL;
    ou <code>mysqldump</code>). Vale <strong>.sql</strong> ou <strong>.txt</strong>, e pode compactar em <strong>.zip</strong> para ficar bem menor.
    Arquivo gerado pelo PowerShell (UTF-16, acentos trocados) é corrigido sozinho.</p>
  <form method="post" action="<?= url('/sistema/nota10/enviar') ?>" enctype="multipart/form-data" class="n10-form"><?= csrf_campo() ?>
    <input type="file" name="backup" accept=".sql,.txt,.zip,.gz" required aria-label="Arquivo do backup">
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
  <input type="hidden" name="mapa_json" value="" data-n10-mapa-json><?php /* o navegador junta as escolhas aqui (um campo só: a hospedagem barra formulário com centenas de campos) */ ?>
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
    <p class="pequeno suave">"➕ Criar funcionário no One" já vem marcado para quem ainda vende (ativo no Nota10 ou com venda nos últimos 90 dias) e não existe no One:
      na importação o One cria o funcionário com o código do Nota10, sem acesso até você definir o e-mail e a senha em Administração › Usuários.</p>
    <table class="tabela"><thead><tr><th>Vendedor no Nota10</th><th class="direita">Clientes</th><th class="direita">Pedidos</th><th>Última venda</th><th>Pessoa no One</th></tr></thead><tbody>
      <?php foreach ($ligar['vendedor'] as $v): ?>
        <tr data-vendedor="<?= e($v['codigo']) ?>"><td><strong><?= e($v['nome']) ?></strong> <small class="suave">código <?= e($v['codigo']) ?><?= empty($v['ativo']) ? ' · inativo no Nota10' : '' ?></small></td>
          <td class="direita"><?= $n($v['clientes']) ?></td><td class="direita"><?= $n($v['pedidos']) ?></td><td class="pequeno"><?= !empty($v['ultima']) ? data_br($v['ultima']) : '—' ?></td>
          <td><select name="mapa[vendedor][<?= e($v['codigo']) ?>]" aria-label="Pessoa para <?= e($v['nome']) ?>">
            <?php // "Ninguém" primeiro: é o que fica marcado quando não há escolha ?>
            <?= preg_replace('/^(<option value="">[^<]*<\/option>)/', '$1<option value="novo"' . ($v['destino'] === 'novo' ? ' selected' : '') . '>➕ Criar funcionário no One</option>',
                $opcoes($usuarios, $v['destino'], 'Ninguém (fila de reativação)')) ?></select></td></tr>
      <?php endforeach; ?>
      <?php if (!$ligar['vendedor']): ?><tr><td colspan="5" class="suave">O backup não tem vendedores nos clientes nem nos pedidos.</td></tr><?php endif; ?>
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

  <?php if ($ligar['grupo']): ?>
  <section class="card n10-bloco" data-n10-grupos>
    <h3>Grupos de produto</h3>
    <p class="pequeno suave">Insumos, rótulos, embalagens e material de uso viram matéria-prima (Produção), não produto do catálogo.</p>
    <table class="tabela"><thead><tr><th>Grupo no Nota10</th><th class="direita">Produtos</th><th>No One vira</th></tr></thead><tbody>
      <?php foreach ($ligar['grupo'] as $g): ?><tr><td><?= e($g['nome']) ?></td><td class="direita"><?= $n($g['qtd']) ?></td>
        <td><select name="mapa[grupo][<?= e($g['codigo']) ?>]" aria-label="Grupo <?= e($g['nome']) ?>">
          <?php foreach (Conferencia::DESTINOS_GRUPO as $k => $r): ?><option value="<?= $k ?>" <?= $g['destino'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>

  <div class="n10-salvar"><button class="botao" type="submit" data-n10-salvar>Guardar as ligações</button>
    <span class="pequeno suave">A importação (passo 4, abaixo) usa estas escolhas. Guardar não grava nada nos clientes e pedidos.</span></div>
</form>

<?php
  $rel = json_decode((string) ($imp['relatorio'] ?? ''), true) ?: [];
  $rotulos = ['funcionarios_criados' => 'Funcionários criados', 'clientes' => 'Clientes', 'leads' => 'Leads (prospects)', 'sem_responsavel_fila' => 'Sem responsável (fila de Prospecção e Reativação)',
    'clientes_juntados_cnpj' => 'Cadastros juntados pelo mesmo CNPJ', 'fornecedores' => 'Fornecedores', 'transportadoras' => 'Transportadoras',
    'produtos_ligados' => 'Produtos ligados aos do One', 'produtos_novos_inativos' => 'Produtos novos (inativos, fora do catálogo)', 'materias_primas' => 'Matérias-primas', 'precos' => 'Preços (tabela Lojista)',
    'pedidos_faturado' => 'Pedidos faturados', 'pedidos_historico' => 'Pedidos só de histórico', 'pedidos_cancelado' => 'Pedidos cancelados', 'pedidos_outra_empresa' => 'Pedidos de outras empresas do grupo',
    'orcamentos_historico' => 'Orçamentos (histórico)', 'gemeos_juntados' => 'Bonificações juntadas ao pedido principal', 'saidas_ligadas_a_pedido' => 'NFs de "Outras saídas" ligadas a pedidos',
    'itens' => 'Itens de pedido', 'titulos_receber' => 'Títulos a receber', 'titulos_a_pagar_guardados' => 'Contas a pagar (guardadas)', 'compras' => 'Compras de fornecedores',
    'followups' => 'Follow-ups na linha do tempo', 'eventos_nfe' => 'Eventos de NF-e', 'clientes_boleto_bloqueado' => 'Clientes com boleto bloqueado (título vencido)',
    'entrega_para_revisar' => 'Endereços de entrega para revisar', 'possiveis_duplicados' => 'Possíveis duplicados (não somam)', 'alerta_cancelado_com_nf' => 'Pedidos cancelados com NF autorizada (ficaram faturados)',
    'notas_sem_cliente' => 'Notas sem cliente (guardadas)', 'itens_sem_nota_guardados' => 'Itens sem nota (guardados)', 'ajustes_estoque_guardados' => 'Ajustes manuais de estoque (guardados)'];
  $emAndamento = !empty($imp['etapa']) && $imp['etapa'] !== 'fim';
?>
<section class="card n10-bloco" id="importar" data-n10-importar data-url="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/importar-parte') ?>" data-andamento="<?= $emAndamento ? '1' : '0' ?>">
  <h2>4 · Importar para o One</h2>
  <?php if ($imp['importado_em']): ?>
    <div class="aviso sucesso" data-n10-importado>Importação concluída em <?= data_br($imp['importado_em'], true) ?>. Os dados do Nota10 continuam guardados no One (tabelas n10_) para consulta.
      <?php if ($n10Soltos = (int) \App\Core\Banco::valor("SELECT COUNT(*) FROM produtos WHERE slug LIKE 'n10-%' AND juntado_a_id IS NULL")): ?>
        <br><?= $n10Soltos ?> produto(s) vieram só do Nota10: <a href="<?= url('/produtos/nota10') ?>">ligue cada um ao produto do One</a>.<?php endif; ?></div>
    <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/importar') ?>" class="n10-form" data-n10-reimportar><?= csrf_campo() ?>
      <span class="pequeno">Importar de novo este backup (não duplica: completa o que faltou, como os casos de cobrança):</span>
      <input type="text" name="confirmar" placeholder="digite IMPORTAR" autocomplete="off" required aria-label="Digite IMPORTAR">
      <button class="botao contorno pequeno" type="submit">Importar de novo</button>
    </form>
  <?php elseif ($emAndamento): ?>
    <p>Importando… deixe esta página aberta. Se fechar, é só voltar e clicar em continuar: ela segue de onde parou.</p>
    <div class="n10-barra"><span data-n10-imp-pct style="width:0%"></span></div>
    <p class="pequeno suave" data-n10-imp-status><?= e(\App\Services\Nota10\Importador::ETAPAS[$imp['etapa']] ?? '') ?></p>
    <button class="botao" type="button" data-n10-imp-continuar>Continuar a importação</button>
  <?php else:
      $foraNota10 = (int) \App\Core\Banco::valor('SELECT COUNT(*) FROM clientes WHERE nota10_numero IS NULL AND excluido_em IS NULL AND tipo <> \'consumidor\''); ?>
    <p>Grava no One: clientes e leads, fornecedores, produtos e matérias-primas, preços, pedidos e itens (com as bonificações juntadas), contas a receber,
      follow-up e compras. Pode rodar de novo sem duplicar. Antes de começar, o One faz um backup completo do banco (pasta storage/backups).</p>
    <?php if ($foraNota10): ?><p class="aviso alerta" data-n10-fora>O One já tem <?= $n($foraNota10) ?> cliente(s) que não vieram do Nota10. Se forem de teste, use
      <a href="<?= url('/sistema/zerar') ?>">Administração › Zerar dados</a> antes de importar.</p><?php endif; ?>
    <form method="post" action="<?= url('/sistema/nota10/' . (int) $imp['id'] . '/importar') ?>" class="n10-form" data-n10-form-importar><?= csrf_campo() ?>
      <label>Para confirmar, digite <strong>IMPORTAR</strong> <input type="text" name="confirmar" autocomplete="off" required></label>
      <button class="botao" type="submit">Importar agora</button>
    </form>
    <p class="pequeno suave">Confira e guarde as ligações acima antes: vendedores, tipos de cadastro, grupos de produto e formas de pagamento.</p>
  <?php endif; ?>
  <?php if ($rel && count($rel) > 1): $agora = (array) ($rel['no_one'] ?? []); ?>
    <table class="tabela" data-n10-relatorio style="margin-top:12px"><thead><tr><th>Vindo do Nota10, no One agora</th><th class="direita">Quantidade</th></tr></thead><tbody>
      <?php foreach ($agora as $k => [$r, $q]): ?><tr data-agora="<?= e($k) ?>"><td><?= e($r) ?></td><td class="direita"><?= $n($q) ?></td></tr><?php endforeach; ?>
      <?php if (!$agora): foreach ($rotulos as $k => $r): if (!isset($rel[$k])) continue; ?><tr data-rel="<?= e($k) ?>"><td><?= e($r) ?></td><td class="direita"><?= $n($rel[$k]) ?></td></tr><?php endforeach; endif; ?>
      <?php if (isset($rel['faturado_total'])): ?><tr><td><strong>Faturamento importado (Perfeita Alquimia)</strong></td><td class="direita"><strong><?= dinheiro((float) $rel['faturado_total']) ?></strong></td></tr><?php endif; ?>
      <?php foreach ((array) ($rel['faturado_por_ano'] ?? []) as $ano => $v): ?><tr><td class="pequeno">· faturado em <?= (int) $ano ?></td><td class="direita pequeno"><?= dinheiro((float) $v) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php $avisos = array_intersect_key($rel, array_flip(['gemeos_juntados', 'saidas_ligadas_a_pedido', 'alerta_cancelado_com_nf', 'possiveis_duplicados', 'notas_sem_cliente',
        'itens_sem_nota_guardados', 'ajustes_estoque_guardados', 'funcionarios_criados', 'clientes_juntados_cnpj'])); ?>
    <?php if ($avisos && $agora): ?><details style="margin-top:8px"><summary class="pequeno">O que a última rodada fez</summary>
      <ul class="pequeno"><?php foreach ($avisos as $k => $q): ?><li><?= e($rotulos[$k] ?? $k) ?>: <?= $n($q) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
  <?php endif; ?>
</section>

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
