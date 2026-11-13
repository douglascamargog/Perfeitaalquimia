<?php
/** Funil (kanban). Variáveis: $filtros, $opcoes, $colunas */
use App\Core\Visao;
use App\Services\Normalizar;
?>
<?= Visao::renderizar('carteira/_cabecalho', ['aba' => 'funil', 'filtros' => $filtros]) ?>
<?= Visao::renderizar('carteira/_filtros', ['filtros' => $filtros, 'opcoes' => $opcoes, 'acao' => '/carteira/funil']) ?>

<div class="funil" data-funil data-csrf="<?= e(\App\Core\Csrf::token()) ?>">
  <?php foreach ($colunas as $col): $et = $col['etapa']; ?>
    <section class="funil-coluna" data-etapa="<?= (int) $et['id'] ?>" style="--cor: <?= e($et['cor']) ?>">
      <header><span class="funil-ponto"></span><h3><?= e($et['nome']) ?></h3><b data-contagem><?= number_format($col['total'], 0, ',', '.') ?></b></header>
      <div class="funil-cards" data-soltar>
        <?php foreach ($col['cards'] as $c): ?>
          <article class="funil-card" draggable="true" data-cliente="<?= (int) $c['id'] ?>">
            <button type="button" class="nome-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><?= \App\Services\NotificacaoService::marca((int) $c['id']) ?>
            <small><?= e($c['cidade']) ?> / <?= e($c['uf']) ?> · <?= e($c['segmento']) ?></small>
            <div class="funil-card-rodape">
              <span class="selo prioridade-<?= e(Normalizar::semAcento($c['prioridade'])) ?>"><?= e($c['prioridade']) ?></span>
              <span class="pequeno suave"><?= e(explode(' ', (string) $c['responsavel'])[0]) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if ($col['total'] > count($col['cards'])): ?>
          <a class="funil-mais" href="<?= url('/carteira/lista') ?>?<?= e(http_build_query(array_filter($filtros) + ['etapa' => $et['id']])) ?>">+ <?= number_format($col['total'] - count($col['cards']), 0, ',', '.') ?> na lista</a>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?= Visao::renderizar('carteira/_drawer') ?>
