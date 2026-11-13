<?php
/** Funil de cobrança (kanban). Variáveis: $filtros, $opcoes, $resumo, $colunas */
use App\Core\Auth;
use App\Core\Visao;

$podeMover = Auth::pode('cobranca.gerenciar');
?>
<?= Visao::renderizar('cobranca/_cabecalho', ['aba' => 'funil', 'filtros' => $filtros, 'opcoes' => $opcoes, 'resumo' => $resumo]) ?>

<div class="funil funil-cobranca" data-funil data-url-etapa="/cobranca/{id}/etapa">
  <?php foreach ($colunas as $col): $et = $col['etapa']; ?>
    <section class="funil-coluna <?= $col['encerra'] ? 'encerra' : '' ?>" data-etapa="<?= (int) $et['id'] ?>" style="--cor: <?= e($et['cor']) ?>">
      <header>
        <span class="funil-ponto"></span><h3><?= e($et['nome']) ?></h3><b data-contagem><?= number_format($col['total'], 0, ',', '.') ?></b>
      </header>
      <div class="cob-coluna-valor"><?= dinheiro($col['valor']) ?><?= $col['encerra'] ? ' · últimos 30 dias' : '' ?></div>
      <div class="funil-cards" data-soltar>
        <?php foreach ($col['cards'] as $c): $dias = (int) $c['dias_atraso']; ?>
          <article class="funil-card cob-card" <?= $podeMover ? 'draggable="true"' : '' ?> data-cliente="<?= (int) $c['id'] ?>">
            <button type="button" class="nome-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><?= \App\Services\NotificacaoService::marca((int) ($c['cliente_id'] ?? 0)) ?>
            <small><?= e($c['cidade']) ?> / <?= e($c['uf']) ?></small>
            <div class="cob-card-valor">
              <strong><?= dinheiro((float) $c['valor_total']) ?></strong>
              <?php if ($c['vencimento_mais_antigo'] && $c['situacao'] === 'aberta'): ?>
                <span class="selo <?= $dias > 90 ? 'vermelho' : ($dias > 30 ? 'laranja' : 'cinza') ?>"><?= $dias ?> dia<?= $dias === 1 ? '' : 's' ?></span>
              <?php endif; ?>
            </div>
            <?php if ($c['promessa_em'] && $c['etapa_tipo'] === 'promessa'): ?>
              <small class="cob-promessa">Prometido para <?= data_br($c['promessa_em']) ?></small>
            <?php elseif ((int) $c['tentativas_sem_sucesso'] > 0 && $c['situacao'] === 'aberta'): ?>
              <small class="suave"><?= (int) $c['tentativas_sem_sucesso'] ?> tentativa(s) sem sucesso</small>
            <?php endif; ?>
            <div class="funil-card-rodape"><span class="pequeno suave"><?= e(explode(' ', (string) $c['responsavel'])[0]) ?></span></div>
          </article>
        <?php endforeach; ?>
        <?php if ($col['total'] > count($col['cards'])): ?>
          <a class="funil-mais" href="<?= url('/cobranca/lista') ?>?<?= e(http_build_query(array_filter($filtros) + ['etapa' => $et['id']] + ($col['encerra'] ? ['situacao' => 'encerrada'] : []))) ?>">+ <?= number_format($col['total'] - count($col['cards']), 0, ',', '.') ?> na lista</a>
        <?php endif; ?>
        <?php if (!$col['total']): ?><p class="funil-vazio">Nenhum caso</p><?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?= Visao::renderizar('carteira/_drawer', ['url' => '/cobranca/{id}/caso', 'rotulo' => 'Caso de cobrança']) ?>
