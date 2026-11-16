<?php
/** Editar treinamento: trilhas e módulos; tutoriais das telas. Variáveis: $trilhas (com 'modulos'), $tutoriais */
use App\Core\Visao;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">TREINAMENTO</div><h1>Editar treinamento</h1>
    <p>Os textos dos módulos, as perguntas da prova e o passo a passo que o One mostra em cada tela. O que você editar aqui não é trocado pelas atualizações do sistema.</p></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'editar']) ?>

<h2 class="tr-h2">Trilhas e módulos</h2>
<div class="tr-grade">
  <?php foreach ($trilhas as $t): ?>
    <details class="card tr-trilha">
      <summary><span class="tr-icone"><?= e($t['icone'] ?: '📘') ?></span><span><strong><?= e($t['titulo']) ?></strong><small><?= count($t['modulos']) ?> módulos</small></span></summary>
      <ol class="tr-lista">
        <?php foreach ($t['modulos'] as $m): ?>
          <li><a href="<?= url('/treinamento/editar/modulo/' . (int) $m['id']) ?>"><?= e($m['titulo']) ?></a>
            <small class="suave"><?= (int) $m['perguntas'] ?> pergunta(s)<?= $m['tipo'] === 'vizinho' ? ' · para os vizinhos' : '' ?><?= $m['ativo'] ? '' : ' · desligado' ?><?= $m['editado_em'] ? ' · editado' : '' ?></small></li>
        <?php endforeach; ?>
      </ol>
      <form method="post" action="<?= url('/treinamento/editar/trilha/' . (int) $t['id'] . '/modulo') ?>"><?= csrf_campo() ?>
        <button class="botao contorno pequeno" type="submit">+ Novo módulo</button></form>
    </details>
  <?php endforeach; ?>
</div>

<h2 class="tr-h2" id="tutoriais">Tutoriais das telas (o passo a passo do One)</h2>
<div class="card tabela-rolagem">
  <table class="tabela">
    <thead><tr><th>Tela</th><th>Endereço</th><th>Passos</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tutoriais as $t): ?>
        <tr><td><strong><?= e($t['titulo']) ?></strong><?= $t['ativo'] ? '' : ' <span class="selo cinza">desligado</span>' ?><?= $t['editado_em'] ? ' <span class="selo azul">editado</span>' : '' ?></td>
          <td><code><?= e($t['rota']) ?></code></td><td><?= (int) $t['passos'] ?></td>
          <td><a class="botao contorno pequeno" href="<?= url('/treinamento/editar/tutorial/' . (int) $t['id']) ?>">Editar</a></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
