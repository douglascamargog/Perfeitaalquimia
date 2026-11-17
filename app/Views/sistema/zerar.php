<?php
/**
 * Administração › Zerar dados (só o admin). Variáveis: $contagem (grupo => registros), $semClassificacao, $resultado (depois de zerar)
 */
$total = array_sum($contagem);
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO · ZERAR DADOS</div><h1>Zerar os dados do One</h1>
    <p>Apaga tudo o que foi criado no dia a dia para recomeçar com os dados reais (por exemplo, a importação do Nota10).
      Usuários, produtos, catálogo, configurações e treinamento continuam.</p></div>
</div>

<?php if ($resultado): ?>
  <div class="aviso sucesso" data-zerado>Pronto: o One foi zerado. Backup feito antes: <strong><?= e($resultado['backup']) ?></strong>
    (pasta storage/backups do servidor). Para voltar como estava, importe esse arquivo no phpMyAdmin.</div>
<?php endif; ?>

<div class="aviso alerta">Isto não tem botão de desfazer. Antes de apagar, o One faz um backup completo do banco; se precisar voltar,
  é por esse backup (phpMyAdmin › Importar). Recomendado: avise a equipe e faça fora do horário de trabalho.</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
  <section class="card" data-zerar-apaga>
    <h2>🗑️ É apagado</h2>
    <dl style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
      <?php foreach ($contagem as $grupo => $n): ?>
        <div><dt class="pequeno suave"><?= e($grupo) ?></dt><dd style="margin:0;font-weight:700"><?= number_format($n, 0, ',', '.') ?> registro(s)</dd></div>
      <?php endforeach; ?>
    </dl>
    <p class="pequeno suave">Também saem os arquivos anexados a esses dados (prints da linha do tempo, cotações, documentos de matérias-primas, gravações).</p>
  </section>
  <section class="card" data-zerar-mantem>
    <h2>✅ Continua como está</h2>
    <ul style="margin:0;padding-left:18px;line-height:1.7">
      <li>Usuários: logins, senhas, perfis, funções, acessos e o perfil de cada pessoa</li>
      <li>Produtos e catálogo: produtos, categorias, fotos, ficha, documentos, tabela nutricional, preços e estoque</li>
      <li>Configurações e chaves (Tray, Google Maps, IA, Ligue Talk), funis, segmentos, regiões, tabelas de preço, formas de pagamento</li>
      <li>Academia, treinamento, tutoriais e o progresso de cada pessoa</li>
      <li>Mensagens prontas, especialistas do One, pedidos do e-commerce (Tray) e a auditoria</li>
    </ul>
  </section>
</div>

<?php if ($semClassificacao): ?>
  <p class="pequeno suave">Tabelas que o One ainda não sabe classificar (ficam como estão): <?= e(implode(', ', $semClassificacao)) ?>.</p>
<?php endif; ?>

<section class="card" style="margin-top:14px">
  <h2>Zerar agora</h2>
  <?php if (!$total): ?>
    <p class="suave" data-zerar-vazio>Não há dados do dia a dia para apagar: o One já está limpo.</p>
  <?php else: ?>
    <form method="post" action="<?= url('/sistema/zerar') ?>" class="pd-form" style="max-width:420px"
          data-confirmar="Apagar <?= number_format($total, 0, ',', '.') ?> registro(s) do One? O backup é feito antes."><?= csrf_campo() ?>
      <label>Sua senha<input type="password" name="senha" autocomplete="current-password" required></label>
      <label>Para confirmar, digite <strong><?= e(\App\Services\ZerarDadosService::FRASE) ?></strong><input type="text" name="confirmar" autocomplete="off" required></label>
      <button class="botao contorno" type="submit" style="color:var(--perigo);border-color:var(--perigo)" data-zerar-botao>Fazer backup e zerar os dados</button>
    </form>
  <?php endif; ?>
</section>
