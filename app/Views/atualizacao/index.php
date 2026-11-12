<?php /** Atualização do sistema. Variáveis: $versao, $pendentes, $historico, $log */
$total = count($pendentes['migrations']) + count($pendentes['seeds']);
?>
<div class="cabecalho-pagina">
  <p class="suave">Versão instalada: <strong><?= e($versao) ?></strong>. Os dados da equipe ficam no banco; uma versão nova só troca os arquivos e acrescenta o que for novo no banco.</p>
</div>

<?php if ($log): ?>
  <div class="aviso sucesso" style="white-space:pre-line"><?= e(implode("\n", $log)) ?></div>
<?php endif; ?>

<div class="grade-atualizacao">
  <section class="card">
    <span class="sobretitulo">PASSO 1 · ANTES DE ATUALIZAR</span>
    <h2>Baixar backup do banco</h2>
    <p>Gera um arquivo com <strong>todos os dados</strong> (clientes, contatos, metas, usuários). Guarde em local seguro: ele contém dados de clientes (LGPD).</p>
    <form method="post" action="<?= url('/sistema/atualizacao/backup') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit">Baixar backup (.sql.gz)</button></form>
    <p class="pequeno suave" style="margin-top:12px">Para restaurar: cPanel › phpMyAdmin › banco do sistema › Importar › envie este arquivo.</p>
  </section>

  <section class="card">
    <span class="sobretitulo">PASSO 2 · ENVIAR OS ARQUIVOS</span>
    <h2>Enviar o pacote de atualização</h2>
    <p>No Gerenciador de arquivos do cPanel, envie o <strong>pacote de atualização</strong> (one-atualizacao-….zip) para <code>public_html</code> e use <strong>Extrair</strong>, aceitando substituir os arquivos.</p>
    <p class="pequeno suave">O pacote de atualização não traz o <code>config.php</code>, os arquivos de referência nem as mídias: suas senhas e dados ficam como estão.</p>
  </section>

  <section class="card <?= $total ? 'destaque-atualizacao' : '' ?>">
    <span class="sobretitulo">PASSO 3 · APLICAR NO BANCO</span>
    <?php if ($total): ?>
      <h2><?= $total ?> atualização(ões) pendente(s)</h2>
      <ul class="lista-aula pequeno">
        <?php foreach ($pendentes['migrations'] as $m): ?><li>Estrutura: <?= e($m) ?></li><?php endforeach; ?>
        <?php foreach ($pendentes['seeds'] as $s): ?><li>Dados essenciais: <?= e($s) ?></li><?php endforeach; ?>
      </ul>
      <form method="post" action="<?= url('/sistema/atualizacao/aplicar') ?>" data-confirmar="Você já baixou o backup do banco? Aplicar a atualização agora?">
        <?= csrf_campo() ?><button class="botao lima" type="submit">Aplicar atualização</button>
      </form>
    <?php else: ?>
      <h2>Banco em dia ✓</h2>
      <p>Não há nada pendente. Se você acabou de enviar uma versão nova sem mudanças no banco, não precisa fazer mais nada.</p>
    <?php endif; ?>
    <p class="pequeno suave" style="margin-top:12px">Só acrescenta tabelas, campos e configurações novas. Nunca apaga dados e nunca mistura dados de demonstração.</p>
  </section>
</div>

<section class="card">
  <h2>Histórico do banco</h2>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Aplicado em</th><th>Item</th></tr></thead>
      <tbody><?php foreach ($historico as $h): ?><tr><td><?= data_br($h['aplicada_em'], true) ?></td><td><?= e($h['arquivo']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </div>
</section>
