<?php
/**
 * Um roteiro: mapa, paradas por dia (visitar, pedido, Google Maps) e o assistente "lojas no caminho".
 * Variáveis: $r, $aba, $podeMexer, $soLeitura (dona de loja vendo só as dela), $paradas, $dias, $filtros, $caminho, $vendedores, $links
 */
use App\Core\Auth;
use App\Services\RoteiroService as R;

$semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
$nomeDia = fn(string $d) => $semana[(int) date('w', strtotime($d))] . ' ' . date('d/m', strtotime($d));
$porDia = [];
foreach ($paradas as $p) {
    $porDia[$p['dia'] ?? ''][] = $p;
}
$sit = ['pendente' => ['A visitar', ''], 'visitado' => ['Visitado', 'verde'], 'nao_atendeu' => ['Não atendeu', 'laranja']];
$pode = Auth::pode('pedidos.criar');
$mapa = [
    'rota' => array_map(fn($c) => ['nome' => $c['nome'], 'lat' => (float) $c['lat'], 'lng' => (float) $c['lng']], array_filter($r['cidades'], fn($c) => $c['lat'] !== null)),
    'paradas' => array_values(array_map(fn($p) => ['id' => (int) $p['id'], 'nome' => $p['nome'], 'lat' => $p['lat'] !== null ? (float) $p['lat'] : null,
        'lng' => $p['lng'] !== null ? (float) $p['lng'] : null, 'situacao' => $p['situacao'], 'dia' => $p['dia'] ? $nomeDia($p['dia']) : ''], $paradas)),
    'caminho' => $caminho ? array_values(array_map(fn($l) => ['id' => (int) $l['id'], 'nome' => $l['nome'], 'lat' => (float) $l['lat'], 'lng' => (float) $l['lng'],
        'sugerida' => $l['sugerida'], 'no_roteiro' => (bool) $l['no_roteiro']], array_slice($caminho['lojas'], 0, 800))) : [],
];
$linkAba = fn(string $aba, array $mais = []) => url('/roteiros/' . (int) $r['id']) . '?' . e(http_build_query(['aba' => $aba] + $mais));
?>
<a class="voltar-link" href="<?= url('/roteiros') ?>">← Planejador de visitas</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ROTEIRO · <?= e(mb_strtoupper($r['usuario_nome'])) ?></div><h1><?= e($r['nome']) ?></h1>
    <p><?= data_br($r['data_inicio']) ?><?= $r['data_fim'] !== $r['data_inicio'] ? ' a ' . data_br($r['data_fim']) : '' ?> ·
      <strong><?= e(implode(' → ', array_column($r['cidades'], 'nome'))) ?></strong> · lojas até <?= (int) $r['raio_km'] ?> km do caminho · <?= (int) $r['visitas_dia'] ?> visitas por dia
      <?= $r['situacao'] !== 'planejado' ? ' · <span class="selo">' . ($r['situacao'] === 'concluido' ? 'Concluído' : 'Cancelado') . '</span>' : '' ?></p>
    <?php if ($r['observacoes']): ?><p class="suave pequeno"><?= nl2br(e($r['observacoes'])) ?></p><?php endif; ?>
  </div>
  <?php if (!$soLeitura): ?>
    <div class="acoes">
      <?php if ($podeMexer): ?>
        <a class="botao contorno" href="<?= url('/roteiros/' . (int) $r['id'] . '/editar') ?>">Editar</a>
        <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/situacao') ?>" data-confirmar="Marcar o roteiro como concluído?"><?= csrf_campo() ?><input type="hidden" name="acao" value="concluir"><button class="botao contorno" type="submit">Concluir</button></form>
      <?php elseif (R::podeMexer($r)): ?>
        <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/situacao') ?>"><?= csrf_campo() ?><input type="hidden" name="acao" value="reabrir"><button class="botao contorno" type="submit">Reabrir</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($soLeitura): ?>
  <div class="aviso">Você está vendo só as lojas da <strong>sua carteira</strong> que estão neste roteiro de visitas. Pedidos fechados na visita são seus.</div>
<?php endif; ?>

<div id="rt-mapa" class="rt-mapa card" data-mapa-roteiro="<?= e(json_encode($mapa, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Mapa do roteiro"></div>

<?php if (!$soLeitura): ?>
  <nav class="abas" aria-label="Roteiro">
    <a href="<?= $linkAba('dias') ?>" class="<?= $aba === 'dias' ? 'ativa' : '' ?>">Paradas por dia <b class="contador"><?= count($paradas) ?></b></a>
    <?php if ($podeMexer): ?><a href="<?= $linkAba('caminho') ?>" class="<?= $aba === 'caminho' ? 'ativa' : '' ?>" data-aba-caminho>✨ Lojas no caminho</a><?php endif; ?>
  </nav>
<?php endif; ?>

