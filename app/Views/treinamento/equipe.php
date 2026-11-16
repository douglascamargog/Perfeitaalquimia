<?php
/** Equipe: progresso de cada pessoa. Variáveis: $pessoas, $setor, $funcoes, $telasTotal */
use App\Core\Visao;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">TREINAMENTO</div><h1>Treinamento da equipe</h1>
    <p>Quantos módulos cada pessoa já concluiu, a média das provas e quantas telas do sistema ela já aprendeu com o One.</p></div>
  <button class="botao contorno" type="button" onclick="window.print()">Imprimir</button>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'equipe']) ?>
<form method="get" class="card filtros" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
  <label>Setor<select name="setor" onchange="this.form.submit()"><option value="">Todos</option>
    <?php foreach ($funcoes as $f): ?><option value="<?= e($f['slug']) ?>" <?= $setor === $f['slug'] ? 'selected' : '' ?>><?= e($f['nome']) ?></option><?php endforeach; ?></select></label>
</form>
<div class="card tabela-rolagem">
  <table class="tabela tr-equipe" data-tr-equipe>
    <thead><tr><th>Pessoa</th><th>Setor</th><th>Módulos</th><th>Média</th><th>Telas aprendidas</th><th>Último estudo</th><th class="sem-impressao"></th></tr></thead>
    <tbody>
      <?php foreach ($pessoas as $p): $pct = $p['total'] ? (int) round($p['concluidos'] / $p['total'] * 100) : 0; ?>
        <tr data-pessoa="<?= (int) $p['id'] ?>">
          <td><strong><?= e($p['nome']) ?></strong><?php if ($p['tutorial_ativo']): ?> <span class="selo azul" title="O One está mostrando os tutoriais das telas">em tutorial</span><?php endif; ?></td>
          <td class="suave"><?= e($p['funcoes'] ?: '—') ?></td>
          <td><div class="tr-barra" title="<?= $pct ?>%"><span style="width:<?= $pct ?>%"></span></div><small><?= (int) $p['concluidos'] ?> de <?= (int) $p['total'] ?></small></td>
          <td><?= $p['media'] !== null ? (int) $p['media'] . '%' : '—' ?></td>
          <td><?= (int) $p['telas'] ?></td>
          <td><?= $p['ultimo_estudo'] ? e(data_br($p['ultimo_estudo'])) : '—' ?></td>
          <td class="sem-impressao"><form method="post" action="<?= url('/treinamento/equipe/' . (int) $p['id'] . '/reiniciar') ?>"><?= csrf_campo() ?>
            <button class="botao contorno pequeno" type="submit" data-confirmar="Reiniciar o tutorial das telas de <?= e($p['nome']) ?>? O One volta a mostrar o passo a passo em cada tela até a pessoa marcar que aprendeu.">Reiniciar tutorial</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
