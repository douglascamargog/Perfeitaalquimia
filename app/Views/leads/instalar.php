<?php
/** Como colocar o formulário no site. Variáveis: $endpoint, $script, $pagina, $dominios */
$codigo = <<<HTML
<form data-one-lead>
  <input name="nome" placeholder="Seu nome" required>
  <input name="empresa" placeholder="Nome da loja">
  <input name="whatsapp" placeholder="WhatsApp" required>
  <input name="email" type="email" placeholder="E-mail">
  <input name="cidade" placeholder="Cidade">
  <button type="submit">Quero ser revendedor</button>
  <p data-one-resposta></p>
</form>
<script src="$script" data-endpoint="$endpoint" defer></script>
HTML;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo"><a href="<?= url('/leads') ?>">LEADS DO SITE</a></div><h1>Instalar o formulário no site</h1>
  <p>Duas formas. As duas guardam a campanha do anúncio (UTM) e mandam o lead direto para o funil.</p></div></div>
<div class="pr-duas">
  <section class="card">
    <h2>1. Mais simples: link para a nossa página</h2>
    <p>Nos anúncios (Instagram, Google) e no botão do site, use o link abaixo. Acrescente as UTMs da campanha no final.</p>
    <pre class="ld-codigo"><?= e($pagina) ?>?utm_source=instagram&amp;utm_medium=cpc&amp;utm_campaign=nome-da-campanha</pre>
    <p class="suave pequeno">utm_medium = cpc (ou paid/ads) marca o lead como "Anúncio (tráfego pago)"; sem isso, entra como "Site".</p>
  </section>
  <section class="card">
    <h2>2. Formulário dentro do site</h2>
    <p>Cole este código na página do site (quem cuida do site faz em 5 minutos). Os campos e o visual podem ser mudados à vontade: só os <code>name</code> precisam ficar iguais.</p>
    <pre class="ld-codigo" data-codigo><?= e($codigo) ?></pre>
    <button class="botao contorno pequeno" type="button" onclick="navigator.clipboard.writeText(document.querySelector('[data-codigo]').textContent).then(() => this.textContent = 'Copiado ✓')">Copiar código</button>
    <p class="suave pequeno">Campos aceitos: nome, empresa, cnpj, whatsapp, email, cidade, uf, mensagem. As UTMs da URL são lidas sozinhas.</p>
    <p class="suave pequeno">Sites autorizados: <?= $dominios ? e(implode(', ', $dominios)) : 'qualquer site (para restringir, preencha em Configurações › Captura de leads)' ?>.</p>
  </section>
</div>
