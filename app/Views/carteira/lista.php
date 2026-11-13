<?php
/** Lista de clientes. Variáveis: $filtros, $opcoes, $resultado, $ordem */
use App\Core\Visao;
use App\Services\Normalizar;

$r = $resultado;
$parametros = array_filter($filtros) + ($ordem !== 'nome' ? ['ordem' => $ordem] : []);
$linkPagina = fn(int $p) => url('/carteira/lista') . '?' . e(http_build_query($parametros + ['pagina' => $p]));
$primeiro = $r['total'] ? ($r['pagina'] - 1) * $r['por_pagina'] + 1 : 0;
?>
<?= Visao::renderizar('carteira/_cabecalho', ['aba' => 'lista', 'filtros' => $filtros]) ?>
<?= Visao::renderizar('carteira/_filtros', ['filtros' => $filtros, 'opcoes' => $opcoes, 'acao' => '/carteira/lista', 'ordem' => $ordem]) ?>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira">
      <thead>
        <tr><th>Estabelecimento</th><th>Cidade</th><th>Contato</th><th>Prioridade</th><th>Etapa</th><th>Responsável</th><th>Última compra</th></tr>
      </thead>
      <tbody>
      <?php foreach ($r['itens'] as $c): ?>
        <tr>
          <td>
            <button class="nome-cliente" type="button" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><?= \App\Services\NotificacaoService::marca((int) $c['id']) ?>
            <small class="suave"><?= e($c['segmento']) ?></small>
          </td>
          <td><?= e($c['cidade']) ?> / <?= e($c['uf']) ?></td>
          <td><?= $c['telefone'] ? e(Normalizar::telefoneFormatado($c['telefone'])) : ($c['whatsapp'] ? e(Normalizar::telefoneFormatado($c['whatsapp'])) : '<span class="suave">Não informado</span>') ?></td>
          <td><span class="selo prioridade-<?= e(Normalizar::semAcento($c['prioridade'])) ?>"><?= e($c['prioridade']) ?></span></td>
          <td><span class="selo-etapa" style="--cor: <?= e($c['etapa_cor']) ?>"><?= e($c['etapa']) ?></span></td>
          <td class="pequeno"><?= e($c['responsavel']) ?></td>
          <td class="pequeno"><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '<span class="suave">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$r['itens']): ?><tr><td colspan="7" class="vazio">Nenhum estabelecimento encontrado com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra">
    <span>Exibindo <?= $r['total'] ? number_format($primeiro, 0, ',', '.') . '–' . number_format($primeiro + count($r['itens']) - 1, 0, ',', '.') : 0 ?> de <?= number_format($r['total'], 0, ',', '.') ?></span>
    <?php if ($r['paginas'] > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php if ($r['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= $linkPagina($r['pagina'] - 1) ?>">‹ Anterior</a><?php endif; ?>
        <span class="pequeno">Página <?= $r['pagina'] ?> de <?= $r['paginas'] ?></span>
        <?php if ($r['pagina'] < $r['paginas']): ?><a class="botao contorno pequeno" href="<?= $linkPagina($r['pagina'] + 1) ?>">Próxima ›</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<?= Visao::renderizar('carteira/_drawer') ?>
