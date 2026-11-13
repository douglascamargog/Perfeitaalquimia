<?php
/**
 * Prospecção › Encontrar empresas (base de CNPJs).
 * Variáveis: $f (filtros), $r (itens, total, pagina, paginas), $resumo, $cnaes, $ufs, $bairros, $podeEscolher, $vendedores
 */
use App\Core\Visao;
use App\Services\Normalizar;
use App\Services\ProspeccaoService as P;

$icones = asset('img/icones.svg');
$podeEnviar = \App\Core\Auth::pode('prospeccao.usar'); // só "Ver": consulta a base, sem enviar para o funil
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$query = fn(array $extra = []) => '?' . http_build_query(array_filter(['uf' => $f['uf'], 'cidade' => $f['cidade'], 'bairro' => $f['bairro'], 'cnae' => $f['cnae'],
    'meses' => $f['meses'] ?: '', 'q' => $f['q'], 'esconder' => $f['esconder_carteira'] ? '1' : ''] + $extra, fn($v) => $v !== '' && $v !== null));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL · PROSPECÇÃO</div><h1>Encontrar empresas</h1>
    <p>Empresas ativas dos CNAEs do nosso público. Escolha a cidade, marque as que interessam e envie para o funil: elas entram na fila "Para abordar".</p></div>
</div>
<?= Visao::renderizar('prospeccao/_abas', ['atual' => 'empresas']) ?>
<div class="ps-faixa">Amostra <strong>simulada</strong> da base de CNPJs do RS. A base real da Receita Federal entra numa próxima fase, nesta mesma tela.</div>

<div class="bento">
  <section class="b-card b-escuro span-4"><header class="b-topo"><span>Empresas na base · <?= e($f['uf']) ?></span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg></span></header>
    <strong class="b-numero-grande" data-total-base><?= $n($resumo['empresas']) ?></strong>
    <p class="b-sub"><?= count($cnaes) ?> CNAE(s) do nosso público · <?= count($resumo['cidades']) ?> cidades</p></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Já na carteira</span></header><strong class="b-valor" data-na-carteira><?= $n($resumo['na_carteira']) ?></strong><small>clientes e leads com o mesmo CNPJ</small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Cobertura</span></header><strong class="b-valor"><?= number_format($resumo['cobertura'], 1, ',', '.') ?>%</strong><small>da base já trabalhada</small></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Abertas nos últimos 12 meses</span></header><strong class="b-valor"><?= $n($resumo['novas_12m']) ?></strong>
    <small><a href="<?= url('/prospeccao/empresas') . e($query(['meses' => 12])) ?>">ver empresas novas →</a></small></section>
</div>

