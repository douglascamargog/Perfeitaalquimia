<?php
/** Desenvolvimento de Produto: quadro por etapa. Variáveis: $etapas, $projetos, $progressos, $f, $categorias, $clientesTerc, $podeGerenciar */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$porEtapa = [];
foreach ($projetos as $p) {
    $porEtapa[(int) $p['etapa_id']][] = $p;
}
$link = fn(array $x) => url('/desenvolvimento') . '?' . e(http_build_query(array_filter($x + $f)));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DESENVOLVIMENTO DE PRODUTO</div><h1>Projetos de produto novo</h1>
    <p>Do briefing ao lançamento: cada etapa é feita por uma área (Técnica, Marketing, Qualidade, Produção). O produto é preenchido nas telas de sempre e só aparece no catálogo no lançamento.</p></div>
  <div class="acoes">
    <a class="botao contorno" href="<?= url('/desenvolvimento/minhas') ?>">Minhas tarefas</a>
    <?php if ($podeGerenciar): ?><button class="botao" type="button" data-abrir="#novo-projeto"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Novo projeto</button><?php endif; ?>
  </div>
</div>

<div class="chips dp-filtros">
  <a class="chip <?= !$f['tipo'] ? 'ativo' : '' ?>" href="<?= $link(['tipo' => '']) ?>">Todos</a>
  <a class="chip <?= $f['tipo'] === 'nosso' ? 'ativo' : '' ?>" href="<?= $link(['tipo' => 'nosso']) ?>">Nossos produtos</a>
  <a class="chip <?= $f['tipo'] === 'terceirizacao' ? 'ativo' : '' ?>" href="<?= $link(['tipo' => 'terceirizacao']) ?>">Terceirização</a>
  <a class="chip <?= $f['atrasados'] ? 'ativo' : '' ?>" href="<?= $link(['atrasados' => $f['atrasados'] ? '' : '1']) ?>">Atrasados</a>
  <a class="chip <?= $f['situacao'] === 'lancado' ? 'ativo' : '' ?>" href="<?= $link(['situacao' => $f['situacao'] === 'lancado' ? '' : 'lancado']) ?>">Lançados</a>
</div>

<?php if ($f['situacao'] === 'lancado'): ?>
  <div class="dp-grade"><?php foreach ($projetos as $p): ?><?= Visao::renderizar('desenvolvimento/_cartao', ['p' => $p, 'progresso' => $progressos[(int) $p['id']] ?? 0]) ?><?php endforeach; ?></div>
  <?php if (!$projetos): ?><div class="card vazio"><p>Nenhum produto lançado ainda.</p></div><?php endif; ?>
<?php else: ?>
<div class="dp-quadro" aria-label="Projetos por etapa">
  <?php foreach ($etapas as $e): $lista = $porEtapa[(int) $e['id']] ?? []; ?>
    <section class="dp-coluna" data-etapa="<?= (int) $e['id'] ?>">
      <header><strong><?= e($e['nome']) ?></strong><small><?= e($e['funcao'] ?: 'gestão') ?> · <?= count($lista) ?></small></header>
      <?php foreach ($lista as $p): ?><?= Visao::renderizar('desenvolvimento/_cartao', ['p' => $p, 'progresso' => $progressos[(int) $p['id']] ?? 0]) ?><?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($podeGerenciar): ?>
<dialog id="novo-projeto" class="ct-dialogo">
  <form method="post" action="<?= url('/desenvolvimento') ?>" class="pd-form">
    <?= csrf_campo() ?>
    <h2>Novo projeto de produto</h2>
    <fieldset class="dp-tipos"><legend>O que vamos fabricar?</legend>
      <?php foreach (\App\Services\DesenvolvimentoService::TIPOS as $slug => $nomeTipo): ?>
        <label class="dp-tipo"><input type="radio" name="tipo" value="<?= e($slug) ?>" <?= $slug === 'pote' ? 'checked' : '' ?>>
          <?= Visao::renderizar('desenvolvimento/_tipos', ['tipo' => $slug, 'classe' => 'completo']) ?>
          <strong><?= e($nomeTipo) ?></strong><small><?= ['pote' => 'pó ou cápsulas', 'frasco' => 'líquido ou gotas', 'caixinha' => 'cartucho, chás, blister'][$slug] ?></small></label>
      <?php endforeach; ?>
    </fieldset>
    <label>Nome do produto<input type="text" name="nome" maxlength="160" required placeholder="Ex.: Colágeno + Vitamina C 300 g"></label>
    <label>Briefing <small>objetivo, público, diferenciais, apresentação</small><textarea name="briefing" rows="4"></textarea></label>
    <div class="pd-duas">
      <label>Apresentação<select name="categoria_id"><option value="">—</option><?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Tamanho / embalagem<input type="text" name="embalagem" maxlength="120" placeholder="Ex.: 300 g, 30 ml, 60 cápsulas"></label>
    </div>
    <div class="pd-duas">
      <label>Cliente de terceirização <small>se for com a marca de um cliente</small><select name="cliente_id"><option value="">— Produto nosso</option>
        <?php foreach ($clientesTerc as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
      <label>Prazo de lançamento<input type="date" name="prazo"></label>
    </div>
    <div class="acoes-form"><button class="botao contorno" type="button" data-fechar>Cancelar</button><button class="botao" type="submit">Criar projeto</button></div>
  </form>
</dialog>
<?php endif; ?>
