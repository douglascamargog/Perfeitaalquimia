<?php /** Discador do topo (Televendas e Cobrança): ligar para qualquer número pelo Ligue Talk. Só aparece para TelefoniaService::veDiscador() */ ?>
<div class="discador" data-discador>
  <button class="botao-icone" type="button" aria-label="Discador: ligar para um número" aria-haspopup="true" aria-expanded="false" data-discador-botao title="Ligar para um número">
    <svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
  </button>
  <div class="discador-painel" data-discador-painel hidden role="dialog" aria-label="Discador">
    <form data-discador-form autocomplete="off">
      <label for="discador-numero"><strong>Ligar para</strong> <small>pelo Ligue Talk</small></label>
      <input type="tel" id="discador-numero" name="numero" inputmode="tel" placeholder="(51) 99999-9999" maxlength="16" data-discador-numero>
      <div class="discador-teclas" aria-hidden="true">
        <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'] as $t): ?><button type="button" tabindex="-1" data-tecla="<?= $t ?>" <?= $t === '' ? 'disabled' : '' ?>><?= $t ?></button><?php endforeach; ?>
      </div>
      <button class="botao lima largo" type="submit" data-discador-ligar>Ligar</button>
      <p class="discador-msg pequeno" data-discador-msg role="status"></p>
      <div class="discador-recentes" data-discador-recentes></div>
    </form>
  </div>
</div>
