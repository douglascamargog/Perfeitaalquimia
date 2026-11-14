<?php
/** Acompanhamento › Cobrança. Variáveis: $linhas, $antes, $visoes, $de, $ate, $periodo */
use App\Core\Visao;
use App\Services\TelefoniaService as T;

$tot = fn(string $k) => array_sum(array_column($linhas, $k));
$d = fn($a, $b, bool $inverso = false) => Visao::renderizar('acompanhamento/_delta', ['a' => $a, 'b' => $b, 'inverso' => $inverso]);
$totA = fn(string $k) => array_sum(array_column($antes, $k));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">GESTÃO · ACOMPANHAMENTO</div><h1>Acompanhamento da Cobrança</h1>
    <p>Quanto foi recuperado, tentativas, promessas, títulos em atraso e ligações de quem cuida da Cobrança. <?= data_br($de) ?> a <?= data_br($ate) ?>.</p></div>
</div>
<?= Visao::renderizar('acompanhamento/_visoes', ['visoes' => $visoes, 'atual' => 'cobranca', 'periodo' => $periodo, 'de' => $de, 'ate' => $ate]) ?>
<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento', 'periodo' => $periodo, 'de' => $de, 'ate' => $ate, 'extra' => ['visao' => 'cobranca']]) ?>

<div class="ac-cards">
  <div class="card"><span>Recuperado</span><strong data-total-recuperado><?= dinheiro($tot('recuperado')) ?></strong><small><?= $tot('titulos_pagos') ?> título(s) pagos <?= $d($tot('recuperado'), $totA('recuperado')) ?></small></div>
  <div class="card"><span>Tentativas</span><strong><?= $tot('tentativas') ?></strong><small><?= $tot('com_sucesso') ?> falaram com o cliente <?= $d($tot('tentativas'), $totA('tentativas')) ?></small></div>
  <div class="card"><span>Promessas</span><strong><?= $tot('promessas') ?></strong><small><?= $tot('cumprida') ?> cumpridas · <?= $tot('quebrada') ?> quebradas</small></div>
  <div class="card"><span>Em atraso agora</span><strong><?= dinheiro($tot('em_aberto')) ?></strong><small><?= $tot('casos') ?> caso(s) abertos</small></div>
  <div class="card"><span>Ligações</span><strong><?= $tot('ligacoes') ?></strong><small><?= T::duracao($tot('falado')) ?> falados</small></div>
</div>

<section class="card sem-espaco">
  <div class="tabela-rolagem"><table class="tabela ac-tabela">
    <thead><tr><th>Pessoa</th><th class="direita">Recuperado</th><th class="direita">Tentativas</th><th class="direita">Promessas</th>
      <th class="direita">Casos abertos</th><th class="direita">Em atraso</th><th class="direita">Atraso médio</th><th class="direita">Ligações</th></tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): $a = $antes[$l['id']] ?? []; ?>
      <tr data-pessoa="<?= (int) $l['id'] ?>">
        <td><a href="<?= url('/acompanhamento/cobranca/' . (int) $l['id']) ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><strong><?= e($l['nome']) ?></strong></a></td>
        <td class="direita" data-recuperado><?= dinheiro($l['recuperado']) ?> <?= $d($l['recuperado'], $a['recuperado'] ?? 0) ?><br><small class="suave"><?= $l['titulos_pagos'] ?> título(s)</small></td>
        <td class="direita" data-tentativas><?= $l['tentativas'] ?><br><small class="suave"><?= $l['com_sucesso'] ?> com sucesso</small></td>
        <td class="direita" data-promessas><?= $l['promessas'] ?><br><small class="suave"><?= $l['cumprida'] ?> cumpridas · <span class="<?= $l['quebrada'] ? 'ac-atraso' : '' ?>"><?= $l['quebrada'] ?> quebradas</span></small></td>
        <td class="direita" data-casos><?= $l['casos'] ?></td>
        <td class="direita"><?= dinheiro($l['em_aberto']) ?></td>
        <td class="direita"><?= $l['casos'] ? (int) round($l['atraso_medio']) . ' dias' : '—' ?></td>
        <td class="direita"><?= $l['ligacoes'] ?><br><small class="suave"><?= T::duracao($l['falado']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$linhas): ?><tr><td colspan="8" class="suave">Ninguém na Cobrança ainda. Coloque a pessoa na função Cobrança (Administração › Usuários).</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<p class="pequeno suave">Recuperado: títulos pagos no período nos casos da pessoa. Promessa cumprida: algum título do caso foi pago até a data prometida; quebrada: a data passou sem pagamento.</p>
