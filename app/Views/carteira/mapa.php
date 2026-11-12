<?php
/** Mapa em tela cheia. Variáveis: $filtros, $opcoes, $resumo, $segmentos */
use App\Core\Visao;

$query = http_build_query(array_filter($filtros));
?>
<?= Visao::renderizar('carteira/_cabecalho', ['aba' => 'mapa', 'filtros' => $filtros]) ?>
<?= Visao::renderizar('carteira/_filtros', ['filtros' => $filtros, 'opcoes' => $opcoes, 'acao' => '/carteira/mapa']) ?>

<section class="painel-mapa">
  <div class="card painel-mapa-principal">
    <div class="mapa-moldura grande"><div class="mapa" data-mapa data-filtros="<?= e($query) ?>" aria-label="Mapa da carteira"></div><div class="mapa-carregando" data-mapa-carregando>Carregando mapa…</div></div>
    <div class="mapa-rodape"><span data-mapa-contagem>—</span><span>Pontos sem endereço exato ficam em posição aproximada dentro da cidade.</span></div>
  </div>
  <div class="card painel-mapa-lado">
    <h4>Presença</h4>
    <p>Resumo dos estabelecimentos filtrados.</p>
    <div class="mapa-resumo">
      <div><strong><?= number_format($resumo['total'], 0, ',', '.') ?></strong><span>Estabelecimentos</span></div>
      <div><strong><?= number_format($resumo['cidades'], 0, ',', '.') ?></strong><span>Cidades</span></div>
      <div><strong><?= number_format($resumo['clientes'], 0, ',', '.') ?></strong><span>Clientes</span></div>
      <div><strong><?= number_format($resumo['alta'], 0, ',', '.') ?></strong><span>Prioridade alta</span></div>
    </div>
    <div class="mapa-legenda">
      <div><i style="background:#caff4e"></i>Produtos naturais</div>
      <div><i style="background:#35d99c"></i>Suplementos</div>
      <div><i style="background:#6cb8ff"></i>Farmácia</div>
      <div><i style="background:#c9cfc9"></i>Outros</div>
      <div><i class="anel"></i>Cliente (já comprou)</div>
    </div>
    <p class="pequeno suave">O mapa usa OpenFreeMap e precisa de internet. Clique num ponto para abrir a ficha.</p>
  </div>
</section>

<?= Visao::renderizar('carteira/_drawer') ?>
