<?php
/**
 * Terceirização › Produtos por cliente.
 * Variáveis: $clienteId, $itens, $editar, $clientes (de terceirização), $todosClientes, $nossos (produtos), $pode
 */
use App\Core\Visao;

$atualNome = '';
foreach ($todosClientes as $c) {
    if ((int) $c['id'] === (int) $clienteId) { $atualNome = $c['nome']; }
}
?>
<?= Visao::renderizar('terceirizacao/_abas', ['atual' => '/terceirizacao/produtos', 'titulo2' => 'Produtos por cliente',
    'texto' => 'O que cada cliente faz com a marca dele: um produto nosso com o rótulo dele ou uma fórmula própria (exclusiva, nunca aparece para outros clientes).']) ?>

<form class="filtros card" method="get" action="<?= url('/terceirizacao/produtos') ?>" data-filtros>
  <div class="filtros-grade">
    <select name="cliente" aria-label="Cliente" onchange="this.form.submit()">
      <option value="">Todos os clientes de terceirização</option>
      <?php foreach ($clientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $clienteId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></option><?php endforeach; ?>
    </select>
  </div>
</form>

<div class="te-grade">
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira" data-tabela-terc>
      <thead><tr><th>Produto (no rótulo do cliente)</th><th>Cliente</th><th>Tipo</th><th class="direita">Preço</th><th class="direita">Rótulos</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($itens as $t): ?>
        <tr class="<?= $t['ativo'] ? '' : 'inativo' ?>">
          <td><strong><?= $t['codigo_cliente'] ? '<span class="cod-produto">' . e($t['codigo_cliente']) . '</span> ' : '' ?><?= e($t['nome_rotulo']) ?></strong>
            <small class="suave"><?= $t['cliente_exclusivo_id'] ? 'Fórmula própria' : 'Base: ' . e($t['produto_nome']) ?><?= $t['embalagem'] ? ' · ' . e($t['embalagem']) : '' ?></small></td>
          <td class="pequeno"><?= e($t['cliente_nome']) ?></td>
          <td><span class="selo <?= $t['cliente_exclusivo_id'] ? 'laranja' : 'verde' ?>"><?= $t['cliente_exclusivo_id'] ? 'exclusivo' : 'nosso produto' ?></span></td>
          <td class="direita"><?= $t['preco_final'] !== null ? dinheiro((float) $t['preco_final']) . ($t['preco'] === null ? '<small class="suave">tabela</small>' : '') : '<span class="selo laranja">sem preço</span>' ?></td>
          <td class="direita"><strong class="<?= (int) $t['rotulos_estoque'] <= (int) $t['rotulos_minimo'] ? 'pn-vermelho' : '' ?>"><?= number_format((int) $t['rotulos_estoque'], 0, ',', '.') ?></strong><small class="suave">mín. <?= (int) $t['rotulos_minimo'] ?></small></td>
          <td><?php if ($pode): ?><a class="botao contorno pequeno" href="<?= url('/terceirizacao/produtos?cliente=' . (int) $t['cliente_id'] . '&editar=' . (int) $t['id']) ?>#form-terc">Editar</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="6" class="suave">Nenhum produto cadastrado<?= $atualNome ? ' para ' . e($atualNome) : '' ?>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($pode): ?>
<form method="post" action="<?= url('/terceirizacao/produtos' . ($editar ? '/' . (int) $editar['id'] : '')) ?>" class="card te-form" id="form-terc" data-form-terc>
  <?= csrf_campo() ?>
  <h2><?= $editar ? 'Editar produto do cliente' : 'Cadastrar produto do cliente' ?></h2>
  <?php if ($editar): ?>
    <p class="suave pequeno"><?= e($editar['cliente_nome']) ?> · <?= $editar['cliente_exclusivo_id'] ? 'fórmula própria' : 'base: ' . e($editar['produto_nome']) ?></p>
    <input type="hidden" name="cliente_id" value="<?= (int) $editar['cliente_id'] ?>">
  <?php else: ?>
    <div class="campo"><label for="te-cliente">Cliente</label>
      <select id="te-cliente" name="cliente_id" required>
        <option value="">Escolha o cliente…</option>
        <?php foreach ($todosClientes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $clienteId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?> — <?= e($c['cidade']) ?>/<?= e($c['uf']) ?></option><?php endforeach; ?>
      </select>
      <div class="ajuda">Pode ser de qualquer carteira (Televendas, Externo...): ele passa a aparecer na Terceirização.</div></div>
    <div class="campo te-tipo">
      <label class="opcao"><input type="radio" name="tipo" value="nosso" checked data-tipo> <span><strong>Nosso produto com a marca do cliente</strong><small class="suave">Mesma ficha técnica do nosso produto</small></span></label>
      <label class="opcao"><input type="radio" name="tipo" value="proprio" data-tipo> <span><strong>Fórmula própria do cliente</strong><small class="suave">Produto exclusivo dele; a ficha técnica se cadastra na Produção</small></span></label>
    </div>
    <div class="campo" data-so-nosso><label for="te-produto">Nosso produto</label>
      <select id="te-produto" name="produto_id">
        <option value="">Escolha…</option>
        <?php foreach ($nossos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= $p['codigo'] ? e($p['codigo']) . ' · ' : '' ?><?= e($p['nome']) ?><?= $p['embalagem'] ? ' (' . e($p['embalagem']) . ')' : '' ?></option><?php endforeach; ?>
      </select></div>
    <div class="campo" data-so-proprio hidden><label for="te-embalagem">Embalagem</label><input type="text" id="te-embalagem" name="embalagem" maxlength="120" placeholder="Ex.: 60 cápsulas"></div>
  <?php endif; ?>
  <div class="grade-campos">
    <div class="campo"><label for="te-nome">Nome no rótulo do cliente</label><input type="text" id="te-nome" name="nome_rotulo" maxlength="160" required value="<?= e($editar['nome_rotulo'] ?? '') ?>"></div>
    <div class="campo"><label for="te-codigo">Código do cliente</label><input type="text" id="te-codigo" name="codigo_cliente" maxlength="40" value="<?= e($editar['codigo_cliente'] ?? '') ?>"></div>
    <div class="campo"><label for="te-preco">Preço (opcional)</label><input type="text" id="te-preco" name="preco" inputmode="decimal" placeholder="vazio = tabela Terceirização" value="<?= isset($editar['preco']) && $editar['preco'] !== null ? e(number_format((float) $editar['preco'], 2, ',', '.')) : '' ?>"></div>
    <div class="campo"><label for="te-minimo">Rótulos: estoque mínimo</label><input type="number" id="te-minimo" name="rotulos_minimo" min="0" value="<?= (int) ($editar['rotulos_minimo'] ?? 0) ?>"></div>
  </div>
  <div class="campo"><label for="te-obs">Observações</label><textarea id="te-obs" name="observacoes" rows="2"><?= e($editar['observacoes'] ?? '') ?></textarea></div>
  <?php if ($editar): ?><label class="opcao"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" <?= $editar['ativo'] ? 'checked' : '' ?>> Ativo</label><?php endif; ?>
  <div class="acoes-form"><button class="botao" type="submit"><?= $editar ? 'Salvar' : 'Cadastrar' ?></button>
    <?php if ($editar): ?><a class="botao contorno" href="<?= url('/terceirizacao/produtos?cliente=' . (int) $editar['cliente_id']) ?>">Cancelar</a><?php endif; ?></div>
</form>
<script>
  // Mostra o campo certo para "nosso produto" ou "fórmula própria"
  document.querySelectorAll('[data-tipo]').forEach((r) => r.addEventListener('change', () => {
    const proprio = document.querySelector('[data-tipo][value=proprio]').checked;
    document.querySelector('[data-so-nosso]').hidden = proprio;
    document.querySelector('[data-so-proprio]').hidden = !proprio;
  }));
</script>
<?php endif; ?>
</div>
