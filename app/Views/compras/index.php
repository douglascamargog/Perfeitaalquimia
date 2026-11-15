<?php
/**
 * Compras › Orçamentos. Variáveis: $aba (abertos|aprovacao|concluidos|minhas), $lista, $qtdMinhas, $sugestoes, $pode
 */
use App\Services\CompraService as C;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
$abas = ['abertos' => 'Abertos', 'aprovacao' => 'Esperando aprovação', 'concluidos' => 'Concluídos', 'minhas' => 'Para eu aprovar' . ($qtdMinhas ? " ($qtdMinhas)" : '')];
if (!$pode && !\App\Core\Auth::pode('compras.ver')) {
    $abas = ['minhas' => $abas['minhas']];
}
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMPRAS · QUALIDADE</div><h1>Pedidos de compra</h1>
    <p>Matéria-prima, rótulos, embalagens e itens avulsos: monte as cotações, escolha quem aprova e acompanhe até chegar.</p></div>
  <?php if ($pode): ?><div class="acoes"><a class="botao" href="<?= url('/compras/novo') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Novo orçamento</a></div><?php endif; ?>
</div>
<nav class="pr-abas" aria-label="Orçamentos"><?php foreach ($abas as $k => $n): ?><a href="<?= url('/compras?aba=' . $k) ?>" class="<?= $aba === $k ? 'ativo' : '' ?>"><?= e($n) ?></a><?php endforeach; ?></nav>

<?php if ($pode && $aba === 'abertos' && ($sugestoes['materias'] || $sugestoes['projetos'])): ?>
<section class="card cp-sugestoes" data-sugestoes>
  <h2>Sugestões para orçar</h2>
  <ul>
    <?php foreach ($sugestoes['materias'] as $m): $falta = max(0, $m['estoque_minimo'] * 2 - $m['estoque']); ?>
      <li><span><strong><?= e($m['nome']) ?></strong> <small class="suave">abaixo do mínimo: <?= e(P::qtd($m['estoque'], $m['unidade'])) ?> de <?= e(P::qtd($m['estoque_minimo'], $m['unidade'])) ?></small></span>
        <a class="botao contorno pequeno" href="<?= url('/compras/novo') . '?' . e(http_build_query(['tipo' => !empty($m['rotulo']) ? 'rotulo' : ($m['tipo'] === 'embalagem' ? 'embalagem' : 'materia_prima'), 'mp' => $m['id'], 'qtd' => rtrim(rtrim(number_format($falta, 3, ',', ''), '0'), ','), 'motivo' => 'Estoque abaixo do mínimo'])) ?>" data-sugestao-mp="<?= (int) $m['id'] ?>">Criar orçamento</a></li>
    <?php endforeach; ?>
    <?php foreach ($sugestoes['projetos'] as $pj): ?>
      <li><span><strong>Rótulos e insumos: <?= e($pj['nome']) ?></strong> <small class="suave">projeto do Desenvolvimento na etapa de compras</small></span>
        <a class="botao contorno pequeno" href="<?= url('/compras/novo') . '?' . e(http_build_query(['tipo' => 'rotulo', 'projeto' => $pj['id'], 'titulo' => 'Rótulo ' . $pj['nome'], 'motivo' => 'Produto novo (Desenvolvimento)'])) ?>">Criar orçamento</a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira" data-compras>
      <thead><tr><th>Orçamento</th><th>Situação</th><th class="direita">Qtd</th><th class="direita">Valor</th><th>Aprovação</th><th>Precisa até</th></tr></thead>
      <tbody>
      <?php foreach ($lista as $c): [$sn, $sc] = C::SITUACOES[$c['situacao']] ?? [$c['situacao'], 'cinza']; ?>
        <tr data-compra="<?= (int) $c['id'] ?>">
          <td><a href="<?= url('/compras/' . (int) $c['id']) ?>"><strong><?= e($c['titulo']) ?></strong></a>
            <small class="suave"><?= e(C::TIPOS[$c['tipo']] ?? $c['tipo']) ?><?= $c['projeto'] ? ' · projeto ' . e($c['projeto']) : '' ?> · <?= (int) $c['cotacoes'] ?> cotação(ões)</small></td>
          <td><span class="selo <?= $sc ?>"><?= e($sn) ?></span></td>
          <td class="direita"><?= e(P::qtd((float) $c['quantidade'], (string) $c['unidade'])) ?></td>
          <td class="direita"><?= $c['valor_escolhido'] !== null ? dinheiro((float) $c['valor_escolhido']) : ($c['menor_valor'] !== null ? '<small class="suave">a partir de</small> ' . dinheiro((float) $c['menor_valor']) : '—') ?></td>
          <td class="pequeno"><?= $c['aprovador'] ? e($c['aprovador']) : '—' ?></td>
          <td class="pequeno <?= $c['necessario_ate'] && $c['necessario_ate'] < date('Y-m-d') && !in_array($c['situacao'], ['recebido', 'cancelado', 'recusado'], true) ? 'pn-vermelho' : '' ?>"><?= $c['necessario_ate'] ? data_br($c['necessario_ate']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?><tr><td colspan="6" class="suave"><?= $aba === 'minhas' ? 'Nenhum orçamento esperando a sua aprovação.' : 'Nenhum orçamento aqui.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
