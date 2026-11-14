<?php
/**
 * Início do Marketing: resumo da semana, mini calendário do mês e lista de tarefas (fazendo, próximas, aprovação, feitas).
 * Variáveis: $so ('minhas'|'todas'), $mes, $semanas, $porDia, $colunas, $resumo, $datas, $tipos, $funcoes, $pessoas, $pode
 */
use App\Core\Auth;
use App\Core\Visao;

$hoje = date('Y-m-d');
$podeAprovar = Auth::pode('marketing.aprovar');
$icones = asset('img/icones.svg');
$acoes = '<a class="botao contorno" href="' . url('/marketing/pedir') . '">Pedidos ao Marketing</a>'
    . ($pode ? '<button class="botao" type="button" data-nova-tarefa><svg class="icone"><use href="' . $icones . '#mais"/></svg>Nova tarefa</button>' : '');
$nomes = ['fazendo' => ['Fazendo agora', 'Em andamento'], 'a_fazer' => ['A fazer', 'Próximas, por prazo'], 'aprovacao' => ['Esperando aprovação', $podeAprovar ? 'Você aprova' : 'Com o Gestor de Marketing'], 'feito' => ['Feito', 'Últimos 7 dias']];
$diasSemana = ['S', 'T', 'Q', 'Q', 'S', 'S', 'D'];
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'inicio', 'mes' => $mes, 'titulo' => 'Início do Marketing', 'sobretitulo' => 'MARKETING',
    'subtitulo' => 'O que está sendo feito, o que vem depois e o que espera aprovação.', 'acoes' => $acoes]) ?>

<div class="mk-numeros mt-resumo">
  <a class="mk-numero escuro" href="<?= url('/marketing') ?>"><span>Posts nesta semana</span><strong><?= (int) $resumo['posts_semana'] ?></strong><small>no mural</small></a>
  <div class="mk-numero <?= $resumo['aprovacao'] ? 'alerta' : 'ok' ?>"><span>Esperando aprovação</span><strong data-n-aprovacao><?= (int) $resumo['aprovacao'] ?></strong><small>posts e tarefas</small></div>
  <div class="mk-numero <?= $resumo['atrasadas'] ? 'alerta' : 'ok' ?>"><span>Tarefas atrasadas</span><strong data-n-atrasadas><?= (int) $resumo['atrasadas'] ?></strong><small>prazo vencido</small></div>
  <div class="mk-numero"><span>Pedidos novos</span><strong><?= (int) $resumo['pedidos_novos'] ?></strong><small>de outros setores</small></div>
  <a class="mk-numero" href="<?= url('/desenvolvimento') ?>"><span>Desenvolvimento</span><strong><?= (int) $resumo['rotulos'] ?></strong><small>etapas do Marketing em andamento</small></a>
</div>