<?php if ($aba === 'caminho' && $caminho !== null && $podeMexer): ?>
  <section class="card" data-caminho>
    <div class="rt-caminho-topo">
      <p style="margin:0">O One procurou as lojas a até <strong><?= (int) $r['raio_km'] ?> km</strong> do caminho, de <strong>todas as carteiras</strong>.
        <strong>Sugeridas</strong> são as mais importantes que cabem na viagem (<?= (int) $r['visitas_dia'] ?> por dia × <?= count($dias) ?> dia(s)):
        recompra atrasada, sem compra há 60/90 dias, quem mais compra por mês e leads ainda não abordados.</p>
      <form method="get" action="<?= url('/roteiros/' . (int) $r['id']) ?>" class="rt-filtros"><input type="hidden" name="aba" value="caminho">
        <select name="so" onchange="this.form.submit()" aria-label="Quais lojas"><option value="sugeridas" <?= $filtros['so'] === 'sugeridas' ? 'selected' : '' ?>>Só as sugeridas</option><option value="todas" <?= $filtros['so'] === 'todas' ? 'selected' : '' ?>>Todas no caminho</option></select>
        <select name="tipo" onchange="this.form.submit()" aria-label="Clientes ou leads"><option value="">Clientes e leads</option><option value="cliente" <?= $filtros['tipo'] === 'cliente' ? 'selected' : '' ?>>Só clientes</option><option value="lead" <?= $filtros['tipo'] === 'lead' ? 'selected' : '' ?>>Só leads</option></select>
        <select name="vendedor" onchange="this.form.submit()" aria-label="Carteira de"><option value="">Todas as carteiras</option><?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $filtros['vendedor'] === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select>
      </form>
    </div>
    <?php if ($caminho['sem_localizacao']): ?><p class="pequeno suave"><?= (int) $caminho['sem_localizacao'] ?> loja(s) dessas cidades ainda não têm localização no mapa e não aparecem aqui (use a busca abaixo para incluir).</p><?php endif; ?>
    <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/adicionar') ?>" data-form-caminho><?= csrf_campo() ?><input type="hidden" name="voltar" value="caminho">
      <div class="tabela-rolagem">
        <table class="tabela rt-tabela">
          <thead><tr><th><input type="checkbox" data-marcar-todas aria-label="Marcar todas"></th><th>Loja</th><th>Carteira de</th><th class="direita">Da rota</th><th>Por que visitar</th></tr></thead>
          <tbody>
          <?php foreach ($caminho['lojas'] as $l): ?>
            <tr class="<?= $l['no_roteiro'] ? 'rt-ja' : '' ?>" data-loja-caminho="<?= (int) $l['id'] ?>">
              <td><?php if (!$l['no_roteiro']): ?><input type="checkbox" name="clientes[]" value="<?= (int) $l['id'] ?>" <?= $l['sugerida'] ? 'checked' : '' ?> aria-label="Incluir <?= e($l['nome']) ?>"><?php else: ?>✓<?php endif; ?></td>
              <td><strong><?= e($l['nome']) ?></strong><?= $l['tipo'] === 'lead' ? ' <span class="selo">lead</span>' : '' ?><br><small class="suave"><?= e($l['cidade']) ?>/<?= e($l['uf']) ?><?= $l['aproximada'] ? ' · ponto aproximado' : '' ?></small></td>
              <td><?= e($l['vendedor'] ?? 'sem vendedor(a)') ?></td>
              <td class="direita"><?= number_format($l['distancia_km'], 1, ',', '.') ?> km</td>
              <td><?= $l['motivos'] ? e(implode(' · ', $l['motivos'])) : '<span class="suave">—</span>' ?><?= $l['no_roteiro'] ? ' <span class="selo verde">no roteiro</span>' : '' ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$caminho['lojas']): ?><tr><td colspan="5" class="suave">Nenhuma loja <?= $filtros['so'] === 'sugeridas' ? 'sugerida ' : '' ?>nesse caminho<?= $filtros['so'] === 'sugeridas' ? ' — veja "Todas no caminho" ou aumente a distância da rota (Editar)' : '' ?>.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($caminho['lojas']): ?><div class="acoes-formulario" style="margin-top:12px"><button class="botao" type="submit" data-incluir-marcadas>Incluir as marcadas no roteiro</button></div><?php endif; ?>
    </form>
    <div class="rt-busca">
      <label>Incluir outra loja (qualquer carteira)<input type="search" placeholder="Nome, cidade ou CNPJ…" data-busca-lojas="<?= url('/roteiros/buscar-lojas') ?>" autocomplete="off"></label>
      <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/adicionar') ?>" data-resultado-busca><?= csrf_campo() ?><input type="hidden" name="voltar" value="caminho"><ul></ul></form>
    </div>
  </section>
