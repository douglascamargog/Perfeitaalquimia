<?php
/** Cadastro completo do estabelecimento. Variáveis: $c (null = novo), $segmentos, $origens, $tabelas, $ufs, $prioridades */
$novo = $c === null;
$v = fn(string $campo) => antigo($campo, $c[$campo] ?? '');
$selecionado = fn(string $campo, $valor) => (string) \App\Core\Sessao::antigo($campo, $c[$campo] ?? '') === (string) $valor ? 'selected' : '';
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA</div><h1><?= $novo ? 'Novo estabelecimento' : e($c['nome_fantasia'] ?: $c['razao_social']) ?></h1>
    <p><?= $novo ? 'O responsável é definido pela regra de funções.' : 'Responsável: ' . e($c['responsavel']) ?></p></div>
</div>

<form method="post" action="<?= url($novo ? '/carteira/clientes' : '/carteira/clientes/' . (int) $c['id']) ?>" class="card form-cliente">
  <?= csrf_campo() ?>
  <?php if (!$novo): ?><input type="hidden" name="atualizado_em" value="<?= e($c['atualizado_em']) ?>"><?php endif; ?>

  <h2>Identificação</h2>
  <div class="grade-campos">
    <div class="campo"><label for="razao_social">Nome / razão social *</label><input type="text" id="razao_social" name="razao_social" value="<?= $v('razao_social') ?>" required maxlength="200"></div>
    <div class="campo"><label for="nome_fantasia">Nome fantasia</label><input type="text" id="nome_fantasia" name="nome_fantasia" value="<?= $v('nome_fantasia') ?>" maxlength="200"></div>
    <div class="campo"><label for="cnpj">CNPJ</label><input type="text" id="cnpj" name="cnpj" value="<?= e(cnpj_formatado(\App\Core\Sessao::antigo('cnpj', $c['cnpj'] ?? ''))) ?>" inputmode="numeric" placeholder="00.000.000/0000-00"></div>
    <div class="campo"><label for="segmento_id">Segmento</label>
      <select id="segmento_id" name="segmento_id"><?php foreach ($segmentos as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $selecionado('segmento_id', $s['id']) ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="prioridade">Prioridade</label>
      <select id="prioridade" name="prioridade"><?php foreach ($prioridades as $p): ?><option <?= $selecionado('prioridade', $p) ?: ($novo && $p === 'Média' ? 'selected' : '') ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
    <div class="campo"><label for="tabela_preco_id">Tabela de preço</label>
      <select id="tabela_preco_id" name="tabela_preco_id"><?php foreach ($tabelas as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $selecionado('tabela_preco_id', $t['id']) ?>><?= e($t['nome']) ?></option><?php endforeach; ?></select></div>
  </div>

  <h2>Endereço</h2>
  <div class="grade-campos">
    <div class="campo"><label for="endereco">Endereço</label><input type="text" id="endereco" name="endereco" value="<?= $v('endereco') ?>"></div>
    <div class="campo"><label for="bairro">Bairro</label><input type="text" id="bairro" name="bairro" value="<?= $v('bairro') ?>"></div>
    <div class="campo"><label for="cep">CEP</label><input type="text" id="cep" name="cep" value="<?= $v('cep') ?>" inputmode="numeric"></div>
    <div class="campo"><label for="cidade">Cidade *</label><input type="text" id="cidade" name="cidade" value="<?= $v('cidade') ?>" required></div>
    <div class="campo"><label for="uf">UF *</label>
      <select id="uf" name="uf" required><option value="">—</option><?php foreach ($ufs as $uf): ?><option <?= $selecionado('uf', $uf) ?>><?= e($uf) ?></option><?php endforeach; ?></select></div>
  </div>

  <h2>Contato e redes</h2>
  <div class="grade-campos">
    <div class="campo"><label for="telefone">Telefone</label><input type="tel" id="telefone" name="telefone" value="<?= $v('telefone') ?>"></div>
    <div class="campo"><label for="whatsapp">WhatsApp</label><input type="tel" id="whatsapp" name="whatsapp" value="<?= $v('whatsapp') ?>"></div>
    <div class="campo"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= $v('email') ?>"></div>
    <div class="campo"><label for="instagram">Instagram</label><input type="text" id="instagram" name="instagram" value="<?= $v('instagram') ?>" placeholder="@perfil"></div>
    <div class="campo"><label for="facebook">Facebook</label><input type="text" id="facebook" name="facebook" value="<?= $v('facebook') ?>"></div>
    <div class="campo"><label for="site">Site</label><input type="text" id="site" name="site" value="<?= $v('site') ?>"></div>
  </div>

  <h2>Comercial</h2>
  <div class="grade-campos">
    <div class="campo"><label for="origem_id">Origem</label>
      <select id="origem_id" name="origem_id"><option value="">—</option><?php foreach ($origens as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $selecionado('origem_id', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="campo"><label for="mix_produtos">Mix de produtos da loja</label><textarea id="mix_produtos" name="mix_produtos" rows="2"><?= $v('mix_produtos') ?></textarea></div>
  <div class="campo"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="3"><?= $v('observacoes') ?></textarea></div>

  <div class="acoes-form">
    <button class="botao" type="submit"><?= $novo ? 'Cadastrar' : 'Salvar alterações' ?></button>
    <a class="botao contorno" href="<?= url('/carteira/lista') ?>">Cancelar</a>
  </div>
</form>
