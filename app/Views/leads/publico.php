<?php
/**
 * Página pública "Seja um revendedor" (para o site e os anúncios). Página própria, sem menu.
 * Variáveis: $utm (UTMs da URL), $enviado (mensagem de obrigado ou null), $erro (ou null)
 */
use App\Core\Config;

$empresa = (string) Config::get('empresa.nome', 'Perfeita Alquimia');
$ufs = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
$v = fn(string $c) => e((string) ($_POST[$c] ?? ''));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="<?= e(\App\Services\Aparencia::padrao()[0]) ?>">
  <title>Seja um revendedor · <?= e($empresa) ?></title>
  <meta name="description" content="Venda os suplementos e produtos naturais da <?= e($empresa) ?> na sua loja.">
  <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/prospeccao.css') ?>">
  <?= \App\Services\Aparencia::estilo(null, true) /* cores do sistema */ ?>
</head>
<body class="lp">
<?php if (config('modo_demo')): ?><div class="faixa-demo">Ambiente de demonstração — dados simulados</div><?php endif; ?>
<main class="lp-caixa">
  <section class="lp-chamada">
    <img src="<?= asset('img/logo.png') ?>" alt="<?= e($empresa) ?>" width="64" height="64">
    <p class="lp-sobre">PARA LOJISTAS E DISTRIBUIDORES</p>
    <h1>Venda <?= e($empresa) ?> na sua loja</h1>
    <p>Fabricante de suplementos e produtos naturais no Rio Grande do Sul, atendendo lojas de todo o Brasil. Deixe seu contato e um consultor fala com você pelo WhatsApp com a tabela para lojistas.</p>
    <ul><li>Preço de fábrica para revenda</li><li>Pedido pelo catálogo online, a qualquer hora</li><li>Treinamento e material de divulgação</li></ul>
  </section>
  <section class="lp-form card">
    <?php if ($enviado): ?>
      <div class="lp-obrigado" data-obrigado><strong>Obrigado!</strong><p><?= e($enviado) ?></p></div>
    <?php else: ?>
      <?php if ($erro): ?><div class="aviso erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
      <form method="post" action="<?= url('/seja-revendedor') ?>" data-form-lead>
        <?= csrf_campo() ?>
        <input type="hidden" name="inicio" value="<?= time() ?>">
        <?php foreach (($_POST ? array_intersect_key($_POST, array_flip(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'])) : $utm) as $k => $val): ?>
          <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $val) ?>">
        <?php endforeach; ?>
        <div class="lp-escondido" aria-hidden="true"><label>Não preencha <input type="text" name="site_url" tabindex="-1" autocomplete="off"></label></div>
        <div class="campo"><label for="nome">Seu nome</label><input type="text" id="nome" name="nome" required maxlength="120" value="<?= $v('nome') ?>" autocomplete="name"></div>
        <div class="campo"><label for="empresa">Nome da loja</label><input type="text" id="empresa" name="empresa" maxlength="200" value="<?= $v('empresa') ?>" autocomplete="organization"></div>
        <div class="grade-campos">
          <div class="campo"><label for="whatsapp">WhatsApp</label><input type="tel" id="whatsapp" name="whatsapp" maxlength="20" value="<?= $v('whatsapp') ?>" placeholder="(51) 99999-9999" autocomplete="tel"></div>
          <div class="campo"><label for="email">E-mail</label><input type="email" id="email" name="email" maxlength="160" value="<?= $v('email') ?>" autocomplete="email"></div>
        </div>
        <div class="grade-campos">
          <div class="campo"><label for="cnpj">CNPJ (opcional)</label><input type="text" id="cnpj" name="cnpj" maxlength="20" value="<?= $v('cnpj') ?>" inputmode="numeric"></div>
          <div class="campo"><label for="cidade">Cidade</label><input type="text" id="cidade" name="cidade" maxlength="120" value="<?= $v('cidade') ?>"></div>
          <div class="campo"><label for="uf">UF</label><select id="uf" name="uf"><option value="">—</option><?php foreach ($ufs as $uf): ?><option <?= ($_POST['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="campo"><label for="mensagem">Mensagem (opcional)</label><textarea id="mensagem" name="mensagem" rows="3" maxlength="1000"><?= $v('mensagem') ?></textarea></div>
        <button class="botao lima largo" type="submit">Quero ser revendedor</button>
        <p class="suave pequeno">Usamos seus dados só para entrar em contato sobre a revenda (LGPD).</p>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
