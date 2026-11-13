<?php
/**
 * Exceções de acesso de uma pessoa (sobre o perfil).
 * Variáveis: $acessos (app/acessos.php), $permPerfis [perfil_id => permissões], $excecoes [permissao => 1|0], $perfilAtual
 * O "igual ao perfil" mostra o nível do perfil escolhido (e muda junto quando troca o perfil, pelo script abaixo).
 */
use App\Services\AcessoService;

$nomes = [0 => 'Sem acesso', 1 => 'Ver', 2 => 'Ver e editar'];
$i = -1;
// Nível que a pessoa tem hoje por causa das exceções (null = sem exceção nessa aba)
$nivelExcecao = function (?string $ver, string $editar) use ($excecoes): ?int {
    if (!array_key_exists($editar, $excecoes) && (!$ver || !array_key_exists($ver, $excecoes))) {
        return null;
    }
    if (!empty($excecoes[$editar])) {
        return 2;
    }
    return $ver && !empty($excecoes[$ver]) ? 1 : 0;
};
?>
<div class="tabela-rolagem"><table class="tabela ac-grade" data-excecoes data-perfis='<?= e(json_encode(array_map(fn($l) => array_values($l), $permPerfis))) ?>'>
  <thead><tr><th>Aba</th><th>Acesso desta pessoa</th></tr></thead>
  <tbody>
  <?php foreach ($acessos['abas'] as $grupo => $abas): ?>
    <tr class="ac-grupo"><th colspan="2"><?= e($grupo) ?></th></tr>
    <?php foreach ($abas as [$titulo, $ver, $editar]): $i++; $atual = $nivelExcecao($ver, $editar); ?>
      <tr><td><?= e($titulo) ?></td>
        <td><select name="excecao[<?= $i ?>]" aria-label="Acesso desta pessoa: <?= e($titulo) ?>" data-ver="<?= e((string) $ver) ?>" data-editar="<?= e($editar) ?>">
          <?php $np = AcessoService::nivel($permPerfis[$perfilAtual] ?? [], $ver, $editar); ?>
          <option value="">Igual ao perfil (<?= $np === 2 && !$ver ? 'Tem acesso' : $nomes[$np] ?>)</option>
          <?php foreach ($nomes as $k => $rotulo): if ($k === 1 && !$ver) continue; ?>
            <option value="<?= $k ?>" <?= $atual === $k ? 'selected' : '' ?>><?= $k === 2 && !$ver ? 'Tem acesso' : $rotulo ?></option>
          <?php endforeach; ?>
        </select></td></tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <tr class="ac-grupo"><th colspan="2">Ações especiais</th></tr>
  <?php foreach ($acessos['especiais'] as $perm => $rotulo): $e = $excecoes[$perm] ?? null; ?>
    <tr><td><?= e($rotulo) ?></td>
      <td><select name="excecao_especial[<?= e($perm) ?>]" aria-label="<?= e($rotulo) ?>" data-especial="<?= e($perm) ?>">
        <option value="">Igual ao perfil</option>
        <option value="1" <?= $e === 1 ? 'selected' : '' ?>>Pode</option>
        <option value="0" <?= $e === 0 ? 'selected' : '' ?>>Não pode</option>
      </select></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<script>
  // Trocou o perfil: o texto "Igual ao perfil (...)" acompanha
  (() => {
    const tabela = document.querySelector('[data-excecoes]');
    const perfil = document.getElementById('perfil_id');
    if (!tabela || !perfil) return;
    const perms = JSON.parse(tabela.dataset.perfis || '{}');
    const nomes = ['Sem acesso', 'Ver', 'Ver e editar'];
    perfil.addEventListener('change', () => {
      const lista = perms[perfil.value] || [];
      const tudo = lista.includes('*');
      tabela.querySelectorAll('select[data-editar]').forEach((s) => {
        const n = tudo || lista.includes(s.dataset.editar) ? 2 : (s.dataset.ver && lista.includes(s.dataset.ver) ? 1 : 0);
        s.options[0].textContent = `Igual ao perfil (${n === 2 && !s.dataset.ver ? 'Tem acesso' : nomes[n]})`;
      });
    });
  })();
</script>
