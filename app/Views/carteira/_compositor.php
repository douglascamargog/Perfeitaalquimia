<?php
/**
 * Caixa rápida no topo da linha do tempo: Anotação (com print colado), Agendar atividade e Registrar contato.
 * Os formulários são data-ajax (carteira.js envia e atualiza a ficha/página). O compositor.js cuida das abas e do Ctrl+V.
 * Variáveis: $id (cliente), $canais
 */
use App\Core\Auth;
use App\Services\ClienteAnexoService;

// Gestão (vê todas as carteiras ou gerencia um setor) agenda para alguém da equipe; os outros, para si
$equipe = Auth::pode('carteira.ver_todas') || Auth::pode('setor.gestao') ? \App\Models\Listas::vendedores() : [];

$base = url("/carteira/clientes/$id");
$hoje = date('Y-m-d');
$sufixo = 'cx' . $id . '-' . bin2hex(random_bytes(2)); // ids únicos (gaveta e página podem coexistir)
?>
<div class="cx" data-compositor>
  <div class="cx-abas" role="tablist">
    <button type="button" role="tab" class="ativo" data-cx-aba="nota" aria-selected="true">📝 Anotação</button>
    <button type="button" role="tab" data-cx-aba="agendar" aria-selected="false">📅 Agendar</button>
    <button type="button" role="tab" data-cx-aba="contato" aria-selected="false">📞 Contato</button>
  </div>

  <form class="cx-painel" data-cx-painel="nota" method="post" action="<?= $base ?>/anotar" enctype="multipart/form-data" data-ajax>
    <?= csrf_campo() ?>
    <textarea name="texto" rows="2" maxlength="2000" placeholder="Escreva uma observação… ou cole um print com Ctrl+V" aria-label="Anotação" data-cx-texto></textarea>
    <div class="cx-previas" data-cx-previas></div>
    <div class="cx-rodape">
      <label class="cx-anexar"><input type="file" name="anexos[]" accept="image/*,application/pdf" multiple hidden data-cx-arquivos>📎 Anexar print ou PDF</label>
      <span class="pequeno suave" data-cx-dica>Dica: tire o print (Win+Shift+S) e cole aqui.</span>
      <button class="botao lima pequeno" type="submit">Salvar anotação</button>
    </div>
  </form>

  <form class="cx-painel" data-cx-painel="agendar" method="post" action="<?= $base ?>/agendar" data-ajax hidden>
    <?= csrf_campo() ?>
    <div class="cx-grade">
      <label>O quê<select name="atividade"><?php foreach (ClienteAnexoService::ATIVIDADES as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Dia<input type="date" name="data" min="<?= $hoje ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></label>
      <label>Hora <small>opcional</small><input type="time" name="hora"></label>
      <?php if ($equipe): ?><label>Para<select name="usuario_id"><option value="">Eu</option><?php foreach ($equipe as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    </div>
    <input type="text" name="notas" maxlength="1000" placeholder="Observação (ex.: oferecer a campanha de Magnésio)" aria-label="Observação da atividade">
    <div class="cx-rodape"><span class="pequeno suave">Vai para a Agenda e avisa no dia.</span><button class="botao lima pequeno" type="submit">Agendar</button></div>
  </form>

  <form class="cx-painel" data-cx-painel="contato" method="post" action="<?= $base ?>/contato" data-ajax hidden>
    <?= csrf_campo() ?>
    <div class="cx-grade">
      <label>Canal<select name="canal"><?php foreach ($canais as $canal): ?><option><?= e($canal) ?></option><?php endforeach; ?></select></label>
      <label>Data<input type="date" name="data" value="<?= $hoje ?>" max="<?= $hoje ?>"></label>
      <label>Próximo contato <small>opcional</small><input type="date" name="proximo_contato" min="<?= $hoje ?>"></label>
    </div>
    <textarea name="resumo" rows="2" placeholder="Resumo da conversa…" aria-label="Resumo da conversa"></textarea>
    <div class="cx-rodape"><span></span><button class="botao lima pequeno" type="submit">Registrar contato</button></div>
  </form>
</div>
