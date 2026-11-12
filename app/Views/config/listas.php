<?php
/** Listas configuráveis. Variáveis: $etapas, $segmentos, $origens, $cobranca, $posts, $usos, $tipos, $tiposCobranca, $tiposPost */
$secoes = [
    'etapas'    => ['Etapas do funil', 'A ordem define as colunas do funil. "Ganho" marca o cliente que comprou.', $etapas],
    'segmentos' => ['Segmentos', 'Usados nos filtros, no mapa e na normalização das planilhas importadas.', $segmentos],
    'origens'   => ['Origens', 'De onde veio o cliente ou lead (inclui campanhas).', $origens],
    'cobranca'  => ['Funil de cobrança', 'Colunas do funil da Cobrança. Etapas "Encerra: …" fecham o caso; "Sem contato" é para onde vão os casos depois das tentativas sem resposta.', $cobranca],
];
$secoes['posts'] = ['Etapas dos posts (Marketing)', 'Colunas do fluxo dos posts do Instagram. O tipo "Aguardando aprovação" exige aprovação antes de "Agendado/Publicado".', $posts];
$tiposPor = ['etapas' => $tipos, 'cobranca' => $tiposCobranca, 'posts' => $tiposPost];
?>
<div class="cabecalho-pagina">
  <p class="suave">Listas usadas na Carteira e na Cobrança. Itens em uso não são apagados: desative para esconder dos formulários. Toda mudança fica na Auditoria.</p>
  <a class="botao contorno" href="<?= url('/configuracoes') ?>">Configurações gerais</a>
</div>

<?php foreach ($secoes as $lista => [$titulo, $ajuda, $itens]): $ativo = isset($tiposPor[$lista]) ? 'ativa' : 'ativo'; $tiposLista = $tiposPor[$lista] ?? []; ?>
  <section class="card" id="<?= $lista ?>">
    <h2><?= e($titulo) ?></h2>
    <p class="suave pequeno"><?= e($ajuda) ?></p>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th>Nome</th><?= $lista !== 'origens' ? '<th>Ordem</th>' : '' ?><?= $tiposLista ? '<th>Cor</th><th>Tipo</th>' : '' ?><th><?= ['cobranca' => 'Casos abertos', 'posts' => 'Posts'][$lista] ?? 'Clientes' ?></th><th>Ativo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($itens as $item): $form = $lista . '-' . (int) $item['id']; ?>
          <tr class="<?= $item[$ativo] ? '' : 'inativo' ?>">
            <td><input type="text" name="nome" form="<?= $form ?>" value="<?= e($item['nome']) ?>" maxlength="80" required aria-label="Nome"></td>
            <?php if ($lista !== 'origens'): ?><td style="width:90px"><input type="number" name="ordem" form="<?= $form ?>" value="<?= (int) $item['ordem'] ?>" aria-label="Ordem"></td><?php endif; ?>
            <?php if ($tiposLista): ?>
              <td style="width:70px"><input type="color" name="cor" form="<?= $form ?>" value="<?= e($item['cor']) ?>" aria-label="Cor"></td>
              <td><select name="tipo" form="<?= $form ?>" aria-label="Tipo"><?php foreach ($tiposLista as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $item['tipo'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></td>
            <?php endif; ?>
            <td><?= (int) ($usos[$lista][$item['id']] ?? 0) ?></td>
            <td><input type="checkbox" name="ativo" value="1" form="<?= $form ?>" <?= $item[$ativo] ? 'checked' : '' ?> aria-label="Ativo"></td>
            <td><form id="<?= $form ?>" method="post" action="<?= url("/configuracoes/listas/$lista/" . (int) $item['id']) ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Salvar</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="post" action="<?= url("/configuracoes/listas/$lista") ?>" class="nova-linha">
      <?= csrf_campo() ?>
      <input type="text" name="nome" placeholder="Novo item…" maxlength="80" required aria-label="Novo item em <?= e($titulo) ?>">
      <?php if ($lista !== 'origens'): ?><input type="number" name="ordem" value="<?= count($itens) + 1 ?>" aria-label="Ordem" style="max-width:90px"><?php endif; ?>
      <?php if ($tiposLista): ?>
        <input type="color" name="cor" value="#94a3b8" aria-label="Cor">
        <select name="tipo" aria-label="Tipo" style="max-width:180px"><?php foreach ($tiposLista as $valor => $rotulo): ?><option value="<?= $valor ?>"><?= $rotulo ?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <button class="botao pequeno" type="submit">Adicionar</button>
    </form>
  </section>
<?php endforeach; ?>
