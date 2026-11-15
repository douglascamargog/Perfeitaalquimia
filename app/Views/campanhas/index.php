<?php
/**
 * Comercial › Campanhas: lista (no ar, agendadas, encerradas) e o visual padrão do catálogo.
 * Variáveis: $aba, $campanhas, $pode, $visual (padrão), $banner
 */
use App\Services\CampanhaService as C;

$situacoes = ['no_ar' => ['No ar', 'verde'], 'agendada' => ['Agendada', 'azul'], 'encerrada' => ['Encerrada', 'cinza'], 'desligada' => ['Desligada', 'cinza']];
$grupos = ['no_ar' => [], 'agendada' => [], 'outras' => []];
foreach ($campanhas as $c) {
    $s = C::situacao($c);
    $grupos[in_array($s, ['no_ar', 'agendada'], true) ? $s : 'outras'][] = $c + ['situacao' => $s];
}
$titulos = ['no_ar' => 'No ar agora', 'agendada' => 'Agendadas', 'outras' => 'Encerradas e desligadas'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL</div><h1>Campanhas</h1>
    <p>Campanhas do mês ou da semana: o cliente vê no catálogo, e a vendedora vê ao montar o pedido. Exemplos: "acima de R$ 2.000, 10% de desconto" ou "acima de R$ 10.000, ganha uma bicicleta".</p></div>
  <?php if ($pode): ?><div class="acoes"><a class="botao contorno" href="<?= url('/catalogo') ?>" target="_blank" rel="noopener">Ver o catálogo ↗</a><a class="botao" href="<?= url('/campanhas/nova') ?>">+ Nova campanha</a></div><?php endif; ?>
</div>

<nav class="abas" aria-label="Campanhas">
  <a class="<?= $aba === 'campanhas' ? 'ativa' : '' ?>" href="<?= url('/campanhas') ?>">Campanhas</a>
  <a class="<?= $aba === 'visual' ? 'ativa' : '' ?>" href="<?= url('/campanhas') ?>?aba=visual">Visual do catálogo</a>
</nav>

<?php if ($aba === 'campanhas'): ?>
  <?php if (!$campanhas): ?>
    <div class="card cp-vazio"><strong>Nenhuma campanha ainda.</strong><p>Crie a primeira: escolha o período, as faixas (desconto, brinde, frete grátis) e os produtos em promoção.</p>
      <?php if ($pode): ?><a class="botao" href="<?= url('/campanhas/nova') ?>">+ Nova campanha</a><?php endif; ?></div>
  <?php endif; ?>
  <?php foreach ($grupos as $g => $lista): if (!$lista) continue; ?>
    <h2 class="cp-grupo"><?= e($titulos[$g]) ?> <small><?= count($lista) ?></small></h2>
    <div class="cp-lista">
      <?php foreach ($lista as $c):
        $v = C::visual([C::completar($c)]);
        [$rotulo, $cor] = $situacoes[$c['situacao']]; ?>
        <article class="cp-card <?= $c['situacao'] === 'no_ar' ? '' : 'apagada' ?>">
          <a class="cp-mini <?= $v['tema'] === 'escuro' ? 'escuro' : '' ?>" href="<?= url('/campanhas/' . (int) $c['id']) ?>" style="--p:<?= e($v['primaria']) ?>;--d:<?= e($v['destaque']) ?>">
            <?php if ($c['selo']): ?><span class="cp-mini-selo"><?= e($c['selo']) ?></span><?php endif; ?>
            <small><?= e(C::periodo($c)) ?></small>
            <strong><?= e($c['titulo'] ?: $c['nome']) ?></strong>
          </a>
          <div class="cp-card-corpo">
            <div class="cp-card-topo"><span class="selo <?= $cor ?>"><?= $rotulo ?></span><b><?= e($c['nome']) ?></b></div>
            <p class="pequeno suave"><?= e(C::publicoTexto($c)) ?></p>
            <div class="cp-chips">
              <?php foreach (C::completar($c)['faixas'] as $f): ?><span class="cp-chip <?= e($f['tipo']) ?>">≥ <?= dinheiro((float) $f['a_partir_de']) ?> · <?= e(C::textoFaixa($f)) ?></span><?php endforeach; ?>
              <?php if ((int) $c['produtos']): ?><span class="cp-chip promo"><?= (int) $c['produtos'] ?> produto(s) em promoção</span><?php endif; ?>
            </div>
            <div class="cp-card-pe">
              <span class="pequeno suave"><?= (int) $c['pedidos'] ?> pedido(s) com a campanha</span>
              <?php if ($pode): ?>
                <form method="post" action="<?= url('/campanhas/' . (int) $c['id'] . '/duplicar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit" title="Cria uma cópia para o próximo período">Duplicar</button></form>
                <form method="post" action="<?= url('/campanhas/' . (int) $c['id'] . '/alternar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit"><?= (int) $c['ativa'] ? 'Desligar' : 'Ligar' ?></button></form>
              <?php endif; ?>
              <a class="botao pequeno" href="<?= url('/campanhas/' . (int) $c['id']) ?>"><?= $pode ? 'Editar' : 'Ver' ?></a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <form class="cp-visual" method="post" action="<?= url('/campanhas/visual') ?>" enctype="multipart/form-data" data-cp-form>
    <?= csrf_campo() ?>
    <div class="card cp-campos">
      <h2>Visual padrão do catálogo</h2>
      <p class="pequeno suave">Vale quando nenhuma campanha no ar muda o visual. Cada campanha pode ter cores e banner próprios.</p>
      <div class="campo"><label for="v-titulo">Título grande</label><input type="text" id="v-titulo" name="titulo" maxlength="120" value="<?= e($visual['titulo']) ?>" data-prev="titulo" <?= $pode ? '' : 'disabled' ?>></div>
      <div class="campo"><label for="v-chamada">Frase abaixo do título</label><textarea id="v-chamada" name="chamada" rows="2" maxlength="255" data-prev="chamada" <?= $pode ? '' : 'disabled' ?>><?= e($visual['chamada']) ?></textarea></div>
      <?= \App\Core\Visao::renderizar('campanhas/_cores', ['prim' => (string) \App\Core\Config::get('catalogo.visual_primaria', ''), 'dest' => (string) \App\Core\Config::get('catalogo.visual_destaque', ''), 'tema' => $visual['tema'], 'pode' => $pode]) ?>
      <div class="campo"><label for="v-banner">Imagem do banner (opcional)</label>
        <input id="v-banner" type="file" name="banner" accept="image/png,image/jpeg,image/webp" data-prev-banner <?= $pode ? '' : 'disabled' ?>>
        <small class="suave">Foto dos produtos ou arte com fundo. Ideal: 1200 × 700 px.</small>
        <?php if ($banner): ?><label class="opcao"><input type="checkbox" name="tirar_banner" value="1"> Tirar a imagem atual</label><?php endif; ?></div>
      <?php if ($pode): ?><button class="botao" type="submit">Salvar visual</button><?php endif; ?>
    </div>
    <?= \App\Core\Visao::renderizar('campanhas/_previa', ['v' => $visual, 'faixas' => []]) ?>
  </form>
<?php endif; ?>
