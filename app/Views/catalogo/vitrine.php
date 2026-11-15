<?php
/**
 * Vitrine do catálogo (visual de campanha): banner grande, faixas da campanha, destaques em oferta, todos os produtos
 * e a barra fixa do carrinho com "faltam R$ X para você ganhar…".
 * Variáveis: $busca, $categoria, $categorias, $produtos (com promo_pct/preco_promo), $destaques, $emPromocao, $campanhas,
 *            $resumo (cliente logado), $carrinho, $logado, $minimo, $tabelaNome, $semPrecos, $meusProdutos, $visualCatalogo
 */
use App\Core\Config;
use App\Services\CampanhaService as C;

$icones = asset('img/icones.svg');
$v = $visualCatalogo;
$camp = $v['campanha'];
$filtrando = $busca !== '' || $categoria;

// Todas as faixas das campanhas do cliente, da menor para a maior
$faixas = [];
foreach ($campanhas as $c) {
    foreach ($c['faixas'] as $f) {
        $faixas[] = $f;
    }
}
usort($faixas, fn($a, $b) => (float) $a['a_partir_de'] <=> (float) $b['a_partir_de']);

// Título em duas linhas: a última palavra na cor de destaque
$palavras = preg_split('/\s+/', trim($v['titulo'])) ?: ['Nossos', 'produtos'];
$ultima = count($palavras) > 1 ? array_pop($palavras) : null;

// Produto do quadro ao lado do título: o 1º destaque, senão o 1º em promoção, senão o 1º com foto
$vitrineFoto = $destaques[0] ?? ($emPromocao[0] ?? (array_values(array_filter($produtos, fn($p) => $p['imagem']))[0] ?? null));
$maiorPromo = $emPromocao ? max(array_column($emPromocao, 'promo_pct')) : 0;

$preco = function (array $p, bool $grande = false) use ($logado): string {
    if (!$logado) {
        return '<div class="vt-preco-off">Entre para ver o preço</div>';
    }
    if ($p['preco'] === null) {
        return '<div class="cat-sem-preco">Sem preço na sua tabela</div>';
    }
    if ($p['preco_promo'] !== null) {
        return '<div class="vt-precos"><s>' . dinheiro((float) $p['preco']) . '</s><strong class="' . ($grande ? 'grande' : '') . '">' . dinheiro((float) $p['preco_promo']) . ' <small>/ un</small></strong></div>';
    }
    return '<div class="vt-precos"><strong class="' . ($grande ? 'grande' : '') . '">' . dinheiro((float) $p['preco']) . ' <small>/ un</small></strong></div>';
};
$qtd = function (array $p) use ($logado, $carrinho): string {
    if (!$logado || $p['preco'] === null) {
        return '';
    }
    $q = (int) ($carrinho[$p['id']] ?? 0);
    return '<div class="cat-qtd" data-produto="' . (int) $p['id'] . '"><button type="button" data-menos aria-label="Diminuir">−</button>'
        . '<input type="number" min="0" max="9999" value="' . $q . '" aria-label="Quantidade de ' . e($p['nome']) . '"><button type="button" data-mais aria-label="Aumentar">+</button></div>';
};
$selo = fn(array $p) => $p['promo_pct'] > 0 ? '<span class="vt-off"><b>-' . e(rtrim(rtrim(number_format((float) $p['promo_pct'], 1, ',', ''), '0'), ',')) . '%</b><small>OFF</small></span>' : '';
$link = fn(array $p) => url('/catalogo/produto/' . rawurlencode($p['slug']));
?>
<section class="vt-hero <?= $filtrando ? 'compacto' : '' ?>">
  <div class="vt-hero-main" <?= $v['banner'] ? 'style="--banner:url(\'' . e(url('/' . $v['banner'])) . '\')"' : '' ?>>
    <span class="vt-kicker"><?= $camp ? e(C::periodo($camp)) : 'Catálogo para lojistas' ?></span>
    <h1 class="vt-titulo"><span><?= e(implode(' ', $palavras)) ?></span><?php if ($ultima): ?><span><?= e($ultima) ?></span><?php endif; ?></h1>
    <p class="vt-chamada"><?= e($v['chamada']) ?>
      <?php if ($logado && $minimo): ?><br><small>Pedido mínimo da sua região: <strong><?= e(dinheiro((float) $minimo)) ?></strong></small><?php endif; ?></p>
    <div class="vt-acoes">
      <?php if (!$logado): ?>
        <a class="vt-cta" href="<?= url('/catalogo/entrar') ?>">Entrar com o CNPJ</a>
      <?php else: ?>
        <a class="vt-cta" href="#produtos">Fazer o pedido</a>
      <?php endif; ?>
      <?php if ($emPromocao): ?><a class="vt-cta secundario" href="#ofertas">Ver as ofertas</a><?php endif; ?>
    </div>
  </div>
  <?php if ($vitrineFoto && !$filtrando): ?>
    <a class="vt-quadro" href="<?= $link($vitrineFoto) ?>">
      <?php if ($v['selo'] || $vitrineFoto['promo_pct'] > 0): ?>
        <span class="vt-etiqueta"><small><?= $v['selo'] ? 'oferta' : 'até' ?></small><strong><?= e($v['selo'] ?: '-' . rtrim(rtrim(number_format($maiorPromo, 1, ',', ''), '0'), ',') . '%') ?></strong></span>
      <?php endif; ?>
      <span class="vt-rotulo"><?= $camp ? 'Destaque da campanha' : 'Destaque' ?></span>
      <h2><?= e($vitrineFoto['nome']) ?></h2>
      <p><?= e($vitrineFoto['chamada'] ?: $vitrineFoto['embalagem']) ?></p>
      <?php if ($vitrineFoto['imagem']): ?><span class="vt-quadro-foto"><img src="<?= url('/' . $vitrineFoto['imagem']) ?>" alt=""></span><?php endif; ?>
    </a>
  <?php endif; ?>
