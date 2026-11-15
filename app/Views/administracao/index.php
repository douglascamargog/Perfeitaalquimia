<?php
/** Administração (engrenagem do topo). Variáveis: $itens (telas de administração que a pessoa pode abrir) */
$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">SISTEMA</div><h1>Administração</h1>
    <p>Pessoas e acessos, regras do sistema, integrações e atualizações. Escolha o que quer ajustar.</p></div>
</div>
<div class="adm-grade">
  <?php foreach ($itens as $i): ?>
    <a class="adm-cartao" href="<?= url($i['url']) ?>">
      <span class="adm-icone"><svg class="icone"><use href="<?= $icones ?>#<?= e($i['icone']) ?>"/></svg></span>
      <span><strong><?= e($i['titulo']) ?></strong><small><?= e($i['descricao'] ?? '') ?></small></span>
      <?php if ($i['leitura']): ?><span class="adm-leitura" title="Só leitura">👁</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php if (\App\Core\Auth::pode('config.gerenciar')): $u = \App\Services\OnePergunta::usoDoMes(); ?>
  <section class="card adm-one" data-uso-one style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">Pergunte ao One <small class="suave"><?= \App\Services\OnePergunta::comIa() ? '✨ com IA (Claude)' : 'sem IA (aulas e dados do sistema)' ?></small></h2>
    <p class="pequeno" style="margin:0"><?= (int) ($u['perguntas'] ?? 0) ?> pergunta(s) este mês, de <?= (int) ($u['pessoas'] ?? 0) ?> pessoa(s)<?= (int) ($u['com_ia'] ?? 0) ? ' · ' . (int) $u['com_ia'] . ' com IA · custo estimado US$ ' . number_format((float) $u['custo'], 2, ',', '.') : '' ?>.
      Ligar ou desligar a IA, o acesso a dados de clientes (LGPD) e o limite por hora: <a href="<?= url('/configuracoes') ?>">Configurações › One (IA)</a>. O texto das perguntas não é guardado.</p>
    <form method="post" action="<?= url('/administracao/testar-ia') ?>" style="margin-top:10px"><?= csrf_campo() ?>
      <button class="botao contorno pequeno" type="submit" data-testar-ia>Testar a IA agora</button>
      <span class="pequeno suave">faz uma pergunta curtinha com a chave do config.php (custa menos de 1 centavo)</span></form>
  </section>
<?php endif; ?>
