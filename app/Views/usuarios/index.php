<?php /** Lista de usuários. Variáveis: $usuarios */ ?>
<div class="cabecalho-pagina">
  <p class="suave">Cada pessoa tem o seu login. O <strong>perfil</strong> define o que ela acessa; as <strong>funções</strong> definem as filas e regras que recebe.</p>
  <a class="botao" href="<?= url('/usuarios/novo') ?>">+ Novo usuário</a>
</div>

<div class="card">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Funções</th><th>Código vendedor</th><th>Último acesso</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr class="<?= $u['ativo'] ? '' : 'inativo' ?>">
          <td><strong><?= e($u['nome']) ?></strong><?= $u['ativo'] ? '' : ' <span class="selo cinza">inativo</span>' ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="selo"><?= e($u['perfil_nome']) ?></span></td>
          <td><?= e($u['funcoes_nomes'] ?: '—') ?></td>
          <td><?= e($u['codigo_vendedor'] ?: '—') ?></td>
          <td><?= data_br($u['ultimo_login_em'], true) ?></td>
          <td><a class="botao contorno pequeno" href="<?= url('/usuarios/' . (int) $u['id'] . '/editar') ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