<div class="mt-layout">
  <section class="mt-quadro" aria-label="Lista de tarefas">
    <div class="mt-quadro-topo">
      <h2>Tarefas</h2>
      <nav class="mt-filtro" aria-label="Quais tarefas">
        <a href="<?= url('/marketing/inicio') ?>?mes=<?= e($mes) ?>" class="<?= $so === 'minhas' ? 'ativo' : '' ?>">Das minhas funções</a>
        <a href="<?= url('/marketing/inicio') ?>?ver=todas&amp;mes=<?= e($mes) ?>" class="<?= $so === 'todas' ? 'ativo' : '' ?>" data-ver-todas>Todas</a>
      </nav>
    </div>
    <div class="mt-colunas">
      <?php foreach ($colunas as $sit => $lista): ?>
        <div class="mt-coluna mt-<?= $sit ?>" data-coluna="<?= $sit ?>">
          <h3><?= $nomes[$sit][0] ?> <b><?= count($lista) ?></b><small><?= $nomes[$sit][1] ?></small></h3>
          <?php foreach ($lista as $c): ?><?= Visao::renderizar('marketing/_tarefa_cartao', ['c' => $c, 'pode' => $pode, 'podeAprovar' => $podeAprovar]) ?><?php endforeach; ?>
          <?php if (!$lista): ?><p class="mt-vazio"><?= $sit === 'fazendo' ? 'Nada em andamento.' : ($sit === 'a_fazer' ? 'Nada na fila. 🎉' : ($sit === 'aprovacao' ? 'Nada para aprovar.' : 'Nada concluído nesta semana.')) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="card mt-calendario" data-calendario>
    <h2>Calendário</h2>
    <div class="mt-cal-grade" role="grid">
      <?php foreach ($diasSemana as $d): ?><span class="mt-cal-dsem"><?= $d ?></span><?php endforeach; ?>
      <?php foreach ($semanas as $semana): foreach ($semana as $dia): $itens = $porDia[$dia] ?? []; $comemorativa = $datas[substr($dia, 5)] ?? null; ?>
        <button type="button" class="mt-cal-dia <?= substr($dia, 0, 7) !== $mes ? 'fora' : '' ?> <?= $dia === $hoje ? 'hoje' : '' ?> <?= $itens ? 'tem' : '' ?>"
          data-dia="<?= $dia ?>" title="<?= e($comemorativa ?? '') ?>" aria-label="<?= data_br($dia) ?>: <?= count($itens) ?> item(ns)">
          <span><?= (int) substr($dia, 8) ?></span>
          <i class="mt-pontos"><?php foreach (array_slice($itens, 0, 4) as $it): ?><b class="<?= $it['tipo'] ?>" style="<?= $it['tipo'] === 'post' ? '--c:' . e($it['cor']) : '' ?>"></b><?php endforeach; ?></i>
          <?php if ($comemorativa): ?><em class="mt-data" aria-hidden="true">★</em><?php endif; ?>
        </button>
      <?php endforeach; endforeach; ?>
    </div>
    <p class="mt-legenda"><b class="post"></b> post <b class="prazo"></b> prazo de tarefa <em>★</em> data comemorativa</p>
    <div class="mt-cal-lista" data-cal-lista>
      <?php foreach ($porDia as $dia => $itens): ?>
        <div data-lista-dia="<?= $dia ?>" hidden>
          <strong><?= data_br($dia) ?><?= isset($datas[substr($dia, 5)]) ? ' · ' . e($datas[substr($dia, 5)]) : '' ?></strong>
          <ul><?php foreach ($itens as $it): ?><li><a href="<?= url($it['link']) ?>"><?= $it['tipo'] === 'post' ? '📷 ' : '⏰ ' ?><?= e($it['titulo']) ?></a></li><?php endforeach; ?></ul>
        </div>
      <?php endforeach; ?>
      <p class="pequeno suave" data-cal-dica>Toque num dia para ver os posts e prazos.</p>
    </div>
  </aside>
</div>

<?php if ($pode): ?>
<dialog class="mt-dialogo" data-dialogo-tarefa>
  <form method="post" action="<?= url('/marketing/tarefas') ?>" enctype="multipart/form-data" class="pd-form">
    <?= csrf_campo() ?>
    <h2>Nova tarefa do Marketing</h2>
    <label>O que fazer<input type="text" name="titulo" maxlength="160" required></label>
    <div class="pd-duas">
      <label>Tipo<select name="tipo_id" required><option value="">— escolha</option><?php foreach ($tipos as $t): ?><option value="<?= (int) $t['id'] ?>" data-funcao="<?= e($t['funcao_slug']) ?>"><?= e($t['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Função <small>vem do tipo</small><select name="funcao_slug"><option value="">(a do tipo)</option><?php foreach ($funcoes as $f): ?><option value="<?= e($f['slug']) ?>"><?= e($f['nome']) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="pd-duas">
      <label>Quem faz <small>opcional</small><select name="responsavel_id"><option value="">Quem pegar primeiro</option><?php foreach ($pessoas as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Prazo<input type="date" name="prazo" min="<?= $hoje ?>"></label>
    </div>
    <label>Detalhes<textarea name="descricao" rows="3"></textarea></label>
    <label>Anexo <small>PDF ou imagem (opcional)</small><input type="file" name="anexo" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
    <label class="caixa-marcar"><input type="checkbox" name="urgente" value="1"> Urgente</label>
    <div class="acoes-form"><button class="botao lima" type="submit">Criar tarefa</button><button class="botao contorno" type="button" data-fechar>Cancelar</button></div>
  </form>
</dialog>
<?php endif; ?>
