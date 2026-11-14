<?php
/** Gestão › Acompanhamento. Variáveis: $linhas, $de, $ate, $periodo, $setor, $setores, $modo */
use App\Core\Visao;
use App\Services\TelefoniaService as T;

$tot = fn(string $k) => array_sum(array_column($linhas, $k));
$modos = ['simulado' => 'Demonstração: ligações e gravações simuladas', 'manual' => 'Ligue Talk em modo manual (ligações registradas com 1 clique)', 'api' => 'Ligue Talk conectado'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">GESTÃO · ACOMPANHAMENTO</div><h1>Acompanhamento da equipe</h1>
    <p>Ligações e gravações do Ligue Talk, contatos registrados, reativações e pedidos de cada vendedora. <?= data_br($de) ?> a <?= data_br($ate) ?>.</p></div>
  <div class="acoes"><span class="selo <?= $modo === 'api' ? 'lima' : 'cinza' ?>"><?= e($modos[$modo] ?? $modo) ?></span>
    <?php if (\App\Core\Auth::pode('integracao.gerenciar')): ?>
      <form method="post" action="<?= url('/telefonia/testar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Testar Ligue Talk</button></form>
      <?php if ($modo === 'api'): ?><form method="post" action="<?= url('/telefonia/sincronizar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Buscar ligações agora</button></form><?php endif; ?>
    <?php endif; ?></div>
</div>

<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento', 'periodo' => $periodo, 'de' => $de, 'ate' => $ate, 'extra' => array_filter(['setor' => $setor])]) ?>

<?php if (count($setores) > 1): ?>
<div class="chips ac-setores">
  <a class="chip <?= !$setor ? 'ativo' : '' ?>" href="<?= url('/acompanhamento') ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>">Todos os setores</a>
  <?php foreach ($setores as $s): ?><a class="chip <?= (int) $setor === (int) $s['id'] ? 'ativo' : '' ?>" href="<?= url('/acompanhamento') ?>?setor=<?= (int) $s['id'] ?>&periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><?= e($s['nome']) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="ac-cards">
  <div class="card"><span>Ligações</span><strong data-total-ligacoes><?= $tot('ligacoes') ?></strong><small><?= $tot('ligacoes') ? round($tot('atendidas') / $tot('ligacoes') * 100) : 0 ?>% atendidas</small></div>
  <div class="card"><span>Tempo falado</span><strong><?= T::duracao($tot('falado')) ?></strong><small><?= $tot('gravacoes') ?> gravação(ões)</small></div>
  <div class="card"><span>Contatos</span><strong><?= $tot('contatos') ?></strong><small><?= $tot('clientes_contatados') ?> clientes</small></div>
  <div class="card"><span>Reativações</span><strong><?= $tot('reativacoes') ?></strong><small>voltaram a comprar</small></div>
  <div class="card"><span>Pedidos</span><strong><?= $tot('pedidos') ?></strong><small><?= dinheiro((float) array_sum(array_column($linhas, 'valor'))) ?></small></div>
</div>

<section class="card sem-espaco">
  <div class="tabela-rolagem"><table class="tabela ac-tabela">
    <thead><tr><th>Vendedora</th><th class="direita">Ligações</th><th class="direita">Atendidas</th><th class="direita">Tempo falado</th><th class="direita">Média</th>
      <th class="direita">Contatos</th><th class="direita">Reativações</th><th class="direita">Pedidos</th><th class="direita">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): ?>
      <tr data-pessoa="<?= (int) $l['id'] ?>">
        <td><a href="<?= url('/acompanhamento/pessoa/' . (int) $l['id']) ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><strong><?= e($l['nome']) ?></strong></a></td>
        <td class="direita" data-ligacoes><?= $l['ligacoes'] ?></td>
        <td class="direita"><?= $l['atendidas'] ?> <small class="suave">(<?= $l['taxa'] ?>%)</small></td>
        <td class="direita"><?= T::duracao($l['falado']) ?></td>
        <td class="direita"><?= $l['medio'] ? T::duracao($l['medio']) : '—' ?></td>
        <td class="direita" data-contatos><?= $l['contatos'] ?></td>
        <td class="direita" data-reativacoes><?= $l['reativacoes'] ?></td>
        <td class="direita"><?= $l['pedidos'] ?></td>
        <td class="direita"><?= dinheiro($l['valor']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$linhas): ?><tr><td colspan="9" class="suave">Ninguém para acompanhar neste setor.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<p class="pequeno suave">Reativação: pedido faturado no período de um cliente que estava sem comprar há mais tempo que o limite de inatividade (Configurações). Clique na vendedora para ver as ligações, ouvir as gravações e os contatos.</p>