<?php else: ?>
  <?php if ($podeMexer && $paradas): ?>
    <div class="rt-barra">
      <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/organizar') ?>" data-confirmar="Reorganizar todas as paradas a visitar pela ordem do caminho? (o dia que você mudou à mão também é refeito)"><?= csrf_campo() ?>
        <button class="botao contorno pequeno" type="submit">↻ Reorganizar pelo caminho</button></form>
      <?php foreach ($links as $i => $l): ?><a class="botao contorno pequeno" href="<?= e($l) ?>" target="_blank" rel="noopener" data-google-maps>🧭 Rota no Google Maps<?= count($links) > 1 ? ' (parte ' . ($i + 1) . ')' : '' ?></a><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!$paradas): ?>
    <section class="card"><p class="suave">Nenhuma loja no roteiro ainda.<?= $podeMexer ? ' Abra <a href="' . $linkAba('caminho') . '">✨ Lojas no caminho</a> para o One sugerir.' : '' ?></p></section>
  <?php endif; ?>
  <?php foreach (array_merge($dias, ['']) as $dia): if (empty($porDia[$dia])) { continue; } $lista = $porDia[$dia]; ?>
    <section class="card rt-dia" data-dia="<?= e($dia) ?>">
      <header class="rt-dia-topo"><h2><?= $dia ? e(ucfirst($nomeDia($dia))) : 'Sem dia' ?> <small class="suave"><?= count($lista) ?> loja(s)</small></h2>
        <?php if ($dia && !$soLeitura): foreach (R::linksGoogleMaps($lista) as $l): ?><a class="pequeno" href="<?= e($l) ?>" target="_blank" rel="noopener">🧭 Rota do dia</a><?php endforeach; endif; ?></header>
      <ol class="rt-paradas">
        <?php foreach ($lista as $p): [$rotulo, $cor] = $sit[$p['situacao']] ?? [$p['situacao'], '']; $outra = (int) $p['responsavel_id'] !== (int) $r['usuario_id']; ?>
          <li class="rt-parada rt-<?= e($p['situacao']) ?>" id="parada-<?= (int) $p['id'] ?>" data-parada="<?= (int) $p['id'] ?>">
            <div class="rt-parada-info">
              <strong><a href="<?= url('/carteira/clientes/' . (int) $p['cliente_id']) ?>"><?= e($p['nome']) ?></a></strong>
              <span class="selo <?= $cor ?>"><?= $rotulo ?></span><?= $outra ? ' <span class="selo" title="A venda vai para a dona da carteira">carteira de ' . e($p['vendedor'] ?? '—') . '</span>' : '' ?>
              <small class="suave"><?= e(trim(($p['endereco'] ?? '') . ($p['bairro'] ? ' · ' . $p['bairro'] : ''))) ?> · <?= e($p['cidade']) ?>/<?= e($p['uf']) ?>
                <?= $p['ultima_compra_em'] ? ' · última compra ' . data_br($p['ultima_compra_em']) : ' · ainda não comprou' ?></small>
              <?php if ($p['pedido_id']): ?><small>🛒 <a href="<?= url('/pedidos/' . (int) $p['pedido_id']) ?>">Pedido #<?= (int) $p['pedido_id'] ?></a> · <?= dinheiro($p['pedido_total']) ?></small><?php endif; ?>
              <?php if ($p['observacao']): ?><small>📝 <?= e($p['observacao']) ?></small><?php endif; ?>
            </div>
            <?php if ($podeMexer): ?>
              <div class="rt-parada-acoes">
                <?php if ($pode && !$p['pedido_id']): ?><a class="botao pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $p['cliente_id'] ?>" data-pedido-parada>Novo pedido</a><?php endif; ?>
                <?php $tel = preg_replace('/\D/', '', (string) ($p['whatsapp'] ?: $p['telefone'])); if ($tel): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($tel) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <details class="rt-mais"><summary class="botao contorno pequeno">Registrar visita</summary>
                  <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/paradas/' . (int) $p['id'] . '/visita') ?>" class="rt-form-visita"><?= csrf_campo() ?>
                    <select name="situacao" aria-label="Como foi"><?php foreach (R::SITUACOES_PARADA as $k => $nome): ?><option value="<?= $k ?>" <?= $p['situacao'] === $k || ($p['situacao'] === 'pendente' && $k === 'visitado') ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select>
                    <input type="text" name="observacao" value="<?= e($p['observacao'] ?? '') ?>" maxlength="500" placeholder="Como foi? (vai para a linha do tempo)" aria-label="Observação">
                    <button class="botao pequeno" type="submit">Salvar</button>
                  </form>
                  <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/paradas/' . (int) $p['id'] . '/dia') ?>" class="rt-form-visita"><?= csrf_campo() ?>
                    <select name="dia" aria-label="Mudar o dia"><?php foreach ($dias as $d): ?><option value="<?= $d ?>" <?= $p['dia'] === $d ? 'selected' : '' ?>><?= e($nomeDia($d)) ?></option><?php endforeach; ?></select>
                    <button class="botao contorno pequeno" type="submit">Mudar o dia</button>
                  </form>
                  <?php if ($p['situacao'] === 'pendente' && !$p['pedido_id']): ?>
                    <form method="post" action="<?= url('/roteiros/' . (int) $r['id'] . '/paradas/' . (int) $p['id'] . '/remover') ?>" data-confirmar="Tirar esta loja do roteiro?"><?= csrf_campo() ?><button class="botao contorno pequeno rt-perigo" type="submit">Tirar do roteiro</button></form>
                  <?php endif; ?>
                </details>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
