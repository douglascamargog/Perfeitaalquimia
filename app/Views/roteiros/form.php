<?php
/**
 * Novo / editar roteiro. Variáveis: $r (ou null), $conhecidas (cidades "Nome/UF" para sugerir)
 */
use App\Core\Sessao;

$v = fn(string $campo, $padrao = '') => Sessao::antigo($campo, $r[$campo] ?? $padrao);
$cidades = Sessao::antigo('cidades', $r ? array_map(fn($c) => $c['nome'] . '/' . $c['uf'], $r['cidades']) : []);
$cidades = array_values(array_filter((array) $cidades, fn($c) => trim((string) $c) !== ''));
$linhas = max(count($cidades) + 2, 4);
$acao = $r ? '/roteiros/' . (int) $r['id'] . '/editar' : '/roteiros';
?>
<a class="voltar-link" href="<?= url($r ? '/roteiros/' . (int) $r['id'] : '/roteiros') ?>">← <?= $r ? e($r['nome']) : 'Planejador de visitas' ?></a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PLANEJADOR DE VISITAS</div><h1><?= $r ? 'Editar roteiro' : 'Novo roteiro' ?></h1>
    <p>Escolha as cidades na ordem do caminho (saída → paradas → chegada). Depois o One mostra as lojas que ficam perto da rota.</p></div>
</div>
<form class="card rt-form" method="post" action="<?= url($acao) ?>" data-form-roteiro><?= csrf_campo() ?>
  <div class="rt-form-grade">
    <label class="rt-largo">Nome do roteiro<input type="text" name="nome" value="<?= e($v('nome')) ?>" maxlength="120" placeholder="Ex.: Serra gaúcha – dezembro" required></label>
    <label>Saída<input type="date" name="data_inicio" value="<?= e($v('data_inicio', date('Y-m-d', strtotime('+1 day')))) ?>" required></label>
    <label>Volta<input type="date" name="data_fim" value="<?= e($v('data_fim', date('Y-m-d', strtotime('+1 day')))) ?>" required></label>
    <label>Lojas até quantos km da rota<input type="number" name="raio_km" min="1" max="150" value="<?= e($v('raio_km', \App\Core\Config::get('roteiros.raio_km', 15))) ?>"></label>
    <label>Visitas por dia<input type="number" name="visitas_dia" min="1" max="40" value="<?= e($v('visitas_dia', \App\Core\Config::get('roteiros.visitas_dia', 8))) ?>"></label>
  </div>
  <fieldset class="rt-cidades-campos" data-cidades>
    <legend>Cidades do caminho, na ordem <small class="suave">(escreva "Cidade/UF"; sem a UF, vale RS)</small></legend>
    <?php for ($i = 0; $i < $linhas; $i++): ?>
      <div class="rt-cidade-linha"><span class="rt-num"><?= $i + 1 ?></span><input type="text" name="cidades[]" list="lista-cidades" value="<?= e($cidades[$i] ?? '') ?>" placeholder="<?= $i === 0 ? 'Ex.: Porto Alegre/RS (saída)' : 'Próxima cidade' ?>" aria-label="Cidade <?= $i + 1 ?>"></div>
    <?php endfor; ?>
    <button class="botao contorno pequeno" type="button" data-mais-cidade>＋ Mais uma cidade</button>
    <datalist id="lista-cidades"><?php foreach ($conhecidas as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    <input type="hidden" name="uf_padrao" value="RS">
  </fieldset>
  <label style="display:block;margin-top:12px">Observações<textarea name="observacoes" rows="2" maxlength="2000"><?= e($v('observacoes')) ?></textarea></label>
  <div class="acoes-formulario" style="margin-top:14px"><button class="botao" type="submit"><?= $r ? 'Salvar roteiro' : 'Criar e escolher as lojas' ?></button></div>
</form>
