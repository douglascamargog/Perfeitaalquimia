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
    <?php $mascara = \App\Services\ChaveIA::mascarada(); ?>
    <form method="post" action="<?= url('/administracao/chave-ia') ?>" class="adm-chave" id="chave-ia" autocomplete="off"><?= csrf_campo() ?>
      <label for="chave-ia-campo"><strong>Chave da IA (Anthropic)</strong>
        <small class="suave"><?= $mascara ? 'Cadastrada: ' . e($mascara) . '. Para trocar, cole a nova.' : 'Nenhuma chave cadastrada. Crie em console.anthropic.com › API Keys e cole aqui.' ?></small></label>
      <div class="adm-chave-linha">
        <input type="password" id="chave-ia-campo" name="chave" placeholder="sk-ant-…" autocomplete="new-password" spellcheck="false" data-chave-ia>
        <button class="botao pequeno" type="submit">Guardar chave</button>
        <?php if ($mascara): ?><button class="botao contorno pequeno" type="submit" name="remover" value="1" formnovalidate data-confirmar="Remover a chave da IA? O One volta a responder sem IA.">Remover</button><?php endif; ?>
      </div>
      <small class="suave">A chave fica guardada só no servidor, num arquivo protegido (fora do banco de dados e dos pacotes) e não aparece de novo na tela.</small>
    </form>
    <form method="post" action="<?= url('/administracao/testar-ia') ?>" style="margin-top:10px"><?= csrf_campo() ?>
      <button class="botao contorno pequeno" type="submit" data-testar-ia>Testar a IA agora</button>
      <span class="pequeno suave">faz uma pergunta curtinha com a chave cadastrada (custa menos de 1 centavo)</span></form>
  </section>
<?php endif; ?>
