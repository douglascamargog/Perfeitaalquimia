<?php
/**
 * Lista completa dos pedidos do e-commerce (paginada no servidor). Sem dados pessoais.
 * Variáveis: $f (filtros), $extra (sem_nf, atrasado), $lista (EcommerceService::pedidos)
 */
use App\Core\Visao;
use App\Integrations\Tray\TrayTexto;

$icones = asset('img/icones.svg');
$base = array_filter(['periodo' => $f['periodo'], 'de' => $f['periodo'] === 'livre' ? $f['de'] : null, 'ate' => $f['periodo'] === 'livre' ? $f['ate'] : null,
    'canal' => $f['canal'], 'uf' => $f['uf'], 'situacao' => $f['situacao'], 'busca' => $f['busca'],
    'sem_nf' => $extra['sem_nf'] ? 1 : null, 'atrasado' => $extra['atrasado'] ? 1 : null], fn($v) => $v !== null && $v !== '');
$acoes = '<a class="botao contorno" href="' . url('/marketing/ecommerce') . '?' . e(http_build_query(array_diff_key($base, ['busca' => 1, 'sem_nf' => 1, 'atrasado' => 1]))) . '">← Painel</a>'
    . '<a class="botao contorno" href="' . url('/marketing/ecommerce/pedidos.csv') . '?' . e(http_build_query($base)) . '" data-eco-csv><svg class="icone"><use href="' . $icones . '#baixar"/></svg>Planilha</a>'
    . '<button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="' . $icones . '#imprimir"/></svg>Imprimir</button>';
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'ecommerce', 'mes' => date('Y-m'), 'semMes' => true, 'titulo' => 'Pedidos do e-commerce',
    'sobretitulo' => 'LOJA VIRTUAL · TRAY · MARKETPLACES', 'acoes' => $acoes,
    'subtitulo' => number_format($lista['total'], 0, ',', '.') . ' pedido(s) de ' . data_br($f['de']) . ' a ' . data_br($f['ate']) . '. Clique no número para ver o pedido.']) ?>

<form class="eco-filtros no-print" method="get" action="<?= url('/marketing/ecommerce/pedidos') ?>">
  <input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nº do pedido, do marketplace ou cupom" aria-label="Buscar" data-eco-busca>
  <label class="eco-datas">de <input type="date" name="de" value="<?= e($f['de']) ?>"> até <input type="date" name="ate" value="<?= e($f['ate']) ?>"></label>
  <select name="canal" aria-label="Canal">
    <option value="">Todos os canais</option>
    <?php foreach (TrayTexto::CANAIS as $chave => [$nome]): ?><option value="<?= $chave ?>" <?= $f['canal'] === $chave ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?>
  </select>
  <select name="situacao" aria-label="Situação">
    <option value="">Todas as situações</option>
    <?php foreach (TrayTexto::SITUACOES as $chave => [$nome]): ?><option value="<?= $chave ?>" <?= $f['situacao'] === $chave ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?>
  </select>
  <input type="text" name="uf" value="<?= e($f['uf']) ?>" maxlength="2" placeholder="UF" aria-label="Estado" style="width:64px">
  <label class="pequeno"><input type="checkbox" name="sem_nf" value="1" <?= $extra['sem_nf'] ? 'checked' : '' ?>> sem nota</label>
  <label class="pequeno"><input type="checkbox" name="atrasado" value="1" <?= $extra['atrasado'] ? 'checked' : '' ?>> passou do prazo</label>
  <button class="botao pequeno" type="submit">Filtrar</button>
  <a class="b-chip" href="<?= url('/marketing/ecommerce/pedidos') ?>">Limpar</a>
</form>

<section class="card">
  <?= Visao::renderizar('marketing/_ecommerce_tabela', ['linhas' => $lista['linhas'], 'compacta' => false]) ?>
  <?php if ($lista['paginas'] > 1): ?>
    <nav class="paginacao no-print" aria-label="Páginas" data-eco-paginas>
      <?php $de = max(1, $lista['pagina'] - 4); $ate = min($lista['paginas'], $de + 9); ?>
      <?php if ($lista['pagina'] > 1): ?><a class="botao contorno pequeno" href="?<?= e(http_build_query($base + ['pagina' => $lista['pagina'] - 1])) ?>">‹</a><?php endif; ?>
      <?php for ($i = $de; $i <= $ate; $i++): ?>
        <a class="botao <?= $i === $lista['pagina'] ? '' : 'contorno' ?> pequeno" href="?<?= e(http_build_query($base + ['pagina' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($lista['pagina'] < $lista['paginas']): ?><a class="botao contorno pequeno" href="?<?= e(http_build_query($base + ['pagina' => $lista['pagina'] + 1])) ?>">›</a><?php endif; ?>
      <span class="pequeno suave">página <?= $lista['pagina'] ?> de <?= $lista['paginas'] ?></span>
    </nav>
  <?php endif; ?>
</section>
