<?php
/**
 * Cabeçalho da Cobrança: título, números do topo, abas e filtros.
 * Variáveis: $aba ('funil'|'lista'), $filtros, $opcoes, $resumo
 */
use App\Core\Auth;

$icones = asset('img/icones.svg');
$query = http_build_query(array_filter($filtros));
$sel = fn(string $campo, $valor) => (string) ($filtros[$campo] ?? '') === (string) $valor ? 'selected' : '';
$n = fn($v) => number_format((int) $v, 0, ',', '.');
$linkLista = fn(array $extra) => url('/cobranca/lista') . '?' . e(http_build_query($extra));
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">FINANCEIRO · RECUPERAÇÃO DE CRÉDITO</div>
    <h1>Cobrança</h1>
    <p>Clientes com títulos em atraso. Registre tentativas e promessas; sem contato, busque contatos novos com IA.</p>
  </div>
  <div class="acoes">
    <?php if (Auth::pode('cobranca.gerenciar')): ?>
      <form method="post" action="<?= url('/cobranca/importar') ?>">
        <?= csrf_campo() ?>
        <button class="botao" type="submit"><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg>Ler títulos vencidos do ERP</button>
      </form>
    <?php endif; ?>
    <a class="botao contorno" href="<?= url('/cobranca/imprimir') . ($query ? '?' . e($query) : '') ?>" target="_blank"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</a>
    <a class="botao contorno" href="<?= url('/cobranca/exportar.csv') . ($query ? '?' . e($query) : '') ?>"><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>CSV</a>
  </div>
</div>

<div class="cob-numeros">
  <a class="cob-numero destaque" href="<?= url('/cobranca/lista') ?>">
    <span>Total em aberto</span><strong><?= dinheiro((float) $resumo['total']) ?></strong><small><?= $n($resumo['casos']) ?> caso(s) · <?= $n($resumo['meus']) ?> comigo</small>
  </a>
  <a class="cob-numero" href="<?= $linkLista(['faixa' => '31a60']) ?>"><span>Mais de 30 dias</span><strong><?= $n($resumo['mais30']) ?></strong><small><?= $n($resumo['mais60']) ?> com mais de 60</small></a>
  <a class="cob-numero alerta" href="<?= $linkLista(['faixa' => 'mais90']) ?>"><span>Mais de 90 dias</span><strong><?= $n($resumo['mais90']) ?></strong><small>risco de perda</small></a>
  <div class="cob-numero"><span>Promessas para hoje</span><strong><?= $n($resumo['promessas_hoje']) ?></strong><small>conferir pagamento</small></div>
  <div class="cob-numero"><span>Sem contato</span><strong><?= $n($resumo['sem_contato']) ?></strong><small>buscar contatos com IA</small></div>
  <div class="cob-numero verde"><span>Recuperado no mês</span><strong><?= dinheiro((float) $resumo['recuperado_mes']) ?></strong><small>títulos pagos</small></div>
</div>

<nav class="abas" aria-label="Visões da cobrança">
  <a href="<?= url('/cobranca') . ($query ? '?' . e($query) : '') ?>" class="<?= $aba === 'funil' ? 'ativa' : '' ?>">Funil</a>
  <a href="<?= url('/cobranca/lista') . ($query ? '?' . e($query) : '') ?>" class="<?= $aba === 'lista' ? 'ativa' : '' ?>">Lista</a>
</nav>

<form class="filtros card" method="get" action="<?= url($aba === 'funil' ? '/cobranca' : '/cobranca/lista') ?>" data-filtros>
  <div class="filtros-busca">
    <svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Buscar por nome ou CNPJ…" aria-label="Buscar">
  </div>
  <div class="filtros-grade">
    <?php if ($aba === 'lista'): ?>
      <select name="etapa" aria-label="Etapa">
        <option value="">Todas as etapas</option>
        <?php foreach ($opcoes['etapas'] as $et): ?><option value="<?= (int) $et['id'] ?>" <?= $sel('etapa', $et['id']) ?>><?= e($et['nome']) ?></option><?php endforeach; ?>
      </select>
      <select name="situacao" aria-label="Situação">
        <option value="">Casos abertos</option>
        <option value="encerrada" <?= $sel('situacao', 'encerrada') ?>>Casos encerrados</option>
      </select>
    <?php endif; ?>
    <select name="faixa" aria-label="Atraso">
      <option value="">Qualquer atraso</option>
      <?php foreach ($opcoes['faixas'] as $chave => [$rotulo]): ?><option value="<?= $chave ?>" <?= $sel('faixa', $chave) ?>><?= e($rotulo) ?></option><?php endforeach; ?>
    </select>
    <select name="responsavel" aria-label="Responsável">
      <option value="">Todos os responsáveis</option>
      <?php foreach ($opcoes['responsaveis'] as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $sel('responsavel', $r['id']) ?>><?= e($r['nome']) ?></option><?php endforeach; ?>
    </select>
    <select name="uf" aria-label="UF">
      <option value="">Todas as UFs</option>
      <?php foreach ($opcoes['ufs'] as $uf): ?><option <?= $sel('uf', $uf) ?>><?= e($uf) ?></option><?php endforeach; ?>
    </select>
    <label class="opcao"><input type="checkbox" name="minhas" value="1" <?= ($filtros['minhas'] ?? '') === '1' ? 'checked' : '' ?>> Só os meus</label>
  </div>
  <?php if (array_filter($filtros)): ?><a class="pequeno" href="<?= url($aba === 'funil' ? '/cobranca' : '/cobranca/lista') ?>">Limpar filtros</a><?php endif; ?>
</form>
