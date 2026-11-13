<?php
/**
 * Meu mês. Variáveis: $mes, $aba, $dia, $eu (indicadores individuais), $equipe (ou null), $tarefas, $podeEquipe, $participacao
 */
use App\Core\Auth;
use App\Core\Visao;
use App\Services\MetaService;

$meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$nomeMes = fn(string $m) => ucfirst($meses[(int) substr($m, 5, 2)]) . ' de ' . substr($m, 0, 4);
$anterior = date('Y-m', strtotime("$mes-01 -1 month"));
$seguinte = date('Y-m', strtotime("$mes-01 +1 month"));
$pct = fn(float $v) => number_format($v, 1, ',', '.') . '%';
$ou = fn($v) => $v === null ? '—' : dinheiro($v);
$link = fn(array $p) => url('/meu-mes') . '?' . e(http_build_query($p + ['mes' => $mes]));
$nome = explode(' ', Auth::usuario()['nome'])[0];
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">SEU ESPAÇO DE TRABALHO</div>
    <h1>Meu mês <span class="suave-titulo">· <?= e($nome) ?></span></h1>
    <p>Suas vendas, seus melhores clientes, quem está parado e a sua rotina até a meta.</p>
  </div>
  <div class="acoes seletor-mes">
    <a class="botao-icone" href="<?= $link(['mes' => $anterior, 'aba' => $aba]) ?>" aria-label="Mês anterior">←</a>
    <form method="get" action="<?= url('/meu-mes') ?>"><input type="hidden" name="aba" value="<?= e($aba) ?>"><input type="month" name="mes" value="<?= e($mes) ?>" aria-label="Mês de referência" onchange="this.form.submit()"></form>
    <a class="botao-icone" href="<?= $link(['mes' => $seguinte, 'aba' => $aba]) ?>" aria-label="Próximo mês">→</a>
    <?php if ($mes !== date('Y-m')): ?><a class="botao contorno pequeno" href="<?= url('/meu-mes') ?>">Este mês</a><?php endif; ?>
  </div>
</div>

<?php // A meta da equipe fica em Equipe de vendas; aqui só um atalho ?>
<?php if ($equipe && \App\Core\Auth::pode('equipe.ver')): ?>
  <a class="mm-equipe-atalho" href="<?= url('/equipe?mes=' . e($mes)) ?>" data-atalho-equipe>
    <span>Equipe: <strong><?= $pct($equipe['percentual']) ?></strong> da meta de <?= e($nomeMes($mes)) ?></span>
    <span class="mm-equipe-barra"><i style="width: <?= min(100, $equipe['percentual']) ?>%"></i></span>
    <span>Ver equipe de vendas →</span>
  </a>
<?php endif; ?>

<nav class="abas" aria-label="Meu mês">
  <?php foreach (['painel' => 'Visão do mês', 'vendas' => 'Minhas vendas', 'clientes' => 'Meus clientes', 'calendario' => 'Calendário', 'tarefas' => 'Tarefas'] as $chave => $rotulo):
      $pendentes = $chave === 'tarefas' ? count(array_filter($tarefas, fn($t) => !$t['concluida_em'])) : 0; ?>
    <a href="<?= $link(['aba' => $chave]) ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= $rotulo ?><?= $pendentes ? " <b class=\"contador\">$pendentes</b>" : '' ?></a>
  <?php endforeach; ?>
</nav>

<?= Visao::renderizar('meu_mes/_' . $aba, compact('mes', 'dia', 'eu', 'equipe', 'tarefas', 'participacao', 'faturadoSistema', 'nomeMes', 'pct', 'ou', 'link', 'clientes')) ?>
