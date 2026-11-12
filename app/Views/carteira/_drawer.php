<?php /** Gaveta lateral onde a ficha do cliente é carregada (pelo carteira.js). $url opcional: outro conteúdo, ex. '/cobranca/{id}/caso' */ ?>
<div class="gaveta-fundo" data-gaveta-fundo hidden></div>
<aside class="gaveta" data-gaveta <?= !empty($url) ? 'data-url="' . e($url) . '"' : '' ?> role="dialog" aria-modal="true" aria-label="<?= e($rotulo ?? 'Ficha do estabelecimento') ?>" hidden>
  <div class="gaveta-conteudo" data-gaveta-conteudo></div>
</aside>
<div class="aviso-flutuante" data-aviso-flutuante role="status" aria-live="polite"></div>
