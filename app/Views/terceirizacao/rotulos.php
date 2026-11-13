<?php
/** Terceirização › Rótulos. Variáveis: $itens, $so (só alertas), $movimentos, $alertas, $pode */
use App\Core\Visao;

$n = fn($v) => number_format((float) $v, 0, ',', '.');
$tipos = ['entrada' => ['Chegada', 'verde'], 'consumo' => ['Produção', 'cinza'], 'ajuste' => ['Ajuste', 'laranja']];
?>
<?= Visao::renderizar('terceirizacao/_abas', ['atual' => '/terceirizacao/rotulos', 'alertas' => $alertas, 'titulo2' => 'Rótulos dos clientes',
    'texto' => 'Estoque de rótulos de cada cliente. Entram quando chegam; saem sozinhos ao concluir a ordem de produção para o cliente (1 por unidade).']) ?>
<div class="te-filtro-situacao">
  <a href="<?= url('/terceirizacao/rotulos') ?>" class="botao <?= $so ? 'contorno' : '' ?> pequeno">Todos</a>
  <a href="<?= url('/terceirizacao/rotulos?situacao=alerta') ?>" class="botao <?= $so ? '' : 'contorno' ?> pequeno">Em alerta (<?= (int) $alertas ?>)</a>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira" data-tabela-rotulos>
      <thead><tr><th>Produto do cliente</th><th>Cliente</th><th class="direita">Estoque</th><th class="direita">Mínimo</th><th class="direita">Em ordens</th><?php if ($pode): ?><th>Registrar</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($itens as $t): $alerta = (int) $t['rotulos_estoque'] <= (int) $t['rotulos_minimo'] || (int) $t['rotulos_estoque'] < (int) $t['em_ordens']; ?>
        <tr data-rotulo="<?= (int) $t['id'] ?>">
          <td><strong><?= e($t['nome_rotulo']) ?></strong><?= $alerta ? ' <span class="selo vermelho">' . ((int) $t['rotulos_estoque'] < (int) $t['em_ordens'] ? 'não dá para as ordens' : 'abaixo do mínimo') . '</span>' : '' ?></td>
          <td class="pequeno"><?= e($t['cliente_nome']) ?></td>
          <td class="direita"><strong class="<?= $alerta ? 'pn-vermelho' : '' ?>" data-saldo><?= $n($t['rotulos_estoque']) ?></strong></td>
          <td class="direita"><?= $n($t['rotulos_minimo']) ?></td>
          <td class="direita"><?= $t['em_ordens'] ? $n($t['em_ordens']) : '—' ?></td>
          <?php if ($pode): ?>
          <td>
            <form method="post" action="<?= url('/terceirizacao/rotulos/' . (int) $t['id']) ?>" class="te-mov">
              <?= csrf_campo() ?>
              <select name="tipo" aria-label="Tipo"><option value="entrada">Chegaram</option><option value="ajuste">Contagem</option></select>
              <input type="number" name="quantidade" min="0" required placeholder="qtd" aria-label="Quantidade de rótulos de <?= e($t['nome_rotulo']) ?>">
              <input type="text" name="observacao" maxlength="255" placeholder="obs. (NF, quem enviou…)" aria-label="Observação">
              <button class="botao contorno pequeno" type="submit">Registrar</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="6" class="suave"><?= $so ? 'Nenhum rótulo em alerta.' : 'Nenhum produto de cliente cadastrado.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<section class="card">
  <h2>Últimos movimentos</h2>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Produto</th><th>Cliente</th><th>Movimento</th><th class="direita">Qtd</th><th class="direita">Saldo</th><th>Obs.</th></tr></thead>
      <tbody>
      <?php foreach ($movimentos as $m): [$tn, $tc] = $tipos[$m['tipo']] ?? [$m['tipo'], 'cinza']; ?>
        <tr><td class="pequeno"><?= data_br($m['criado_em'], true) ?></td><td><?= e($m['nome_rotulo']) ?></td><td class="pequeno"><?= e($m['cliente_nome']) ?></td>
          <td><span class="selo <?= $tc ?>"><?= $tn ?></span><?= $m['ordem_producao_id'] ? ' <a href="' . url('/producao/ordens/' . (int) $m['ordem_producao_id']) . '">OP ' . (int) $m['ordem_producao_id'] . '</a>' : '' ?></td>
          <td class="direita"><?= ($m['quantidade'] > 0 ? '+' : '') . $n($m['quantidade']) ?></td><td class="direita"><?= $n($m['saldo_apos']) ?></td>
          <td class="pequeno"><?= e($m['observacao'] ?? '') ?><?= $m['usuario'] ? ' · ' . e($m['usuario']) : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
