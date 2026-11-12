<?php
/**
 * Mural do mês: calendário seg → dom com os posts do Instagram.
 * Variáveis: $mes, $semanas, $porDia, $filtros, $resumo, $campanhas, $datas, $meta, $etapas, $listaCampanhas
 */
use App\Core\Auth;
use App\Core\Visao;
use App\Models\Post;

$hoje = date('Y-m-d');
$diasSemana = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
$podeEditar = Auth::pode('marketing.editar');
$icones = asset('img/icones.svg');
$acoes = $podeEditar ? '<button class="botao" type="button" data-novo-post="' . e($hoje >= "$mes-01" && str_starts_with($hoje, $mes) ? $hoje : "$mes-01") . '"><svg class="icone"><use href="' . $icones . '#mais"/></svg>Novo post</button>' : '';
$fracas = 0;
foreach ($semanas as $semana) {
    $qtd = 0;
    foreach ($semana as $d) {
        $qtd += count($porDia[$d] ?? []);
    }
    if ($meta && $qtd < $meta && end($semana) >= $hoje) {
        $fracas++;
    }
}
$sel = fn(string $campo, $valor) => (string) $filtros[$campo] === (string) $valor ? 'selected' : '';
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'mural', 'mes' => $mes, 'titulo' => 'Mural de posts',
    'subtitulo' => 'Planeje o Instagram do mês. Arraste um post para mudar o dia; clique para abrir.', 'acoes' => $acoes]) ?>

<div class="mk-numeros">
  <div class="mk-numero escuro"><span>Posts no mês</span><strong><?= $resumo['total'] ?></strong>
    <small><?php foreach (Post::FORMATOS as $chave => $nome): if (!empty($resumo['formatos'][$chave])): ?><?= $resumo['formatos'][$chave] ?> <?= mb_strtolower($nome) ?> · <?php endif; endforeach; ?></small></div>
  <div class="mk-numero"><span>Publicados</span><strong><?= $resumo['publicados'] ?></strong><small>de <?= $resumo['total'] ?> planejados</small></div>
  <a class="mk-numero <?= $resumo['aprovacao'] ? 'alerta' : '' ?>" href="?mes=<?= e($mes) ?>&etapa=<?= (int) (\App\Services\MarketingService::etapaDoTipo('aprovacao')['id'] ?? 0) ?>"><span>Aguardando aprovação</span><strong><?= $resumo['aprovacao'] ?></strong><small><?= $resumo['ajuste'] ?> com ajuste pedido</small></a>
  <div class="mk-numero <?= $fracas ? 'alerta' : 'ok' ?>"><span>Semanas abaixo da meta</span><strong><?= $fracas ?></strong><small>meta: <?= $meta ?> posts por semana</small></div>
</div>

<form class="mk-filtros" method="get" data-filtros>
  <input type="hidden" name="mes" value="<?= e($mes) ?>">
  <select name="formato" aria-label="Formato"><option value="">Todos os formatos</option><?php foreach (Post::FORMATOS as $k => $n): ?><option value="<?= $k ?>" <?= $sel('formato', $k) ?>><?= $n ?></option><?php endforeach; ?></select>
  <select name="etapa" aria-label="Etapa"><option value="">Todas as etapas</option><?php foreach ($etapas as $e): ?><option value="<?= (int) $e['id'] ?>" <?= $sel('etapa', $e['id']) ?>><?= e($e['nome']) ?></option><?php endforeach; ?></select>
  <select name="campanha" aria-label="Campanha"><option value="">Todas as campanhas</option><?php foreach ($listaCampanhas as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $sel('campanha', $c['id']) ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
  <?php if (array_filter($filtros)): ?><a class="pequeno" href="?mes=<?= e($mes) ?>">Limpar</a><?php endif; ?>
  <div class="mk-legenda"><?php foreach ($etapas as $e): ?><span><i style="background: <?= e($e['cor']) ?>"></i><?= e($e['nome']) ?></span><?php endforeach; ?></div>
</form>

<div class="mk-calendario" data-mural>
  <div class="mk-cal-cabeca"><?php foreach ($diasSemana as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
  <?php foreach ($semanas as $semana):
      $qtdSemana = array_sum(array_map(fn($d) => count($porDia[$d] ?? []), $semana));
      $camps = array_filter($campanhas, fn($c) => $c['inicio'] <= $semana[6] && $c['fim'] >= $semana[0]); ?>
    <div class="mk-semana <?= $meta && $qtdSemana < $meta && $semana[6] >= $hoje ? 'fraca' : '' ?>">
      <?php if ($camps): ?>
        <div class="mk-faixas">
          <?php foreach ($camps as $c):
              $ini = max(0, array_search(max($c['inicio'], $semana[0]), $semana, true));
              $fim = array_search(min($c['fim'], $semana[6]), $semana, true); ?>
            <a class="mk-faixa" href="<?= url('/marketing/planejamento') ?>?mes=<?= substr($c['inicio'], 0, 7) ?>" style="--cor: <?= e($c['cor']) ?>; grid-column: <?= $ini + 1 ?> / <?= $fim + 2 ?>"><?= e($c['nome']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mk-dias">
        <?php foreach ($semana as $k => $d): $foraDoMes = !str_starts_with($d, $mes); $comemorativa = $datas[substr($d, 5)] ?? null; ?>
          <div class="mk-dia <?= $foraDoMes ? 'fora' : '' ?> <?= $d === $hoje ? 'hoje' : '' ?> <?= $d < $hoje ? 'passado' : '' ?>" data-dia="<?= $d ?>">
            <div class="mk-dia-topo">
              <b data-semana="<?= $diasSemana[$k] ?>"><?= (int) substr($d, 8) ?></b>
              <?php if ($podeEditar && $d >= $hoje): ?><button type="button" class="mk-mais" data-novo-post="<?= $d ?>" aria-label="Novo post em <?= data_br($d) ?>">+</button><?php endif; ?>
            </div>
            <?php if ($comemorativa): ?><span class="mk-comemorativa" title="Data comemorativa"><?= e($comemorativa) ?></span><?php endif; ?>
            <?php foreach ($porDia[$d] ?? [] as $p): ?>
              <article class="mk-post" <?= $podeEditar ? 'draggable="true"' : '' ?> data-post="<?= (int) $p['id'] ?>" style="--cor: <?= e($p['etapa_cor']) ?>" title="<?= e($p['etapa']) ?>">
                <button type="button" data-abrir-cliente="<?= (int) $p['id'] ?>">
                  <?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" loading="lazy"><?php else: ?><span class="mk-sem-imagem"><?= mb_substr(Post::FORMATOS[$p['formato']], 0, 1) ?></span><?php endif; ?>
                  <span class="mk-post-texto">
                    <span class="mk-formato <?= e($p['formato']) ?>"><?= Post::FORMATOS[$p['formato']] ?><?= $p['hora'] ? ' · ' . substr($p['hora'], 0, 5) : '' ?></span>
                    <strong><?= e($p['titulo']) ?></strong>
                  </span>
                </button>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($meta): ?><span class="mk-contagem-semana"><?= $qtdSemana ?>/<?= $meta ?></span><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?= Visao::renderizar('carteira/_drawer', ['url' => '/marketing/posts/{id}', 'rotulo' => 'Post do Instagram']) ?>
