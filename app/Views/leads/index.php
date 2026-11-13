<?php
/** Leads do site e dos anúncios. Variáveis: $dias, $resultado, $r (LeadService::resumo), $leads */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$rotulos = ['criado' => ['Novo lead', 'verde'], 'existente' => ['Já existia', 'laranja'], 'bloqueado' => ['Bloqueado (robô)', 'cinza'], 'invalido' => ['Inválido', 'vermelho']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">MARKETING · CAPTURA DE LEADS</div><h1>Leads do site</h1>
    <p>Formulários do site e dos anúncios. Cada lead entra no funil sozinho, com a campanha (UTM), e vai para a fila de novos leads.</p></div>
  <div class="acoes">
    <a class="botao contorno" href="<?= url('/seja-revendedor') ?>" target="_blank" rel="noopener"><svg class="icone"><use href="<?= $icones ?>#externo"/></svg>Página "Seja revendedor"</a>
    <a class="botao" href="<?= url('/leads/instalar') ?>"><svg class="icone"><use href="<?= $icones ?>#chave"/></svg>Instalar no site</a>
  </div>
</div>
<?php if (\App\Core\Auth::pode('prospeccao.usar')): ?><?= Visao::renderizar('prospeccao/_abas', ['atual' => 'leads']) ?><?php endif; ?>

<form class="filtros card" method="get" action="<?= url('/leads') ?>" data-filtros>
  <div class="filtros-grade">
    <select name="dias" aria-label="Período"><?php foreach ([7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 365 => 'Últimos 12 meses'] as $k => $nome): ?><option value="<?= $k ?>" <?= $dias === $k ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select>
    <select name="resultado" aria-label="Resultado"><option value="">Todos os resultados</option><?php foreach ($rotulos as $k => [$nome]): ?><option value="<?= $k ?>" <?= $resultado === $k ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select>
  </div>
</form>

<div class="bento">
  <section class="b-card b-escuro span-4"><header class="b-topo"><span>Leads recebidos</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#pessoas"/></svg></span></header>
    <strong class="b-numero-grande" data-leads-total><?= $n($r['novos'] + $r['existentes']) ?></strong>
    <p class="b-sub"><?= $n($r['novos']) ?> novos · <?= $n($r['existentes']) ?> já estavam na carteira</p></section>
  <section class="b-card b-kpi b-lima span-3"><header class="b-topo"><span>Viraram clientes</span></header><strong class="b-valor" data-leads-clientes><?= $n($r['clientes']) ?></strong>
    <small><?= $r['novos'] ? number_format($r['clientes'] / $r['novos'] * 100, 1, ',', '.') : '0' ?>% dos leads novos</small></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Bloqueados</span></header><strong class="b-valor"><?= $n($r['bloqueados'] + $r['invalidos']) ?></strong><small>robôs e envios inválidos</small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Período</span></header><strong class="b-valor"><?= $dias ?> dias</strong><small>até hoje</small></section>

  <section class="b-card span-12" data-card="campanhas"><header class="b-topo"><h2>Resultado por campanha</h2><span class="b-chip">utm_source · utm_campaign</span></header>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela" data-tabela-campanhas>
        <thead><tr><th>Fonte</th><th>Campanha</th><th>Tipo</th><th class="direita">Leads</th><th class="direita">Clientes</th><th>Conversão</th><th class="direita">Faturado</th></tr></thead>
        <tbody>
        <?php foreach ($r['campanhas'] as $c): $conv = $c['pessoas'] ? $c['clientes'] / $c['pessoas'] * 100 : 0; ?>
          <tr><td><strong><?= e($c['fonte']) ?></strong></td><td><?= e($c['campanha']) ?></td>
            <td><span class="selo <?= $c['origem'] === 'anuncio' ? 'laranja' : 'cinza' ?>"><?= $c['origem'] === 'anuncio' ? 'anúncio' : 'site' ?></span></td>
            <td class="direita"><?= $n($c['leads']) ?></td><td class="direita"><strong><?= $n($c['clientes']) ?></strong></td>
            <td><div class="ps-conv"><i><b style="width: <?= min(100, $conv) ?>%"></b></i><span><?= number_format($conv, 1, ',', '.') ?>%</span></div></td>
            <td class="direita"><?= dinheiro((float) $c['faturado']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$r['campanhas']): ?><tr><td colspan="7" class="vazio">Nenhum lead no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></section>

  <section class="b-card span-12"><header class="b-topo"><h2>Recebidos</h2><span class="b-chip">mais recentes primeiro</span></header>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela" data-tabela-leads>
        <thead><tr><th>Quando</th><th>Contato</th><th>Local</th><th>Campanha</th><th>Resultado</th><th>No funil</th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): [$rot, $cor] = $rotulos[$l['resultado']] ?? [$l['resultado'], 'cinza']; ?>
          <tr>
            <td class="pequeno"><?= data_br($l['criado_em'], true) ?></td>
            <td><?php if ($l['cliente_id']): ?><button class="nome-cliente" type="button" data-abrir-cliente="<?= (int) $l['cliente_id'] ?>"><?= e($l['empresa'] ?: $l['nome'] ?: '—') ?></button><?php else: ?><?= e($l['empresa'] ?: $l['nome'] ?: '—') ?><?php endif; ?>
              <small class="suave"><?= e($l['nome'] ?? '') ?></small></td>
            <td class="pequeno"><?= e(trim(($l['cidade'] ?? '') . ($l['uf'] ? '/' . $l['uf'] : ''), '/')) ?: '—' ?></td>
            <td class="pequeno"><?= e($l['utm_source'] ?? 'direto') ?><small class="suave"><?= e($l['utm_campaign'] ?? '') ?></small></td>
            <td><span class="selo <?= $cor ?>"><?= $rot ?></span><?= $l['motivo'] ? '<small class="suave">' . e($l['motivo']) . '</small>' : '' ?></td>
            <td class="pequeno"><?php if ($l['cliente_id']): ?><?= $l['cliente_tipo'] === 'cliente' ? '<span class="selo lima">cliente</span>' : e($l['etapa'] ?? '') ?><small class="suave"><?= e($l['responsavel'] ?? '') ?></small><?php else: ?>—<?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?><tr><td colspan="6" class="vazio">Nenhum lead no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></section>
</div>
<?= Visao::renderizar('carteira/_drawer') ?>
