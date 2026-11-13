<?php
/** Lista de casos de cobrança. Variáveis: $filtros, $opcoes, $resumo, $resultado */
use App\Core\Visao;

$r = $resultado;
$linkPagina = fn(int $p) => url('/cobranca/lista') . '?' . e(http_build_query(array_filter($filtros) + ['pagina' => $p]));
$primeiro = $r['total'] ? ($r['pagina'] - 1) * $r['por_pagina'] + 1 : 0;
?>
<?= Visao::renderizar('cobranca/_cabecalho', ['aba' => 'lista', 'filtros' => $filtros, 'opcoes' => $opcoes, 'resumo' => $resumo]) ?>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira">
      <thead>
        <tr><th>Cliente</th><th>Cidade</th><th class="direita">Em aberto</th><th>Vencimento</th><th>Atraso</th><th>Etapa</th><th>Promessa</th><th>Responsável</th></tr>
      </thead>
      <tbody>
      <?php foreach ($r['itens'] as $c): $dias = (int) $c['dias_atraso']; ?>
        <tr>
          <td>
            <button class="nome-cliente" type="button" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><?= \App\Services\NotificacaoService::marca((int) ($c['cliente_id'] ?? 0)) ?>
            <small class="suave"><?= $c['cnpj'] ? e(cnpj_formatado($c['cnpj'])) : 'Sem CNPJ' ?></small>
          </td>
          <td><?= e($c['cidade']) ?> / <?= e($c['uf']) ?></td>
          <td class="direita"><strong><?= dinheiro((float) $c['valor_total']) ?></strong></td>
          <td class="pequeno"><?= $c['vencimento_mais_antigo'] ? data_br($c['vencimento_mais_antigo']) : '—' ?></td>
          <td><?php if ($c['vencimento_mais_antigo'] && $c['situacao'] === 'aberta'): ?><span class="selo <?= $dias > 90 ? 'vermelho' : ($dias > 30 ? 'laranja' : 'cinza') ?>"><?= $dias ?> dias</span><?php else: ?><span class="suave">—</span><?php endif; ?></td>
          <td><span class="selo-etapa" style="--cor: <?= e($c['etapa_cor']) ?>"><?= e($c['etapa']) ?></span></td>
          <td class="pequeno"><?= $c['promessa_em'] ? data_br($c['promessa_em']) : '<span class="suave">—</span>' ?></td>
          <td class="pequeno"><?= e($c['responsavel']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$r['itens']): ?><tr><td colspan="8" class="vazio">Nenhum caso de cobrança com esses filtros.</td></tr><?php endif; ?>
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

<?= Visao::renderizar('carteira/_drawer', ['url' => '/cobranca/{id}/caso', 'rotulo' => 'Caso de cobrança']) ?>
