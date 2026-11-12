<?php
/** Fila de reativação. Variáveis: $resultado, $dias, $aguardando, $vendedores */
use App\Core\Auth;
use App\Core\Visao;

$r = $resultado;
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">FUNÇÃO PROSPECÇÃO E REATIVAÇÃO</div>
    <h1>Fila de reativação</h1>
    <p>Clientes sem compra há mais de <strong><?= (int) $dias ?> dias</strong> (configurável). Reativou? Devolva para um vendedor.</p>
  </div>
  <?php if (Auth::pode('carteira.ver_todas')): ?>
    <form method="post" action="<?= url('/reativacao/processar') ?>" class="acoes">
      <?= csrf_campo() ?>
      <button class="botao lima" type="submit">Processar agora<?= $aguardando ? " ($aguardando)" : '' ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if ($aguardando): ?>
  <div class="aviso aviso"><?= (int) $aguardando ?> cliente(s) já passaram do prazo e entram na fila na próxima execução automática (ou clique em "Processar agora").</div>
<?php endif; ?>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Cliente</th><th>Cidade</th><th>Última compra</th><th>Dias sem compra</th><th>Responsável</th><?= $vendedores ? '<th>Devolver para</th>' : '' ?></tr></thead>
      <tbody>
      <?php foreach ($r['itens'] as $c): $semCompra = (int) ((time() - strtotime($c['ultima_compra_em'])) / 86400); ?>
        <tr>
          <td><button type="button" class="nome-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><small class="suave"><?= e($c['segmento']) ?></small></td>
          <td><?= e($c['cidade']) ?> / <?= e($c['uf']) ?></td>
          <td><?= data_br($c['ultima_compra_em']) ?></td>
          <td><span class="selo <?= $semCompra > 180 ? 'vermelho' : '' ?>"><?= $semCompra ?> dias</span></td>
          <td class="pequeno"><?= e($c['responsavel']) ?></td>
          <?php if ($vendedores): ?>
            <td>
              <form method="post" action="<?= url('/reativacao/' . (int) $c['id'] . '/devolver') ?>" class="devolver" data-confirmar="Devolver este cliente para o vendedor escolhido?">
                <?= csrf_campo() ?>
                <select name="vendedor_id" aria-label="Vendedor" required>
                  <option value="">Escolha…</option>
                  <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['nome']) ?></option><?php endforeach; ?>
                </select>
                <button class="botao contorno pequeno" type="submit">Devolver</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$r['itens']): ?><tr><td colspan="6" class="vazio">Nenhum cliente na fila de reativação.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra"><span><?= number_format($r['total'], 0, ',', '.') ?> cliente(s) na fila</span>
    <?php if ($r['paginas'] > 1): ?><nav class="paginacao">
      <?php for ($p = 1; $p <= $r['paginas']; $p++): ?><a class="botao <?= $p === $r['pagina'] ? '' : 'contorno' ?> pequeno" href="<?= url('/reativacao') ?>?pagina=<?= $p ?>"><?= $p ?></a><?php endfor; ?>
    </nav><?php endif; ?>
  </div>
</section>

<?= Visao::renderizar('carteira/_drawer') ?>
