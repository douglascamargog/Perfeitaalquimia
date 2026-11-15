<?php
/**
 * Criar / editar campanha. Ao lado, a prévia do catálogo com as cores escolhidas.
 * Variáveis: $c (campanha com faixas e produtos), $pode, $tabelas, $regioes, $setores, $ufs, $produtos, $padrao (visual padrão)
 */
use App\Core\Visao;
use App\Services\CampanhaService as C;

$dis = $pode ? '' : 'disabled';
$valores = array_filter(array_map('trim', explode(',', (string) $c['publico_valores'])), 'strlen');
$v = C::visual([$c + ['banner' => $c['banner'] ?? null]]);
$num = fn($x) => $x === '' || $x === null ? '' : rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',');
$listas = [
    'tabela' => array_map(fn($t) => [(string) $t['id'], $t['nome']], $tabelas),
    'regiao' => array_map(fn($r) => [(string) $r['id'], $r['nome']], $regioes),
    'uf'     => array_map(fn($u) => [$u, $u], $ufs),
    'setor'  => array_map(fn($s) => [(string) $s['id'], $s['nome']], $setores),
];
$faixas = $c['faixas'] ?: [['a_partir_de' => '', 'tipo' => 'desconto', 'desconto_pct' => '', 'brinde' => '', 'brinde_midia_id' => null, 'brinde_imagem' => null]];
$promos = $c['produtos'] ?: [['produto_id' => '', 'desconto_pct' => '', 'destaque' => 0]];
$hoje = new DateTimeImmutable('today');
$atalhos = [
    'Este mês' => [$hoje->format('Y-m-01'), $hoje->format('Y-m-t')],
    'Próximo mês' => [$hoje->modify('first day of next month')->format('Y-m-d'), $hoje->modify('last day of next month')->format('Y-m-d')],
    'Esta semana' => [$hoje->modify('monday this week')->format('Y-m-d'), $hoje->modify('sunday this week')->format('Y-m-d')],
    'Próxima semana' => [$hoje->modify('monday next week')->format('Y-m-d'), $hoje->modify('sunday next week')->format('Y-m-d')],
];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/campanhas') ?>">COMERCIAL · CAMPANHAS</a></div>
    <h1><?= $c['id'] ? e($c['nome']) : 'Nova campanha' ?></h1>
    <?php if ($c['id']): [$r, $cor] = ['no_ar' => ['No ar', 'verde'], 'agendada' => ['Agendada', 'azul'], 'encerrada' => ['Encerrada', 'cinza'], 'desligada' => ['Desligada', 'cinza']][C::situacao($c)]; ?>
      <p><span class="selo <?= $cor ?>"><?= $r ?></span> <?= e(C::periodo($c)) ?> · <?= e(C::publicoTexto($c)) ?></p><?php endif; ?></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/campanhas') ?>">← Campanhas</a></div>
</div>

