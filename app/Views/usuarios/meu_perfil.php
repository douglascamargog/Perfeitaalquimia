<?php
/** Meu perfil. Variável: $u (usuário logado) */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">MINHA CONTA</div><h1>Meu perfil</h1>
  <p>Sua foto aparece no topo, no ranking da equipe e no card de aniversariantes.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/minha-senha') ?>">Trocar minha senha</a></div></div>
<form method="post" action="<?= url('/meu-perfil') ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <div class="grade-campos">
    <div class="campo"><label for="nome">Nome</label><input type="text" id="nome" name="nome" maxlength="120" required value="<?= e(antigo('nome', $u['nome'])) ?>"></div>
    <div class="campo"><label>E-mail de acesso</label><input type="email" value="<?= e($u['email']) ?>" disabled>
      <div class="ajuda"><?= e($u['perfil_nome']) ?><?= $u['funcoes'] ? ' · ' . e(implode(', ', array_column($u['funcoes'], 'nome'))) : '' ?></div></div>
  </div>
  <?= Visao::renderizar('usuarios/_ficha', ['u' => $u, 'completo' => false]) ?>
  <p class="suave pequeno">O que aparece no seu Início e no sino segue a sua função. Para mudar o e-mail de acesso, a função ou o que você acompanha, fale com o administrador.</p>
  <div class="acoes-form"><button class="botao" type="submit">Salvar</button></div>
</form>

<?php // Aparência: cores do sistema só para esta pessoa (a prévia muda na hora; salva com o botão)
[$corP, $corS] = \App\Services\Aparencia::daPessoa($u);
$propria = !empty($u['cor_primaria']) || !empty($u['cor_secundaria']);
$atual = 'personalizada';
foreach (\App\Services\Aparencia::COMBINACOES as $chave => [, $p, $s]) {
    if ($p === $corP && $s === $corS) { $atual = $chave; break; }
} ?>
<form method="post" action="<?= url('/meu-perfil/aparencia') ?>" class="card mp-aparencia" style="max-width:820px" data-aparencia>
  <?= csrf_campo() ?>
  <h2>Aparência</h2>
  <p class="suave pequeno">Escolha as cores do seu One. A primária vai no menu, no topo e nos botões; a secundária nos destaques.
    Valem só para você<?= $propria ? '' : ' (agora você está com as cores da empresa)' ?>.</p>
  <div class="mp-combinacoes">
    <?php foreach (\App\Services\Aparencia::COMBINACOES as $chave => [$nome, $p, $s]): ?>
      <label class="mp-combinacao">
        <input type="radio" name="combinacao" value="<?= e($chave) ?>" data-primaria="<?= e($p) ?>" data-secundaria="<?= e($s) ?>" <?= $atual === $chave ? 'checked' : '' ?>>
        <span class="mp-amostra" style="--a:<?= e($p) ?>;--b:<?= e($s) ?>" aria-hidden="true"></span>
        <span><?= e($nome) ?></span>
      </label>
    <?php endforeach; ?>
    <label class="mp-combinacao">
      <input type="radio" name="combinacao" value="personalizada" <?= $atual === 'personalizada' ? 'checked' : '' ?>>
      <span class="mp-amostra mp-amostra-livre" aria-hidden="true"></span><span>Personalizada</span>
    </label>
  </div>
  <div class="mp-cores">
    <label>Cor primária <input type="color" name="cor_primaria" value="<?= e($corP) ?>"></label>
    <label>Cor secundária <input type="color" name="cor_secundaria" value="<?= e($corS) ?>"></label>
  </div>
  <div class="acoes-form">
    <button class="botao" type="submit">Salvar cores</button>
    <?php if ($propria): ?><button class="botao contorno" type="submit" name="padrao" value="1">Voltar ao padrão da empresa</button><?php endif; ?>
  </div>
</form>
