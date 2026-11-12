<?php
/**
 * Filtros da carteira (formulário GET: funciona até sem JavaScript; com JS, filtra ao mudar).
 * Variáveis: $filtros, $opcoes, $acao (endereço), $ordem (opcional)
 */
$sel = fn(string $campo, $valor) => (string) ($filtros[$campo] ?? '') === (string) $valor ? 'selected' : '';
$algumFiltro = (bool) array_filter($filtros);
?>
<form class="filtros card" method="get" action="<?= url($acao) ?>" data-filtros>
  <div class="filtros-busca">
    <svg class="icone"><use href="<?= asset('img/icones.svg') ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Buscar por nome, cidade, telefone ou CNPJ…" aria-label="Buscar">
  </div>
  <div class="filtros-grade">
    <select name="uf" aria-label="UF" data-recarregar>
      <option value="">Todas as UFs</option>
      <?php foreach ($opcoes['ufs'] as $uf): ?><option <?= $sel('uf', $uf) ?>><?= e($uf) ?></option><?php endforeach; ?>
    </select>
    <select name="cidade" aria-label="Cidade">
      <option value="">Todas as cidades</option>
      <?php foreach ($opcoes['cidades'] as $c): ?><option value="<?= e($c['cidade']) ?>" <?= $sel('cidade', $c['cidade']) ?>><?= e($c['cidade']) ?><?= $filtros['uf'] ? '' : ' / ' . e($c['uf']) ?></option><?php endforeach; ?>
    </select>
    <select name="regiao" aria-label="Região">
      <option value="">Todas as regiões</option>
      <?php foreach ($opcoes['regioes'] as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $sel('regiao', $r['id']) ?>><?= e($r['nome']) ?></option><?php endforeach; ?>
    </select>
    <select name="segmento" aria-label="Segmento">
      <option value="">Todos os segmentos</option>
      <?php foreach ($opcoes['segmentos'] as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $sel('segmento', $s['id']) ?>><?= e($s['nome']) ?></option><?php endforeach; ?>
    </select>
    <select name="etapa" aria-label="Etapa">
      <option value="">Todas as etapas</option>
      <?php foreach ($opcoes['etapas'] as $et): ?><option value="<?= (int) $et['id'] ?>" <?= $sel('etapa', $et['id']) ?>><?= e($et['nome']) ?></option><?php endforeach; ?>
    </select>
    <select name="prioridade" aria-label="Prioridade">
      <option value="">Todas as prioridades</option>
      <?php foreach ($opcoes['prioridades'] as $p): ?><option <?= $sel('prioridade', $p) ?>><?= e($p) ?></option><?php endforeach; ?>
    </select>
    <?php if ($opcoes['vendedores']): ?>
      <select name="responsavel" aria-label="Responsável">
        <option value="">Todos os responsáveis</option>
        <?php foreach ($opcoes['vendedores'] as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $sel('responsavel', $v['id']) ?>><?= e($v['nome']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <select name="origem" aria-label="Origem / campanha">
      <option value="">Todas as origens</option>
      <?php foreach ($opcoes['origens'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('origem', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?>
    </select>
    <select name="compra" aria-label="Situação de compra">
      <option value="">Qualquer situação de compra</option>
      <option value="nunca" <?= $sel('compra', 'nunca') ?>>Nunca comprou</option>
      <option value="ativo" <?= $sel('compra', 'ativo') ?>>Comprando (ativo)</option>
      <?php foreach ($opcoes['faixas'] as $dias): ?><option value="sem<?= (int) $dias ?>" <?= $sel('compra', 'sem' . (int) $dias) ?>>Sem compra há +<?= (int) $dias ?> dias</option><?php endforeach; ?>
    </select>
    <?php if (isset($ordem)): ?>
      <select name="ordem" aria-label="Ordenar por">
        <?php foreach (['nome' => 'Ordem: nome', 'cidade' => 'Ordem: cidade', 'prioridade' => 'Ordem: prioridade', 'contato' => 'Ordem: último contato', 'compra' => 'Ordem: última compra', 'recentes' => 'Ordem: alterados recentemente'] as $valor => $rotulo): ?>
          <option value="<?= $valor ?>" <?= $ordem === $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </div>
  <div class="filtros-acoes">
    <button class="botao pequeno" type="submit">Filtrar</button>
    <?php if ($algumFiltro): ?><a class="botao contorno pequeno" href="<?= url($acao) ?>">Limpar filtros</a><?php endif; ?>
  </div>
</form>
