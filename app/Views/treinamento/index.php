<?php
/**
 * Meu treinamento: Integração + trilha do(s) setor(es) da pessoa + áreas vizinhas, com progresso.
 * Variáveis: $blocos, $progresso, $feitos, $total, $proximo, $telasFeitas, $telasTotal, $semSetor, $concluidas
 */
use App\Core\Visao;

$pct = $total ? (int) round($feitos / $total * 100) : 0;
$pctTelas = $telasTotal ? (int) round($telasFeitas / $telasTotal * 100) : 0;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">TREINAMENTO</div><h1>Meu treinamento</h1>
    <p>Feito para o seu setor: primeiro a integração, depois o seu trabalho e, por fim, como funcionam as áreas com que você tem contato. Cada módulo termina com uma provinha.</p></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'meu']) ?>

<section class="tr-resumo" data-tr-resumo>
  <div class="card tr-numero">
    <span class="tr-anel" style="--p: <?= $pct ?>"><b><?= $pct ?>%</b></span>
    <div><strong><?= $feitos ?> de <?= $total ?></strong> módulos concluídos
      <?php if ($proximo): ?><a class="botao pequeno" href="<?= url('/treinamento/modulo/' . (int) $proximo['id']) ?>" data-tr-continuar>Continuar: <?= e($proximo['titulo']) ?> →</a>
      <?php elseif ($total): ?><span class="selo verde">Tudo concluído 🎓</span><?php endif; ?></div>
  </div>
  <div class="card tr-numero">
    <span class="tr-anel azul" style="--p: <?= $pctTelas ?>"><b><?= $pctTelas ?>%</b></span>
    <div><strong><?= $telasFeitas ?> de <?= $telasTotal ?></strong> telas do sistema aprendidas
      <small class="suave">Em cada tela, o One mostra o passo a passo. Esqueceu? Chame o One e clique em "O que dá pra fazer aqui?".</small></div>
  </div>
  <?php if ($concluidas): ?>
    <div class="card tr-selos"><strong>Seus selos</strong>
      <?php foreach ($concluidas as $c): ?><span class="tr-selo" title="Concluída em <?= e(data_br($c['em'])) ?>"><?= e($c['icone'] ?: '🎓') ?> <?= e($c['titulo']) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
</section>

<?php if ($semSetor): ?>
  <p class="aviso">Você ainda não está ligado(a) a um setor (função). Peça ao administrador para marcar a sua função em Pessoas e acessos: aí aparece a trilha do seu setor. Enquanto isso, veja a Integração e <a href="<?= url('/treinamento/trilhas') ?>">todas as trilhas</a>.</p>
<?php endif; ?>

<?php foreach ($blocos as $n => $b): $feitosBloco = count(array_filter($b['modulos'], fn($m) => !empty($progresso[(int) $m['id']]['aprovado_em']))); ?>
  <section class="tr-bloco" data-tr-bloco="<?= e($b['chave']) ?>">
    <header>
      <span class="tr-etapa"><?= $n + 1 ?></span>
      <div><h2><?= e($b['icone'] ?? '') ?> <?= e($b['titulo']) ?></h2><?php if ($b['descricao']): ?><p class="suave"><?= e($b['descricao']) ?></p><?php endif; ?></div>
      <span class="selo <?= $feitosBloco === count($b['modulos']) ? 'verde' : 'cinza' ?>"><?= $feitosBloco ?>/<?= count($b['modulos']) ?></span>
    </header>
    <ol class="tr-modulos">
      <?php foreach ($b['modulos'] as $k => $m): $p = $progresso[(int) $m['id']] ?? null; $ok = !empty($p['aprovado_em']); ?>
        <li class="<?= $ok ? 'feito' : ($p ? 'comecado' : '') ?>">
          <a href="<?= url('/treinamento/modulo/' . (int) $m['id']) ?>" data-tr-modulo="<?= (int) $m['id'] ?>">
            <span class="tr-check" aria-hidden="true"><?= $ok ? '✓' : (e($m['setor_icone'] ?? '') ?: $k + 1) ?></span>
            <span class="tr-texto"><strong><?= e($m['titulo']) ?></strong><?php if ($m['resumo']): ?><small><?= e($m['resumo']) ?></small><?php endif; ?></span>
            <span class="tr-info"><?= (int) $m['tempo_min'] ?> min<?php if ($p && $p['nota'] !== null): ?> · nota <?= (int) $p['nota'] ?>%<?php endif; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endforeach; ?>
