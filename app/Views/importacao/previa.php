<?php
/** Pré-visualização. Variáveis: $importacao, $linhas, $filtro */
$i = $importacao;
$acoes = ['novo' => ['Novos', $i['novos'], 'lima'], 'atualizar' => ['Completar cadastro', $i['atualizados'], ''], 'duplicado' => ['Duplicados (ignorados)', $i['duplicados'], 'cinza'], 'erro' => ['Com erro (ignorados)', $i['erros'], 'vermelho']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">IMPORTAÇÃO · <?= e(strtoupper($i['formato'])) ?></div><h1><?= e($i['arquivo']) ?></h1>
    <p><?= (int) $i['total'] ?> linhas lidas em <?= data_br($i['criado_em'], true) ?>.</p></div>
  <?php if ($i['status'] === 'previa'): ?>
    <div class="acoes">
      <form method="post" action="<?= url('/carteira/importar/' . (int) $i['id'] . '/cancelar') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit">Cancelar</button></form>
      <form method="post" action="<?= url('/carteira/importar/' . (int) $i['id'] . '/confirmar') ?>" data-confirmar="Gravar <?= (int) $i['novos'] ?> novos e completar <?= (int) $i['atualizados'] ?> cadastros?"><?= csrf_campo() ?>
        <button class="botao lima" type="submit">Confirmar importação</button></form>
    </div>
  <?php else: ?>
    <div class="acoes"><span class="selo cinza"><?= $i['status'] === 'concluida' ? 'Já concluída' : 'Cancelada' ?></span></div>
  <?php endif; ?>
</div>

<div class="agenda-resumo quatro">
  <?php foreach ($acoes as $chave => [$nome, $qtd, $cor]): ?>
    <a class="card <?= $filtro === $chave ? 'ativo' : '' ?>" href="<?= url('/carteira/importar/' . (int) $i['id']) ?>?acao=<?= $chave ?>"><span><?= $nome ?></span><strong class="<?= $cor ?>"><?= (int) $qtd ?></strong></a>
  <?php endforeach; ?>
</div>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Linha</th><th>Ação</th><th>Nome</th><th>Cidade / UF</th><th>CNPJ</th><th>Segmento</th><th>Telefone</th><th>Observação</th></tr></thead>
      <tbody>
      <?php foreach ($linhas as $l): $d = json_decode($l['dados_json'], true); ?>
        <tr>
          <td><?= (int) $l['linha'] ?></td>
          <td><span class="selo <?= e($acoes[$l['acao']][2]) ?>"><?= e($l['acao']) ?></span></td>
          <td><strong><?= e($d['razao_social'] ?? '') ?></strong></td>
          <td><?= e($d['cidade'] ?? '') ?> / <?= e($d['uf'] ?? '') ?></td>
          <td class="pequeno"><?= e(cnpj_formatado($d['cnpj'] ?? '')) ?></td>
          <td class="pequeno"><?= e($d['segmento'] ?? '') ?></td>
          <td class="pequeno"><?= e(\App\Services\Normalizar::telefoneFormatado($d['telefone'] ?? '')) ?></td>
          <td class="pequeno suave"><?= e($l['mensagem']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$linhas): ?><tr><td colspan="8" class="vazio">Nenhuma linha nesta categoria.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($linhas) >= 300): ?><p class="pequeno suave" style="padding:12px 16px">Mostrando as primeiras 300 linhas.</p><?php endif; ?>
</section>