</section>

<?php if ($faixas && !$filtrando): ?>
  <section class="vt-faixas" aria-label="Quanto mais você leva, mais ganha">
    <?php foreach ($faixas as $f): ?>
      <div class="vt-faixa <?= e($f['tipo']) ?>">
        <small>Acima de <?= dinheiro((float) $f['a_partir_de']) ?></small>
        <strong><?= $f['tipo'] === 'desconto' ? e(C::pct((float) $f['desconto_pct'])) : ($f['tipo'] === 'frete' ? 'Frete' : '🎁') ?></strong>
        <p><?= $f['tipo'] === 'desconto' ? 'de desconto no pedido' : ($f['tipo'] === 'frete' ? 'grátis para a sua loja' : e((string) $f['brinde']) . ' de brinde') ?></p>
        <?php if (!empty($f['brinde_imagem'])): ?><img src="<?= url('/' . e($f['brinde_imagem'])) ?>" alt=""><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php elseif (!$filtrando && !$logado): ?>
  <section class="vt-faixas" aria-label="Como funciona">
    <div class="vt-faixa"><small>Venda para</small><strong>CNPJ</strong><p>Lojas, farmácias e distribuidores de todo o Brasil.</p></div>
    <div class="vt-faixa"><small>Feito no</small><strong>RS</strong><p>Suplementos e produtos naturais de fabricação própria.</p></div>
    <div class="vt-faixa"><small>Pagamento</small><strong>Pix</strong><p>Pix, cartão ou boleto, com a tabela de preço da sua loja.</p></div>
  </section>
<?php endif; ?>

<?php if ($logado && $resumo && $faixas): ?>
  <section class="vt-progresso" aria-live="polite">
    <div><small>Seu pedido agora: <b data-camp-subtotal><?= dinheiro((float) $resumo['subtotal_valor']) ?></b></small>
      <strong data-camp-msg><?= e($resumo['campanha_msg'] ?: ($resumo['campanha_ganhou'] ? 'Você já ganhou tudo desta campanha! 🎉' : '')) ?></strong>
      <span class="vt-ganhou" data-camp-ganhou <?= $resumo['campanha_ganhou'] ? '' : 'hidden' ?>><?= $resumo['campanha_ganhou'] ? 'Você ganhou: ' . e($resumo['campanha_ganhou']) : '' ?></span></div>
    <div class="vt-progresso-barra"><i data-camp-barra style="width: <?= (int) $resumo['campanha_pct'] ?>%"></i></div>
  </section>
<?php endif; ?>

<?php if (!empty($semPrecos)): ?>
  <div class="cat-vazio cat-aviso-preco" role="status"><strong>Ainda não há produtos com preço na sua tabela (<?= e($tabelaNome) ?>).</strong>
    Os preços chegam do nosso sistema de faturamento. Fale com seu vendedor: ele faz o pedido para você enquanto isso.</div>
<?php endif; ?>

