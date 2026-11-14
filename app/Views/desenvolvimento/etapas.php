<?php /** Configurações › Etapas do desenvolvimento. Variáveis: $etapas, $funcoes */ use App\Services\DesenvolvimentoService as D; ?>
<div class="cabecalho-secao"><div><div class="sobretitulo">ADMINISTRAÇÃO</div><h1>Etapas do desenvolvimento</h1>
  <p>A ordem das etapas, a função que faz cada uma e quem aprova. A checagem automática (o que precisa estar pronto) acompanha a etapa.</p></div></div>
<form method="post" action="<?= url('/desenvolvimento/etapas') ?>" class="card sem-espaco"><?= csrf_campo() ?>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th>Ordem</th><th>Etapa</th><th>Função que faz</th><th>Aprovam</th><th>Checagem</th><th>Ativa</th></tr></thead>
    <tbody><?php foreach ($etapas as $e): $ap = array_filter(explode(',', (string) $e['aprovadores'])); ?>
      <tr><td><input type="number" name="etapa[<?= (int) $e['id'] ?>][ordem]" value="<?= (int) $e['ordem'] ?>" style="width:70px" aria-label="Ordem"></td>
        <td><input type="text" name="etapa[<?= (int) $e['id'] ?>][nome]" value="<?= e($e['nome']) ?>" aria-label="Nome da etapa"></td>
        <td><select name="etapa[<?= (int) $e['id'] ?>][funcao]" aria-label="Função"><option value="">— gestão</option>
          <?php foreach ($funcoes as $f): ?><option value="<?= e($f['slug']) ?>" <?= $f['slug'] === $e['funcao_slug'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option><?php endforeach; ?></select></td>
        <td><?php if ($e['checagem'] === 'aprovacao'): foreach ($funcoes as $f): ?><label class="opcao pequeno"><input type="checkbox" name="etapa[<?= (int) $e['id'] ?>][aprovadores][]" value="<?= e($f['slug']) ?>" <?= in_array($f['slug'], $ap, true) ? 'checked' : '' ?>> <?= e($f['nome']) ?></label><?php endforeach; else: ?>—<?php endif; ?></td>
        <td class="pequeno suave"><?= e(D::CHECAGENS[$e['checagem']] ?? 'manual') ?></td>
        <td><input type="checkbox" name="etapa[<?= (int) $e['id'] ?>][ativa]" value="1" <?= $e['ativa'] ? 'checked' : '' ?> aria-label="Ativa"></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="pd-form" style="padding:14px"><label>Etapa nova (manual, vai para o fim)<input type="text" name="nova" maxlength="120"></label>
    <div class="acoes-form"><button class="botao" type="submit">Salvar etapas</button></div></div>
</form>