<form class="cp-form" method="post" action="<?= url($c['id'] ? '/campanhas/' . (int) $c['id'] : '/campanhas') ?>" enctype="multipart/form-data" data-cp-form>
  <?= csrf_campo() ?>
  <div class="cp-col">
    <section class="card cp-campos">
      <h2>1. Nome e período</h2>
      <div class="cp-linha">
        <div class="campo cp-largo"><label for="c-nome">Nome da campanha (interno)</label><input type="text" id="c-nome" name="nome" maxlength="120" required value="<?= e($c['nome']) ?>" placeholder="Ex.: Campanha de março" <?= $dis ?>></div>
        <label class="opcao cp-ligada"><input type="checkbox" name="ativa" value="1" <?= (int) $c['ativa'] ? 'checked' : '' ?> <?= $dis ?>> Ligada</label>
      </div>
      <div class="cp-atalhos" role="group" aria-label="Períodos prontos">
        <?php foreach ($atalhos as $nome => [$i, $f]): ?><button type="button" class="botao contorno pequeno" data-periodo="<?= $i ?>|<?= $f ?>" <?= $dis ?>><?= $nome ?></button><?php endforeach; ?>
      </div>
      <div class="cp-linha">
        <div class="campo"><label for="c-inicio">Começa em</label><input id="c-inicio" type="date" name="inicio" required value="<?= e($c['inicio']) ?>" <?= $dis ?>></div>
        <div class="campo"><label for="c-fim">Termina em</label><input id="c-fim" type="date" name="fim" required value="<?= e($c['fim']) ?>" <?= $dis ?>></div>
      </div>
    </section>

    <section class="card cp-campos">
      <h2>2. Para quem vale</h2>
      <div class="campo"><label for="c-publico">Público</label>
        <select id="c-publico" name="publico" data-publico <?= $dis ?>>
          <?php foreach (C::PUBLICOS as $k => $nome): ?><option value="<?= $k ?>" <?= $c['publico'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?>
        </select></div>
      <?php foreach ($listas as $tipo => $opcoes): ?>
        <div class="cp-publico-lista" data-publico-lista="<?= $tipo ?>" <?= $c['publico'] === $tipo ? '' : 'hidden' ?>>
          <?php foreach ($opcoes as [$valor, $nome]): ?>
            <label class="cp-check"><input type="checkbox" name="publico_valores[]" value="<?= e($valor) ?>" <?= $c['publico'] === $tipo && in_array($valor, $valores, true) ? 'checked' : '' ?> <?= $c['publico'] === $tipo ? '' : 'disabled' ?> <?= $dis ?>> <?= e($nome) ?></label>
          <?php endforeach; ?>
          <?php if (!$opcoes): ?><p class="pequeno suave">Nada cadastrado ainda.</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="card cp-campos">
      <h2>3. Faixas pelo valor do pedido</h2>
      <p class="pequeno suave">O cliente e a vendedora veem quanto falta para a próxima faixa ("Faltam R$ 200 para você ganhar 10% de desconto"). O valor conta com os preços promocionais.</p>
      <div class="cp-faixas" data-lista="faixas">
        <?php foreach ($faixas as $k => $f): ?>
          <div class="cp-faixa" data-linha>
            <label>A partir de (R$)<input type="text" name="faixa_valor[<?= $k ?>]" inputmode="decimal" value="<?= e($num($f['a_partir_de'])) ?>" placeholder="2.000" data-faixa-valor <?= $dis ?>></label>
            <label>Ganha<select name="faixa_tipo[<?= $k ?>]" data-faixa-tipo <?= $dis ?>>
              <?php foreach (C::TIPOS_FAIXA as $t => $nome): ?><option value="<?= $t ?>" <?= $f['tipo'] === $t ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select></label>
            <label data-so="desconto">Desconto (%)<input type="text" name="faixa_pct[<?= $k ?>]" inputmode="decimal" value="<?= e($num($f['desconto_pct'])) ?>" placeholder="10" <?= $dis ?>></label>
            <label data-so="brinde" class="cp-brinde">Brinde<input type="text" name="faixa_brinde[<?= $k ?>]" maxlength="120" value="<?= e((string) $f['brinde']) ?>" placeholder="Ex.: Bicicleta" <?= $dis ?>></label>
            <label data-so="brinde" class="cp-foto">Foto do brinde
              <?php if (!empty($f['brinde_imagem'])): ?><img src="<?= url('/' . e($f['brinde_imagem'])) ?>" alt=""><?php endif; ?>
              <input type="file" name="faixa_imagem[<?= $k ?>]" accept="image/png,image/jpeg,image/webp" <?= $dis ?>>
              <input type="hidden" name="faixa_brinde_midia[<?= $k ?>]" value="<?= (int) ($f['brinde_midia_id'] ?? 0) ?: '' ?>"></label>
            <?php if ($pode): ?><button type="button" class="cp-tirar" data-tirar aria-label="Tirar faixa">×</button><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($pode): ?><button type="button" class="botao contorno pequeno" data-mais="faixas">+ Faixa</button><?php endif; ?>
    </section>

    <section class="card cp-campos">
      <h2>4. Produtos em promoção</h2>
      <p class="pequeno suave">Desconto em % sobre o preço de cada tabela (Lojista, Distribuidor…). "Destaque" aparece grande no alto do catálogo.</p>
      <div class="cp-promos" data-lista="promos">
        <?php foreach ($promos as $k => $p): ?>
          <div class="cp-promo" data-linha>
            <label class="cp-largo">Produto<select name="promo_produto[<?= $k ?>]" <?= $dis ?>><option value="">— escolha</option>
              <?php foreach ($produtos as $pr): ?><option value="<?= (int) $pr['id'] ?>" <?= (int) $p['produto_id'] === (int) $pr['id'] ? 'selected' : '' ?>><?= e(trim(($pr['codigo'] ? $pr['codigo'] . ' · ' : '') . $pr['nome'] . ' ' . $pr['embalagem'])) ?></option><?php endforeach; ?>
            </select></label>
            <label>Desconto (%)<input type="text" name="promo_pct[<?= $k ?>]" inputmode="decimal" value="<?= e($num($p['desconto_pct'])) ?>" placeholder="15" <?= $dis ?>></label>
            <label class="opcao"><input type="checkbox" name="promo_destaque[<?= $k ?>]" value="1" <?= !empty($p['destaque']) ? 'checked' : '' ?> <?= $dis ?>> Destaque</label>
            <?php if ($pode): ?><button type="button" class="cp-tirar" data-tirar aria-label="Tirar produto">×</button><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($pode): ?><button type="button" class="botao contorno pequeno" data-mais="promos">+ Produto</button><?php endif; ?>
    </section>

    <section class="card cp-campos">
      <h2>5. Desconto da vendedora</h2>
      <label class="opcao"><input type="checkbox" name="soma_desconto_vendedor" value="1" <?= (int) $c['soma_desconto_vendedor'] ? 'checked' : '' ?> <?= $dis ?>>
        O desconto da faixa soma com o desconto que a vendedora dá no pedido</label>
      <p class="pequeno suave">Desmarcado: se a vendedora der desconto, o desconto da faixa não entra (brinde e frete grátis continuam valendo).</p>
    </section>

    <section class="card cp-campos">
      <h2>6. Visual no catálogo</h2>
      <div class="campo"><label for="c-titulo">Título grande</label><input type="text" id="c-titulo" name="titulo" maxlength="120" value="<?= e((string) $c['titulo']) ?>" placeholder="Ex.: Ofertas de março" data-prev="titulo" <?= $dis ?>></div>
      <div class="campo"><label for="c-chamada">Frase abaixo do título</label><textarea id="c-chamada" name="chamada" rows="2" maxlength="255" data-prev="chamada" <?= $dis ?>><?= e((string) $c['chamada']) ?></textarea></div>
      <div class="campo"><label for="c-selo">Selo inclinado (curto)</label><input type="text" id="c-selo" name="selo" maxlength="30" value="<?= e((string) $c['selo']) ?>" placeholder="Ex.: até 15% OFF" data-prev="selo" <?= $dis ?>></div>
      <?= Visao::renderizar('campanhas/_cores', ['prim' => (string) $c['cor_primaria'], 'dest' => (string) $c['cor_destaque'], 'tema' => $c['tema'], 'pode' => $pode]) ?>
      <div class="campo"><label for="c-banner">Imagem do banner (opcional)</label>
        <input id="c-banner" type="file" name="banner" accept="image/png,image/jpeg,image/webp" data-prev-banner <?= $dis ?>>
        <small class="suave">Sem imagem, o banner usa as cores e a foto dos produtos em destaque. Ideal: 1200 × 700 px.</small>
        <?php if (!empty($c['banner_midia_id'])): ?><label class="opcao"><input type="checkbox" name="tirar_banner" value="1" <?= $dis ?>> Tirar a imagem atual</label><?php endif; ?></div>
    </section>

    <?php if ($pode): ?><div class="cp-salvar"><button class="botao" type="submit">Salvar campanha</button></div><?php endif; ?>
  </div>
  <?= Visao::renderizar('campanhas/_previa', ['v' => $v, 'faixas' => $c['faixas']]) ?>
</form>
