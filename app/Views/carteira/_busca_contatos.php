<?php
/**
 * Botão "Buscar contatos com IA" + área onde aparecem as sugestões (preenchida pelo busca_contatos.js).
 * Usado na ficha do cliente (Carteira) e no caso de cobrança.
 * Variáveis: $clienteId, $destaque (bool: realça o botão quando o contato está desatualizado)
 */
use App\Core\Auth;
use App\Core\Config;
use App\Services\BuscaContatoService;

if (!Auth::pode('contatos.buscar_ia')) {
    return;
}
$icones = asset('img/icones.svg');
$demonstracao = Config::get('busca_ia.provedor', 'fake') !== 'claude';
?>
<div class="busca-ia <?= !empty($destaque) ? 'destaque' : '' ?>" data-busca-contatos="<?= (int) $clienteId ?>">
  <div class="busca-ia-topo">
    <img src="<?= asset('img/one/one-pensando-padrao.svg') ?>" alt="" width="44" height="44" class="busca-ia-one" data-one-img>
    <div>
      <strong>Contato desatualizado?</strong>
      <small>O One procura na internet telefone, WhatsApp, e-mail, Instagram e site desta empresa<?= $demonstracao ? ' (modo demonstração: contatos fictícios)' : '' ?>. Você escolhe o que salvar.</small>
    </div>
  </div>
  <?php if (BuscaContatoService::ativa()): ?>
    <div class="ficha-acoes">
      <button class="botao <?= !empty($destaque) ? 'lima' : 'contorno' ?> pequeno" type="button" data-buscar><svg class="icone"><use href="<?= $icones ?>#faisca"/></svg>Buscar contatos com IA</button>
      <button class="botao-link pequeno" type="button" data-ver-ultima>Ver última busca</button>
    </div>
  <?php else: ?>
    <p class="pequeno suave">A busca está desligada. Peça ao admin para ativar em Configurações › Busca de contatos (IA).</p>
  <?php endif; ?>
  <div class="busca-ia-resultado" data-resultado aria-live="polite"></div>
</div>