<?php if ($destaques): ?>
  <section class="vt-secao" id="ofertas">
    <div class="vt-secao-cab"><div><small><?= $camp ? e($camp['titulo'] ?: $camp['nome']) : 'Ofertas' ?></small><h2>Ofertas em destaque</h2></div></div>
    <div class="vt-destaques">
      <?php foreach ($destaques as $n => $p): ?>
        <article class="vt-destaque <?= $n === 0 ? 'principal' : '' ?>">
          <div class="vt-destaque-info">
            <span class="vt-pilula"><?= e($p['categoria']) ?></span>
            <a href="<?= $link($p) ?>"><h3><?= e($p['nome']) ?></h3></a>
            <p class="vt-oferta"><span class="vt-marca-texto">-<?= e(C::pct((float) $p['promo_pct'])) ?></span> neste mês</p>
            <?= $preco($p, true) ?>
            <?= $qtd($p) ?>
          </div>
          <?php if ($p['imagem']): ?><a class="vt-destaque-foto" href="<?= $link($p) ?>"><img src="<?= url('/' . $p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"></a><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($meusProdutos)): ?>
  <section class="cat-marca-cliente" aria-label="Seus produtos com a sua marca">
    <h2>Seus produtos <small>com a sua marca</small></h2>
    <div class="cat-marca-lista">
      <?php foreach ($meusProdutos as $chave => $t): $q = $carrinho[$chave] ?? 0; ?>
        <div class="cat-marca-item">
          <div><strong><?= $t['codigo_cliente'] ? '<span class="cod-produto">' . e($t['codigo_cliente']) . '</span> ' : '' ?><?= e($t['nome_rotulo']) ?></strong>
            <small><?= e($t['embalagem'] ?? '') ?> · <?= dinheiro((float) $t['preco_final']) ?> / un</small></div>
          <div class="cat-qtd" data-produto="<?= e($chave) ?>">
            <button type="button" data-menos aria-label="Diminuir">−</button>
            <input type="number" min="0" max="99999" value="<?= (int) $q ?>" aria-label="Quantidade de <?= e($t['nome_rotulo']) ?>">
            <button type="button" data-mais aria-label="Aumentar">+</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="vt-secao" id="produtos">
  <div class="vt-secao-cab"><div><small><?= count($produtos) ?> produto(s)</small><h2><?= $filtrando ? 'Resultado' : 'Todos os produtos' ?></h2></div></div>
  <form class="cat-busca" method="get" action="<?= url('/catalogo') ?>#produtos">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar produto ou código…" aria-label="Buscar produto">
    <?php if ($categoria): ?><input type="hidden" name="categoria" value="<?= (int) $categoria ?>"><?php endif; ?>
    <button class="vt-cta pequeno" type="submit">Buscar</button>
  </form>
  <nav class="cat-categorias" aria-label="Categorias">
    <a href="<?= url('/catalogo') ?><?= $busca !== '' ? '?q=' . rawurlencode($busca) : '' ?>#produtos" class="<?= !$categoria ? 'ativa' : '' ?>">Todos</a>
    <?php foreach ($categorias as $c): ?>
      <a href="<?= url('/catalogo') ?>?categoria=<?= (int) $c['id'] ?><?= $busca !== '' ? '&q=' . rawurlencode($busca) : '' ?>#produtos" class="<?= (int) $categoria === (int) $c['id'] ? 'ativa' : '' ?>"><?= e($c['nome']) ?> <small><?= (int) $c['total'] ?></small></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$produtos && empty($semPrecos)): ?><p class="cat-vazio">Nenhum produto encontrado.</p><?php endif; ?>
  <div class="cat-grade">
    <?php foreach ($produtos as $p): ?>
      <article class="cat-card <?= $p['promo_pct'] > 0 ? 'em-oferta' : '' ?>">
        <?= $selo($p) ?>
        <a href="<?= $link($p) ?>" class="cat-card-foto">
          <?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php endif; ?>
        </a>
        <div class="cat-card-corpo">
          <small><?= !empty($p['codigo']) ? '<span class="cod-produto">' . e($p['codigo']) . '</span> ' : '' ?><?= e($p['categoria']) ?></small>
          <a href="<?= $link($p) ?>"><strong><?= e($p['nome']) ?></strong></a>
          <span class="cat-embalagem"><?= e($p['embalagem']) ?></span>
          <?= $preco($p) ?>
          <?= $qtd($p) ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!$logado): ?>
  <section class="vt-rodape-cta">
    <h2>Ainda não compra com a gente?</h2>
    <p>Cadastre a sua loja: você recebe a tabela de preço, o pedido mínimo da sua região e acesso ao catálogo com as campanhas do mês.</p>
    <div class="vt-acoes">
      <a class="vt-cta escuro" href="<?= url('/catalogo/entrar') ?>">Já sou cliente: entrar</a>
      <?php if ($w = preg_replace('/\D/', '', (string) Config::get('empresa.whatsapp', ''))): ?>
        <a class="vt-cta contorno" href="https://wa.me/55<?= e($w) ?>?text=<?= rawurlencode('Olá! Quero comprar para a minha loja.') ?>" target="_blank" rel="noopener noreferrer">Quero ser cliente (WhatsApp)</a>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($logado): ?>
  <a class="cat-barra-carrinho" href="<?= url('/catalogo/carrinho') ?>" data-barra-carrinho <?= array_sum($carrinho) ? '' : 'hidden' ?>>
    <svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg>
    <span class="cat-barra-texto"><span><b data-qtd-barra><?= array_sum($carrinho) ?></b> item(ns) · <span data-subtotal><?= $resumo ? dinheiro((float) $resumo['subtotal_valor']) : '' ?></span></span>
      <?php if ($faixas): ?><em data-camp-msg><?= e($resumo['campanha_msg'] ?? '') ?></em><?php endif; ?></span>
    <span class="cat-barra-falta" data-falta></span>
    <strong>Ver carrinho →</strong>
  </a>
<?php endif; ?>
