<?php
/** Equipe: diretório. Variáveis: $busca, $pessoas */
use App\Services\EquipeService;
use App\Services\PessoaService;

$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">EMPRESA · EQUIPE</div><h1>Equipe</h1><p>Conheça quem trabalha com você. Clique para ver o perfil de cada um.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/meu-perfil#perfil-publico') ?>">Editar o meu perfil</a></div>
</div>
<form class="filtros card" method="get" action="<?= url('/pessoas') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome ou setor…" aria-label="Buscar pessoa"></div>
</form>
<div class="pe-grade">
  <?php foreach ($pessoas as $p): ?>
    <a class="pe-cartao" href="<?= url('/pessoas/' . (int) $p['id']) ?>" data-pessoa="<?= (int) $p['id'] ?>">
      <span class="pe-cartao-capa" <?= $p['capa'] ? 'style="background-image:url(\'' . e(url('/' . $p['capa'])) . '\')"' : '' ?>></span>
      <span class="pe-cartao-foto"><?= EquipeService::avatar($p, 72) ?></span>
      <strong><?= e($p['nome']) ?></strong>
      <small><?= e($p['funcoes'] ?: '—') ?></small>
      <?php if ($p['sobre_mim']): ?><span class="pe-cartao-sobre"><?= e(mb_strimwidth($p['sobre_mim'], 0, 90, '…')) ?></span><?php endif; ?>
      <?php if ($p['data_nascimento']): ?><span class="pe-cartao-aniver">🎂 <?= e(PessoaService::aniversario($p['data_nascimento'])) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
