<?php
/** Agenda de retornos. Variáveis: $grupos, $verTodas, $pessoa, $vendedores */
use App\Core\Visao;
use App\Services\Normalizar;

$secoes = ['atrasadas' => ['Atrasados', 'vermelho'], 'hoje' => ['Para hoje', 'lima'], 'futuras' => ['Próximos', '']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ACOMPANHAMENTO</div><h1>Agenda de retornos</h1><p>Próximos contatos combinados com os clientes.</p></div>
  <?php if ($verTodas): ?>
    <form method="get" action="<?= url('/agenda') ?>" class="acoes">
      <select name="pessoa" aria-label="Pessoa" onchange="this.form.submit()">
        <option value="">Todas as pessoas</option>
        <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $pessoa === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?>
      </select>
      <noscript><button class="botao pequeno">Filtrar</button></noscript>
    </form>
  <?php endif; ?>
</div>

<div class="agenda-resumo">
  <?php foreach ($secoes as $chave => [$nome, $cor]): ?>
    <div class="card"><span><?= e($nome) ?></span><strong class="<?= $cor ?>"><?= count($grupos[$chave]) ?></strong></div>
  <?php endforeach; ?>
</div>

<?php foreach ($secoes as $chave => [$nome, $cor]): ?>
  <section class="card">
    <h2><?= e($nome) ?> <span class="selo <?= $cor ?>"><?= count($grupos[$chave]) ?></span></h2>
    <?php if (!$grupos[$chave]): ?><p class="suave">Nada por aqui.</p><?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Data</th><th>Estabelecimento</th><th>Tarefa</th><th>Telefone</th><?= $verTodas ? '<th>Responsável</th>' : '' ?><th></th></tr></thead>
          <tbody>
          <?php foreach ($grupos[$chave] as $t): ?>
            <tr>
              <td><strong><?= data_br($t['vence_em']) ?></strong></td>
              <td><?php if ($t['cliente_id']): ?><button type="button" class="nome-cliente" data-abrir-cliente="<?= (int) $t['cliente_id'] ?>"><?= e($t['razao_social']) ?></button><small class="suave"><?= e($t['cidade']) ?> / <?= e($t['uf']) ?></small><?php endif; ?></td>
              <td><?= e($t['titulo']) ?></td>
              <td class="pequeno"><?= e(Normalizar::telefoneFormatado($t['whatsapp'] ?: $t['telefone'])) ?></td>
              <?= $verTodas ? '<td class="pequeno">' . e($t['usuario_nome']) . '</td>' : '' ?>
              <td><form method="post" action="<?= url('/agenda/' . (int) $t['id'] . '/concluir') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Concluir</button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?= Visao::renderizar('carteira/_drawer') ?>
