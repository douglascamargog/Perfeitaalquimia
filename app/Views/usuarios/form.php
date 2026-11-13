<?php
/** Formulário de usuário. Variáveis: $usuario (null = novo), $perfis, $funcoes, $funcoesMarcadas, $acessos, $permPerfis, $excecoes */
$novo = $usuario === null;
$valor = fn(string $campo) => antigo($campo, $usuario[$campo] ?? '');
$perfilAtual = (int) \App\Core\Sessao::antigo('perfil_id', $usuario['perfil_id'] ?? 0);
$marcadas = array_map('intval', (array) \App\Core\Sessao::antigo('funcoes', $funcoesMarcadas));
$ativo = $novo || $usuario['ativo'];
?>
<form method="post" action="<?= url($novo ? '/usuarios' : '/usuarios/' . (int) $usuario['id']) ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <h2>Dados de acesso</h2>
  <div class="grade-campos">
    <div class="campo">
      <label for="nome">Nome</label>
      <input type="text" id="nome" name="nome" value="<?= $valor('nome') ?>" required>
    </div>
    <div class="campo">
      <label for="email">E-mail (login)</label>
      <input type="email" id="email" name="email" value="<?= $valor('email') ?>" required>
    </div>
    <div class="campo">
      <label for="perfil_id">Perfil de acesso</label>
      <select id="perfil_id" name="perfil_id" required>
        <option value="">Escolha…</option>
        <?php foreach ($perfis as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= $perfilAtual === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> — <?= e($p['descricao']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="codigo_vendedor">Código de vendedor</label>
      <input type="text" id="codigo_vendedor" name="codigo_vendedor" value="<?= $valor('codigo_vendedor') ?>" maxlength="20">
      <div class="ajuda">Usado no link do catálogo: /catalogo/v/CÓDIGO. Deixe vazio se a pessoa não vende.</div>
    </div>
  </div>

  <h2>Ficha do funcionário</h2>
  <?= \App\Core\Visao::renderizar('usuarios/_ficha', ['u' => $usuario, 'completo' => true]) ?>

  <h2>Funções</h2>
  <p class="suave pequeno">As filas e regras (ex.: reativação) usam a função, não a pessoa. Trocou o funcionário? Basta mudar as funções.</p>
  <div class="grade-campos">
    <?php foreach ($funcoes as $f): ?>
      <label class="opcao">
        <input type="checkbox" name="funcoes[]" value="<?= (int) $f['id'] ?>" <?= in_array((int) $f['id'], $marcadas, true) ? 'checked' : '' ?>>
        <?= e($f['nome']) ?>
      </label>
    <?php endforeach; ?>
  </div>

  <h2>O que aparece para esta pessoa</h2>
  <p class="suave pequeno">Define os cartões do Início, as dicas do One, o sino e os números do menu. Não muda o que a pessoa pode abrir (isso é em Acessos).</p>
  <?php $focoProprio = $usuario && $usuario['areas_foco'] !== null; $focoMarcado = $usuario ? \App\Services\Foco::areasDe((int) $usuario['id']) : []; ?>
  <div class="mp-foco" data-foco-admin>
    <label class="opcao"><input type="radio" name="foco_modo" value="funcoes" <?= $focoProprio ? '' : 'checked' ?>> <strong>Seguir as funções</strong> <span class="suave pequeno">(recomendado: muda junto se as funções mudarem)</span></label>
    <label class="opcao"><input type="radio" name="foco_modo" value="escolher" <?= $focoProprio ? 'checked' : '' ?>> <strong>Escolher as áreas</strong></label>
    <div class="mp-foco-areas">
      <?php foreach (\App\Services\Foco::AREAS as $area => $rotulo): ?>
        <label class="opcao"><input type="checkbox" name="foco[]" value="<?= $area ?>" <?= in_array($area, $focoMarcado, true) ? 'checked' : '' ?>> <?= e($rotulo) ?></label>
      <?php endforeach; ?>
    </div>
  </div>

  <details class="ac-excecoes" <?= $excecoes ? 'open' : '' ?>>
    <summary><strong>Acessos desta pessoa</strong> <span class="suave pequeno">— <?= $excecoes ? count($excecoes) . ' exceção(ões) sobre o perfil' : 'segue o perfil' ?></span></summary>
    <p class="suave pequeno">Normalmente a pessoa segue o perfil (Administração › Acessos). Aqui você dá ou tira algo <strong>só desta pessoa</strong>.
      Ex.: um vendedor que também edita o Mural. O perfil Admin tem tudo e ignora as exceções.</p>
    <?= \App\Core\Visao::renderizar('usuarios/_excecoes', ['acessos' => $acessos, 'permPerfis' => $permPerfis, 'excecoes' => $excecoes, 'perfilAtual' => $perfilAtual]) ?>
  </details>

  <h2 style="margin-top:16px"><?= $novo ? 'Senha provisória' : 'Redefinir senha' ?></h2>
  <p class="suave pequeno"><?= $novo ? 'A pessoa troca no primeiro acesso.' : 'Deixe em branco para manter a senha atual. Se preencher, a pessoa troca no próximo acesso.' ?></p>
  <div class="grade-campos">
    <div class="campo">
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" <?= $novo ? 'required' : '' ?>>
    </div>
    <div class="campo">
      <label for="senha_confirmacao">Repita a senha</label>
      <input type="password" id="senha_confirmacao" name="senha_confirmacao" autocomplete="new-password" minlength="8" <?= $novo ? 'required' : '' ?>>
    </div>
  </div>

  <label class="opcao"><input type="checkbox" name="ativo" value="1" <?= $ativo ? 'checked' : '' ?>> Usuário ativo (pode entrar no sistema)</label>

  <div class="acoes-form">
    <button class="botao" type="submit"><?= $novo ? 'Criar usuário' : 'Salvar alterações' ?></button>
    <a class="botao contorno" href="<?= url('/usuarios') ?>">Cancelar</a>
  </div>
</form>
