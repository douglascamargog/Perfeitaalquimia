<?php
/** Grade de acessos. Variáveis: $def (abas e especiais), $perfis, $permissoes [perfil_id => [...]] */
use App\Services\AcessoService;

$i = -1;
$niveis = [0 => 'Sem acesso', 1 => 'Ver', 2 => 'Ver e editar'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO</div><h1>Acessos</h1>
    <p>Quem pode ver e quem pode editar cada aba, por perfil. Todas as abas aparecem no menu para todo mundo:
      sem acesso, a aba fica com cadeado; com "Ver", a tela abre sem os botões de alterar.</p></div>
</div>
<div class="aviso aviso">O perfil <strong>Admin</strong> tem acesso a tudo e não aparece aqui. Exceções de uma pessoa (dar ou tirar algo só dela)
  ficam no cadastro dela, em Usuários. O que aparece no Início e nos avisos de cada um segue a <strong>função</strong> (Administração › Funções).</div>
<form method="post" action="<?= url('/acessos') ?>" class="card sem-espaco" data-grade-acessos>
  <?= csrf_campo() ?>
  <div class="tabela-rolagem">
    <table class="tabela ac-grade">
      <thead><tr><th>Aba</th><?php foreach ($perfis as $p): ?><th><?= e($p['nome']) ?></th><?php endforeach; ?></tr></thead>
      <?php foreach ($def['abas'] as $grupo => $abas): ?>
        <tbody>
          <tr class="ac-grupo"><th colspan="<?= count($perfis) + 1 ?>"><?= e($grupo) ?></th></tr>
          <?php foreach ($abas as [$titulo, $ver, $editar, $obs]): $i++; ?>
            <tr data-aba="<?= $i ?>">
              <td><strong><?= e($titulo) ?></strong><?= $obs ? '<small class="suave">' . e($obs) . '</small>' : '' ?></td>
              <?php foreach ($perfis as $p): $n = AcessoService::nivel($permissoes[(int) $p['id']], $ver, $editar); ?>
                <td><select name="nivel[<?= (int) $p['id'] ?>][<?= $i ?>]" aria-label="<?= e($titulo . ' · ' . $p['nome']) ?>" class="ac-nivel n<?= $n ?>" data-perfil="<?= e($p['slug']) ?>">
                  <?php foreach ($niveis as $k => $rotulo): if ($k === 1 && !$ver) continue; ?>
                    <option value="<?= $k ?>" <?= $n === $k ? 'selected' : '' ?>><?= $k === 2 && !$ver ? 'Tem acesso' : $rotulo ?></option>
                  <?php endforeach; ?>
                </select></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      <?php endforeach; ?>
      <tbody>
        <tr class="ac-grupo"><th colspan="<?= count($perfis) + 1 ?>">Ações especiais</th></tr>
        <?php foreach ($def['especiais'] as $perm => $rotulo): ?>
          <tr data-especial="<?= e($perm) ?>">
            <td><?= e($rotulo) ?></td>
            <?php foreach ($perfis as $p): ?>
              <td><label class="opcao ac-caixa"><input type="checkbox" name="especial[<?= (int) $p['id'] ?>][<?= e($perm) ?>]" value="1"
                <?= in_array($perm, $permissoes[(int) $p['id']], true) ? 'checked' : '' ?> aria-label="<?= e($rotulo . ' · ' . $p['nome']) ?>"></label></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pn-salvar"><button class="botao" type="submit">Salvar acessos</button></div>
</form>
<script>
  // A cor do campo acompanha o nível escolhido
  document.querySelectorAll('.ac-nivel').forEach((s) => s.addEventListener('change', () => { s.className = 'ac-nivel n' + s.value; }));
</script>
