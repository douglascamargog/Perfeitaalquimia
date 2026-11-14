<?php
/**
 * Produção › Rótulos: estoque de rótulos num lugar só.
 *  - Nossos produtos: embalagens marcadas como rótulo (entram na compra/chegada, saem sozinhas na ordem de produção pela ficha técnica);
 *  - Clientes (terceirização): rótulos de cada cliente (saem sozinhos ao concluir a ordem para o cliente).
 * Variáveis: $nossos, $clientes, $so, $alertas, $alertaCliente, $movimentos, $podeNossos, $podeClientes
 */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$tipos = ['entrada' => ['Chegada', 'verde'], 'consumo' => ['Produção', 'cinza'], 'ajuste' => ['Contagem', 'laranja']];
$form = fn(string $acao, string $nome) => '<form method="post" action="' . url($acao) . '" class="te-mov">' . csrf_campo()
    . '<select name="tipo" aria-label="Tipo"><option value="entrada">Chegaram</option><option value="ajuste">Contagem</option></select>'
    . '<input type="number" name="quantidade" min="0" required placeholder="qtd" aria-label="Quantidade de rótulos de ' . e($nome) . '">'
    . '<input type="text" name="observacao" maxlength="255" placeholder="obs. (NF, gráfica…)" aria-label="Observação">'
    . '<button class="botao contorno pequeno" type="submit">Registrar</button></form>';
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · RÓTULOS</div><h1>Estoque de rótulos</h1>
    <p>Rótulos dos nossos produtos e dos clientes de terceirização. Entram quando chegam da gráfica; saem sozinhos ao concluir a ordem de produção.</p></div>
  <div class="acoes nao-imprimir"><button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button></div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/rotulos']) ?>
<div class="te-filtro-situacao nao-imprimir">
  <a href="<?= url('/producao/rotulos') ?>" class="botao <?= $so ? 'contorno' : '' ?> pequeno">Todos</a>
  <a href="<?= url('/producao/rotulos?situacao=alerta') ?>" class="botao <?= $so ? '' : 'contorno' ?> pequeno">Em alerta (<?= (int) $alertas ?>)</a>
</div>

<section class="card sem-espaco">
  <h2 class="pr-titulo-tabela">Nossos produtos</h2>
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira" data-rotulos-nossos>
      <thead><tr><th>Rótulo</th><th class="direita">Estoque</th><th class="direita">Mínimo</th><th class="direita">Em ordens</th><th>Acaba em</th><?php if ($podeNossos): ?><th class="nao-imprimir">Registrar</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($nossos as $m): ?>
        <tr data-rotulo-nosso="<?= (int) $m['id'] ?>">
          <td><a href="<?= url('/producao/materias/' . (int) $m['id']) ?>"><strong><?= e($m['nome']) ?></strong></a>
            <?= $m['alerta'] ? ' <span class="selo vermelho">' . ($m['estoque'] < $m['em_ordens'] ? 'não dá para as ordens' : 'abaixo do mínimo') . '</span>' : '' ?></td>
          <td class="direita"><strong class="<?= $m['alerta'] ? 'pn-vermelho' : '' ?>" data-saldo><?= $n($m['estoque']) ?></strong></td>
          <td class="direita"><?= $n($m['estoque_minimo']) ?></td>
          <td class="direita"><?= $m['em_ordens'] ? $n($m['em_ordens']) : '—' ?></td>
          <td class="pequeno"><?= $m['acaba_em'] ? data_br($m['acaba_em']) : '—' ?></td>
          <?php if ($podeNossos): ?><td class="nao-imprimir"><?= $form('/producao/rotulos/nosso/' . (int) $m['id'], $m['nome']) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$nossos): ?><tr><td colspan="6" class="suave"><?= $so ? 'Nenhum rótulo em alerta.' : 'Nenhum rótulo cadastrado. Marque "É rótulo de produto" no cadastro da embalagem em Matérias-primas (os rótulos que chegam pelo Desenvolvimento de Produto já entram aqui).' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card sem-espaco">
  <h2 class="pr-titulo-tabela">Clientes (terceirização)</h2>
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira" data-rotulos-clientes>
      <thead><tr><th>Produto do cliente</th><th>Cliente</th><th class="direita">Estoque</th><th class="direita">Mínimo</th><th class="direita">Em ordens</th><?php if ($podeClientes): ?><th class="nao-imprimir">Registrar</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($clientes as $t): $alerta = $alertaCliente($t); ?>
        <tr data-rotulo-cliente="<?= (int) $t['id'] ?>">
          <td><strong><?= e($t['nome_rotulo']) ?></strong><?= $alerta ? ' <span class="selo vermelho">' . ((int) $t['rotulos_estoque'] < (int) $t['em_ordens'] ? 'não dá para as ordens' : 'abaixo do mínimo') . '</span>' : '' ?></td>
          <td class="pequeno"><?= e($t['cliente_nome']) ?></td>
          <td class="direita"><strong class="<?= $alerta ? 'pn-vermelho' : '' ?>" data-saldo><?= $n($t['rotulos_estoque']) ?></strong></td>
          <td class="direita"><?= $n($t['rotulos_minimo']) ?></td>
          <td class="direita"><?= $t['em_ordens'] ? $n($t['em_ordens']) : '—' ?></td>
          <?php if ($podeClientes): ?><td class="nao-imprimir"><?= $form('/producao/rotulos/cliente/' . (int) $t['id'], $t['nome_rotulo']) ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clientes): ?><tr><td colspan="6" class="suave"><?= $so ? 'Nenhum rótulo de cliente em alerta.' : 'Nenhum produto de cliente cadastrado.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Últimos movimentos</h2>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Rótulo</th><th>Cliente</th><th>Movimento</th><th class="direita">Qtd</th><th class="direita">Saldo</th><th>Obs.</th></tr></thead>
      <tbody>
      <?php foreach ($movimentos as $m): [$tn, $tc] = $tipos[$m['tipo']] ?? [$m['tipo'], 'cinza']; ?>
        <tr><td class="pequeno"><?= data_br($m['criado_em'], true) ?></td><td><?= e($m['nome']) ?></td><td class="pequeno"><?= $m['cliente'] ? e($m['cliente']) : '<span class="suave">nosso</span>' ?></td>
          <td><span class="selo <?= $tc ?>"><?= $tn ?></span><?= $m['ordem_id'] ? ' <a href="' . url('/producao/ordens/' . (int) $m['ordem_id']) . '">OP ' . (int) $m['ordem_id'] . '</a>' : '' ?></td>
          <td class="direita"><?= ($m['quantidade'] > 0 ? '+' : '') . $n($m['quantidade']) ?></td><td class="direita"><?= $n($m['saldo']) ?></td>
          <td class="pequeno"><?= e($m['observacao'] ?? '') ?><?= $m['usuario'] ? ' · ' . e($m['usuario']) : '' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$movimentos): ?><tr><td colspan="7" class="suave">Nenhum movimento ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
