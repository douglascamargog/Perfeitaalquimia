<?php
/**
 * Campos da ficha do funcionário (usados no cadastro de usuários e no Meu perfil).
 * Variáveis: $u (usuário ou null), $completo (true = admin: admissão e observações também)
 */
use App\Services\EquipeService;

$v = fn(string $campo) => antigo($campo, $u[$campo] ?? '');
?>
<div class="ficha-func">
  <div class="ficha-func-foto">
    <?= EquipeService::avatar($u ?? ['nome' => (string) \App\Core\Sessao::antigo('nome', '?')], 112) ?>
    <label class="botao contorno pequeno" for="foto">Escolher foto</label>
    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" class="ficha-func-arquivo" data-preview-foto>
    <small class="suave">JPG, PNG ou WebP até 5 MB. A foto é recortada em quadrado.</small>
    <?php if (!empty($u['foto'])): ?><label class="opcao pequeno"><input type="checkbox" name="remover_foto" value="1"> Remover foto</label><?php endif; ?>
  </div>
  <div class="grade-campos">
    <div class="campo"><label for="apelido">Como prefere ser chamado(a)</label><input type="text" id="apelido" name="apelido" maxlength="60" value="<?= $v('apelido') ?>"></div>
    <div class="campo"><label for="data_nascimento">Data de nascimento</label><input type="date" id="data_nascimento" name="data_nascimento" max="<?= date('Y-m-d') ?>" value="<?= $v('data_nascimento') ?>">
      <div class="ajuda">A equipe vê só o dia e o mês (aniversariantes do mês).</div></div>
    <div class="campo"><label for="telefone">Telefone</label><input type="tel" id="telefone" name="telefone" value="<?= $v('telefone') ?>"></div>
    <div class="campo"><label for="whatsapp">WhatsApp</label><input type="tel" id="whatsapp" name="whatsapp" value="<?= $v('whatsapp') ?>"></div>
    <?php if ($completo): ?>
      <div class="campo"><label for="data_admissao">Data de admissão</label><input type="date" id="data_admissao" name="data_admissao" max="<?= date('Y-m-d') ?>" value="<?= $v('data_admissao') ?>"></div>
    <?php endif; ?>
  </div>
  <?php if ($completo): ?>
    <div class="campo ficha-func-obs"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="2"><?= $v('observacoes') ?></textarea></div>
  <?php endif; ?>
</div>
<script>
  // Mostra a foto escolhida antes de salvar
  document.querySelector('[data-preview-foto]')?.addEventListener('change', (e) => {
    const arquivo = e.target.files[0];
    const caixa = e.target.closest('.ficha-func-foto');
    if (!arquivo || !arquivo.type.startsWith('image/')) return;
    const img = document.createElement('img');
    img.className = 'avatar'; img.width = 112; img.height = 112; img.alt = 'Foto escolhida';
    img.src = URL.createObjectURL(arquivo);
    caixa.querySelector('.avatar').replaceWith(img);
  });
</script>