<div class="ps-grade">
  <aside class="card ps-cidades">
    <h2>Cobertura por cidade</h2>
    <p class="suave pequeno">Quanto da base de cada cidade já está na carteira. Clique para filtrar.</p>
    <ul>
      <?php foreach ($resumo['cidades'] as $c): ?>
        <li class="<?= $f['cidade'] === $c['cidade'] ? 'ativo' : '' ?>">
          <a href="<?= url('/prospeccao/empresas') . e($query(['cidade' => $c['cidade'], 'bairro' => null, 'pagina' => null])) ?>" data-cidade="<?= e($c['cidade']) ?>">
            <span><?= e($c['cidade']) ?></span><small><?= $n($c['na_carteira']) ?>/<?= $n($c['empresas']) ?></small>
            <i class="ps-barra"><b style="width: <?= min(100, $c['cobertura']) ?>%"></b></i>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <div class="ps-principal">
    <form class="filtros card" method="get" action="<?= url('/prospeccao/empresas') ?>" data-filtros>
      <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nome da empresa ou CNPJ…" aria-label="Buscar"></div>
      <div class="filtros-grade">
        <select name="uf" aria-label="UF"><?php foreach ($ufs as $uf): ?><option <?= $f['uf'] === $uf ? 'selected' : '' ?>><?= e($uf) ?></option><?php endforeach; ?></select>
        <select name="cidade" aria-label="Cidade"><option value="">Todas as cidades</option>
          <?php foreach ($resumo['cidades'] as $c): ?><option <?= $f['cidade'] === $c['cidade'] ? 'selected' : '' ?>><?= e($c['cidade']) ?></option><?php endforeach; ?></select>
        <?php if ($bairros): ?><select name="bairro" aria-label="Bairro"><option value="">Todos os bairros</option>
          <?php foreach ($bairros as $b): ?><option <?= $f['bairro'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?></select><?php endif; ?>
        <select name="cnae" aria-label="Atividade (CNAE)"><option value="">Todas as atividades</option>
          <?php foreach ($cnaes as $cod => $nome): ?><option value="<?= e($cod) ?>" <?= $f['cnae'] === $cod ? 'selected' : '' ?>><?= e(P::cnaeFormatado($cod) . ' · ' . $nome) ?></option><?php endforeach; ?></select>
        <select name="meses" aria-label="Abertura"><option value="">Qualquer data de abertura</option>
          <?php foreach ([3, 6, 12, 24] as $m): ?><option value="<?= $m ?>" <?= $f['meses'] === $m ? 'selected' : '' ?>>Abertas nos últimos <?= $m ?> meses</option><?php endforeach; ?></select>
        <label class="opcao"><input type="checkbox" name="esconder" value="1" <?= $f['esconder_carteira'] ? 'checked' : '' ?>> Esconder as que já estão na carteira</label>
      </div>
    </form>

    <form method="post" action="<?= url('/prospeccao/enviar') ?>" data-form-envio>
      <?= csrf_campo() ?><input type="hidden" name="voltar" value="<?= e($query(['pagina' => $r['pagina'] > 1 ? $r['pagina'] : null])) ?>">
      <section class="card sem-espaco">
        <div class="tabela-rolagem">
          <table class="tabela tabela-carteira ps-tabela">
            <thead><tr><th class="ps-marcar"><?php if ($podeEnviar): ?><input type="checkbox" aria-label="Marcar todas desta página" data-marcar-todas><?php endif; ?></th>
              <th>Empresa</th><th>Atividade</th><th>Local</th><th>Abertura</th><th>Contato</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($r['itens'] as $e): $livre = !$e['cliente']; ?>
              <tr data-empresa="<?= e($e['cnpj']) ?>" class="<?= $livre ? '' : 'ps-na-carteira' ?>">
                <td class="ps-marcar"><?php if ($livre && $podeEnviar): ?><input type="checkbox" name="cnpjs[]" value="<?= e($e['cnpj']) ?>" aria-label="Marcar <?= e($e['nome_fantasia'] ?: $e['razao_social']) ?>" data-marcar><?php endif; ?></td>
                <td><strong><?= e($e['nome_fantasia'] ?: $e['razao_social']) ?></strong><small class="suave"><?= e($e['razao_social']) ?></small>
                  <small class="suave"><?= e(substr($e['cnpj'], 0, 2) . '.' . substr($e['cnpj'], 2, 3) . '.' . substr($e['cnpj'], 5, 3) . '/' . substr($e['cnpj'], 8, 4) . '-' . substr($e['cnpj'], 12)) ?> · <?= e($e['porte'] ?? '') ?></small></td>
                <td class="pequeno"><?= e(P::cnaeFormatado($e['cnae'])) ?><small class="suave"><?= e($cnaes[$e['cnae']] ?? '') ?></small></td>
                <td class="pequeno"><?= e($e['bairro'] ?? '') ?><small class="suave"><?= e($e['cidade']) ?>/<?= e($e['uf']) ?></small></td>
                <td class="pequeno"><?= data_br($e['abertura']) ?><?= $e['abertura'] >= date('Y-m-d', strtotime('-12 months')) ? '<small><span class="selo lima">nova</span></small>' : '' ?></td>
                <td class="pequeno"><?= $e['telefone'] ? e(Normalizar::telefoneFormatado($e['telefone'])) : '<span class="suave">—</span>' ?><?= $e['email'] ? '<small class="suave">' . e($e['email']) . '</small>' : '' ?></td>
                <td>
                  <?php if ($e['cliente']): ?><button type="button" class="selo verde ps-selo-botao" data-abrir-cliente="<?= (int) $e['cliente']['id'] ?>" data-na-carteira><?= $e['cliente']['tipo'] === 'cliente' ? 'cliente' : 'na carteira' ?></button>
                  <?php else: ?><span class="selo cinza">livre</span><?php endif; ?>
                  <?php if ($e['enviada_em']): ?><small class="suave">enviada em <?= data_br($e['enviada_em']) ?></small><?php endif; ?>
                  <?php if ($e['cliente'] && $e['cliente']['responsavel']): ?><small class="suave"><?= e($e['cliente']['responsavel']) ?></small><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$r['itens']): ?><tr><td colspan="7" class="vazio">Nenhuma empresa com esses filtros.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="paginacao-barra">
          <span><?= $n($r['total']) ?> empresa(s)</span>
          <?php if ($r['paginas'] > 1): ?>
            <nav class="paginacao" aria-label="Páginas">
              <?php if ($r['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= url('/prospeccao/empresas') . e($query(['pagina' => $r['pagina'] - 1])) ?>">‹ Anterior</a><?php endif; ?>
              <span class="pequeno">Página <?= $r['pagina'] ?> de <?= $r['paginas'] ?></span>
              <?php if ($r['pagina'] < $r['paginas']): ?><a class="botao contorno pequeno" href="<?= url('/prospeccao/empresas') . e($query(['pagina' => $r['pagina'] + 1])) ?>">Próxima ›</a><?php endif; ?>
            </nav>
          <?php endif; ?>
        </div>
      </section>

      <?php if ($podeEnviar): ?><div class="ps-barra-envio" data-barra-envio hidden>
        <strong><span data-qtd-marcadas>0</span> empresa(s) marcada(s)</strong>
        <?php if ($podeEscolher): ?>
          <label>Responsável <select name="responsavel" aria-label="Responsável">
            <option value="fila">Fila de novos leads (pela função)</option>
            <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['nome']) ?></option><?php endforeach; ?></select></label>
        <?php else: ?><span class="pequeno">Os leads ficam na sua carteira.</span><?php endif; ?>
        <button class="botao lima" type="submit">Enviar para o funil</button>
      </div><?php endif; ?>
    </form>
  </div>
</div>
<?= Visao::renderizar('carteira/_drawer') ?>
