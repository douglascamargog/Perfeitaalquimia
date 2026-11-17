<?php
/**
 * Ajustar o ponto da loja no mapa da Carteira: arraste o marcador até a loja e salve.
 * Variáveis: $c (cliente), $cidade (lat/lng do centro da cidade)
 */
$lat = $c['lat'] ?? ($cidade['lat'] ?? -29.95);
$lng = $c['lng'] ?? ($cidade['lng'] ?? -51.17);
$precisao = ['telhado' => 'no endereço exato (Google Maps)', 'rua' => 'na rua do endereço (Google Maps)', 'manual' => 'ajustado à mão',
    'nao_encontrado' => 'aproximado: o Google não achou o endereço com precisão'][$c['geo_precisao'] ?? ''] ?? ($c['lat'] ? 'exato' : 'aproximado no centro da cidade');
?>
<a class="voltar-link" href="<?= url('/carteira/clientes/' . (int) $c['id']) ?>">← <?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA · MAPA</div><h1>Localização no mapa</h1>
    <p><?= e(trim(($c['endereco'] ?? '') . ($c['bairro'] ? ', ' . $c['bairro'] : '') . ' · ' . $c['cidade'] . '/' . $c['uf'])) ?> — hoje: <strong data-geo-precisao><?= e($precisao) ?></strong>.</p></div>
</div>
<section class="card">
  <p class="pequeno suave" style="margin-top:0">Arraste o marcador até a porta da loja (aproxime com a rodinha do mouse ou dois dedos) e clique em <strong>Salvar</strong>.
    O ponto ajustado à mão não muda mais sozinho.</p>
  <div id="mapa-localizacao" class="mapa-localizacao" data-lat="<?= e((string) $lat) ?>" data-lng="<?= e((string) $lng) ?>" data-exato="<?= $c['lat'] ? '1' : '0' ?>" aria-label="Mapa para ajustar a localização da loja"></div>
  <form method="post" action="<?= url('/carteira/clientes/' . (int) $c['id'] . '/localizacao') ?>" class="acoes-formulario" data-form-localizacao style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <?= csrf_campo() ?>
    <input type="hidden" name="lat" value="<?= e((string) $lat) ?>" data-campo-lat>
    <input type="hidden" name="lng" value="<?= e((string) $lng) ?>" data-campo-lng>
    <button class="botao" type="submit" data-salvar-local>Salvar este ponto</button>
    <?php if (($c['geo_precisao'] ?? '') === 'manual'): ?>
      <button class="botao contorno" type="submit" name="automatico" value="1" data-confirmar="Voltar a localizar pelo endereço (Google Maps)?">Voltar ao automático</button>
    <?php endif; ?>
    <a class="botao contorno" href="<?= url('/carteira/clientes/' . (int) $c['id']) ?>">Cancelar</a>
  </form>
</section>
