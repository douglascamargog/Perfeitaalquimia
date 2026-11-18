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
<?php if (\App\Core\Auth::ehDesenvolvedor() || \App\Core\Auth::pode('usuarios.gerenciar')): $devs = \App\Services\Desenvolvedor::lista(); ?>
  <section class="card" id="desenvolvedor" data-desenvolvedor style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">Desenvolvedor do sistema</h2>
    <p class="pequeno" style="margin:0 0 8px">Quem mantém o One: só essa pessoa vê o Organograma, a Atualização, o Importar Nota10, o Zerar dados e os Dados de teste,
      e pode trocar no topo da tela o jeito de ver o One ("Desenvolvedor", que vê tudo, ou como uma função, ex.: Gestor de Marketing). Nem o perfil Admin vê essas telas sem ser desenvolvedor.</p>
    <?php if (!$devs): ?>
      <form method="post" action="<?= url('/desenvolvedor/marcar') ?>"><?= csrf_campo() ?>
        <button class="botao pequeno" type="submit" data-sou-desenvolvedor data-confirmar="Marcar você como o desenvolvedor do sistema? Depois, só um desenvolvedor pode marcar outra pessoa.">Eu sou o desenvolvedor do sistema</button>
        <span class="pequeno suave">Ninguém marcado ainda: quem mantém o One clica aqui.</span></form>
    <?php else: ?>
      <div class="org-pessoas" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php foreach ($devs as $d): ?><span style="display:inline-flex;align-items:center;gap:6px"><?= \App\Services\EquipeService::avatar($d, 28) ?> <strong class="pequeno"><?= e($d['nome']) ?></strong>
          <?php if (\App\Core\Auth::ehDesenvolvedor() && count($devs) > 1): ?><form method="post" action="<?= url('/desenvolvedor/marcar') ?>" style="display:inline"><?= csrf_campo() ?>
            <input type="hidden" name="usuario" value="<?= (int) $d['id'] ?>"><button class="botao contorno pequeno" type="submit" name="acao" value="desmarcar">tirar</button></form><?php endif; ?></span><?php endforeach; ?>
      </div>
      <?php if (\App\Core\Auth::ehDesenvolvedor()): $outros = \App\Core\Banco::todos('SELECT id, nome FROM usuarios WHERE ativo = 1 AND desenvolvedor = 0 ORDER BY nome'); if ($outros): ?>
        <form method="post" action="<?= url('/desenvolvedor/marcar') ?>" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><?= csrf_campo() ?>
          <select name="usuario" aria-label="Pessoa"><?php foreach ($outros as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['nome']) ?></option><?php endforeach; ?></select>
          <button class="botao contorno pequeno" type="submit" data-confirmar="Essa pessoa também vai ver as telas de desenvolvimento. Continuar?">Marcar também como desenvolvedor</button></form>
      <?php endif; endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php if (\App\Core\Auth::pode('config.gerenciar')): $u = \App\Services\OnePergunta::usoDoMes(); ?>
  <section class="card adm-one" data-uso-one style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">Pergunte ao One <small class="suave"><?= \App\Services\OnePergunta::comIa() ? '✨ com IA (Claude)' : 'sem IA (aulas e dados do sistema)' ?></small></h2>
    <?php if ($semIa = \App\Services\OnePergunta::motivoSemIa()): ?><p class="aviso alerta" data-one-sem-ia>O One está respondendo <strong>sem IA</strong> (só com as aulas): <?= e($semIa) ?>. Os especialistas só funcionam com a IA ligada.</p><?php endif; ?>
    <?php $limitadas = (int) \App\Core\Banco::valor("SELECT COUNT(*) FROM one_perguntas WHERE situacao = 'limite' AND criado_em >= NOW() - INTERVAL 7 DAY");
      if ($limitadas): ?><p class="pequeno" data-one-limite>⚠️ <?= $limitadas ?> pergunta(s) nos últimos 7 dias passaram do limite por hora e foram respondidas sem IA.</p><?php endif; ?>
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
<?php if (\App\Core\Auth::pode('config.gerenciar')):
  $ltEmpresa = (string) config('integracoes.ligue_talk.company_id', '');
  $ltToken = (string) config('integracoes.ligue_talk.token', '');
  $ltModo = (string) \App\Core\Config::get('telefonia.modo', 'simulado');
  $ltUsuarios = \App\Core\Sessao::get('ligue_usuarios'); \App\Core\Sessao::remover('ligue_usuarios');
  $ramais = $ltUsuarios ? array_column(\App\Core\Banco::todos("SELECT nome, ramal FROM usuarios WHERE ativo = 1 AND ramal IS NOT NULL AND ramal <> ''"), 'nome', 'ramal') : [];
?>
  <section class="card adm-one" id="ligue-talk" data-ligue-talk style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">📞 Ligue Talk <small class="suave"><?= ['manual' => 'registro manual das ligações', 'simulado' => 'demonstração (ligações de exemplo)', 'api' => 'ligado à API do Ligue Talk'][$ltModo] ?? e($ltModo) ?></small></h2>
    <p class="pequeno" style="margin:0">Ligado à API, as ligações e gravações das vendedoras aparecem sozinhas no Acompanhamento e na ficha do cliente, e dá para mandar listas de clientes para o discador. Os dados (código da empresa e token da API) são pedidos ao suporte do Ligue Talk.</p>
    <form method="post" action="<?= url('/administracao/ligue-talk') ?>" class="adm-chave" autocomplete="off"><?= csrf_campo() ?>
      <div class="adm-chave-linha">
        <label style="flex:1;min-width:160px">Código da empresa (company_id)<input type="text" name="company_id" value="<?= e($ltEmpresa) ?>" placeholder="ex.: 1234" data-lt-empresa></label>
        <label style="flex:2;min-width:220px">Token da API <small class="suave"><?= $ltToken ? '(cadastrado: …' . e(mb_substr($ltToken, -4)) . '; para trocar, cole o novo)' : '(nenhum cadastrado)' ?></small><input type="password" name="token" placeholder="token X-LTK-VND" autocomplete="new-password" spellcheck="false" data-lt-token></label>
      </div>
      <div class="adm-chave-linha">
        <label>Modo das ligações
          <select name="modo" data-lt-modo>
            <option value="manual" <?= $ltModo === 'manual' ? 'selected' : '' ?>>Manual (registrar com 1 clique)</option>
            <option value="api" <?= $ltModo === 'api' ? 'selected' : '' ?>>API do Ligue Talk (automático)</option>
            <?php if (config('modo_demo')): ?><option value="simulado" <?= $ltModo === 'simulado' ? 'selected' : '' ?>>Demonstração</option><?php endif; ?>
          </select></label>
        <button class="botao pequeno" type="submit">Guardar</button>
        <?php if ($ltToken || $ltEmpresa): ?><button class="botao contorno pequeno" type="submit" name="remover" value="1" data-confirmar="Remover os dados do Ligue Talk? As ligações voltam ao registro manual.">Remover</button><?php endif; ?>
      </div>
      <small class="suave">O token fica guardado só no servidor, no mesmo arquivo protegido da chave da IA, e não aparece de novo.</small>
    </form>
    <form method="post" action="<?= url('/administracao/ligue-talk/testar') ?>" style="margin-top:10px"><?= csrf_campo() ?>
      <button class="botao contorno pequeno" type="submit" data-testar-ligue>Testar conexão</button>
      <span class="pequeno suave">consulta a lista de usuários do Ligue Talk</span></form>
    <?php if ($ltUsuarios): ?>
      <div class="pequeno" style="margin-top:10px" data-lt-usuarios>
        <strong>Usuários do Ligue Talk</strong> — para as ligações caírem na pessoa certa, preencha o "Usuário no Ligue Talk" de cada pessoa em <a href="<?= url('/usuarios') ?>">Pessoas e acessos</a>:
        <ul style="margin:6px 0 0;padding-left:18px">
          <?php foreach ($ltUsuarios as $lu): $dono = $ramais[$lu['id']] ?? null; ?>
            <li><?= e($lu['nome'] ?: '(sem nome)') ?> · id <code><?= e($lu['id']) ?></code> — <?= $dono ? '✅ ligado a ' . e($dono) : '⚠️ ainda não ligado a ninguém do One' ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>
  <?php
  $tApi = (string) config('integracoes.tray.api', '');
  $tKey = (string) config('integracoes.tray.consumer_key', '');
  $tSecret = (string) config('integracoes.tray.consumer_secret', '');
  $tCode = (string) config('integracoes.tray.code', '');
  $tModo = (string) \App\Core\Config::get('ecommerce.provedor', 'fake');
  $final = fn(string $v) => $v ? '(cadastrado: …' . e(mb_substr($v, -4)) . '; para trocar, cole o novo)' : '(nenhum cadastrado)';
  ?>
  <section class="card adm-one" id="tray" data-tray style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">🛒 Tray (loja virtual) <small class="suave"><?= $tModo === 'tray' ? 'ligado à loja' . ($tApi ? ' ' . e(parse_url($tApi, PHP_URL_HOST)) : '') : 'demonstração (números de exemplo)' ?></small></h2>
    <p class="pequeno" style="margin:0">Ligado à Tray, o painel do E-commerce mostra as vendas reais da loja e os pedidos pagos aparecem sozinhos no Depósito para separar. O consumer key e o consumer secret vêm no e-mail da Tray; o endereço da API e o code chegam sozinhos quando a loja instala o aplicativo.</p>
    <div class="pequeno" style="margin:8px 0;padding:8px 10px;border-radius:10px;background:var(--fundo)">
      <strong>URL de retorno (callback) do aplicativo</strong> — cadastre no painel de parceiro da Tray:<br>
      <code data-tray-retorno style="user-select:all;word-break:break-all"><?= e(\App\Controllers\AdministracaoController::urlRetornoTray()) ?></code>
    </div>
    <form method="post" action="<?= url('/administracao/tray') ?>" class="adm-chave" autocomplete="off"><?= csrf_campo() ?>
      <div class="adm-chave-linha">
        <label style="flex:2;min-width:240px">Endereço da API da loja (api_address)<input type="text" name="api" value="<?= e($tApi) ?>" placeholder="https://www.sualoja.com.br/web_api" spellcheck="false" data-tray-api></label>
        <label style="flex:1;min-width:180px">Code de instalação <small class="suave"><?= $final($tCode) ?></small><input type="password" name="code" autocomplete="new-password" spellcheck="false" data-tray-code></label>
      </div>
      <div class="adm-chave-linha">
        <label style="flex:1;min-width:200px">Consumer key <small class="suave"><?= $final($tKey) ?></small><input type="password" name="consumer_key" autocomplete="new-password" spellcheck="false" data-tray-key></label>
        <label style="flex:1;min-width:200px">Consumer secret <small class="suave"><?= $final($tSecret) ?></small><input type="password" name="consumer_secret" autocomplete="new-password" spellcheck="false" data-tray-secret></label>
      </div>
      <div class="adm-chave-linha">
        <label>Números do E-commerce
          <select name="provedor" data-tray-modo>
            <option value="tray" <?= $tModo === 'tray' ? 'selected' : '' ?>>Da loja Tray (reais)</option>
            <option value="fake" <?= $tModo !== 'tray' ? 'selected' : '' ?>>Demonstração (exemplo)</option>
          </select></label>
        <button class="botao pequeno" type="submit">Guardar</button>
        <?php if ($tApi || $tKey || $tSecret || $tCode): ?><button class="botao contorno pequeno" type="submit" name="remover" value="1" data-confirmar="Remover os dados da Tray? O E-commerce volta aos números de demonstração.">Remover</button><?php endif; ?>
      </div>
      <small class="suave">As chaves ficam guardadas só no servidor, no mesmo arquivo protegido da chave da IA, e não aparecem de novo. Nunca mande as chaves por WhatsApp ou e-mail.</small>
    </form>
    <form method="post" action="<?= url('/administracao/tray/conectar') ?>" class="adm-chave" style="margin-top:10px"><?= csrf_campo() ?>
      <div class="adm-chave-linha">
        <label style="flex:2;min-width:240px">Conectar à loja (depois de guardar o consumer key e o secret)<input type="text" name="loja" value="<?= e($tApi ? preg_replace('#/web_api$#', '', $tApi) : '') ?>" placeholder="https://sualoja.commercesuite.com.br" spellcheck="false" data-tray-loja></label>
        <button class="botao pequeno" type="submit" data-tray-conectar <?= ($tKey && $tSecret) ? '' : 'disabled' ?>>Conectar à loja Tray</button>
      </div>
      <small class="suave">Abre a loja para você autorizar o One (entre com o login da loja, se pedir). Ao confirmar, a Tray volta para cá e a loja fica conectada.</small>
    </form>
    <form method="post" action="<?= url('/administracao/tray/testar') ?>" style="margin-top:10px"><?= csrf_campo() ?>
      <button class="botao contorno pequeno" type="submit" data-testar-tray>Testar conexão</button>
      <span class="pequeno suave">gera o Access Token e lê o último pedido da loja</span></form>
  </section>
  <?php $prov = \App\Services\LocalizacaoService::provedor(); $gChave = \App\Services\LocalizacaoService::chaveGoogle(); $aChave = (string) config('integracoes.geoapify.chave', '');
        $chaveAtiva = \App\Services\LocalizacaoService::chave(); $geo = \App\Services\LocalizacaoService::resumo(); ?>
  <section class="card adm-one" id="google-maps" data-google-maps data-mapas style="margin-top:16px">
    <h2 style="margin:0 0 6px;font-size:1rem">🗺️ Mapas <small class="suave">ponto exato das lojas e rotas do Planejador de visitas</small></h2>
    <p class="pequeno" style="margin:0">Com a chave, o One transforma o endereço de cada loja em um ponto no mapa (aos poucos, de hora em hora) e calcula as rotas pelas estradas
      no Planejador de visitas. Só vai para o serviço o endereço comercial da loja. O botão "Rota no Google Maps" do celular não usa chave nem tem custo.</p>
    <form method="post" action="<?= url('/administracao/mapas/provedor') ?>" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin:10px 0" data-mapas-provedor><?= csrf_campo() ?>
      <strong class="pequeno">Serviço em uso:</strong>
      <label class="pequeno"><input type="radio" name="provedor" value="geoapify" <?= $prov === 'geoapify' ? 'checked' : '' ?> onchange="this.form.submit()"> Geoapify <small class="suave">(grátis até 3.000 créditos por dia, sem pré-pagamento)</small></label>
      <label class="pequeno"><input type="radio" name="provedor" value="google" <?= $prov === 'google' ? 'checked' : '' ?> onchange="this.form.submit()"> Google Maps <small class="suave">(pago, mais preciso no número da casa)</small></label>
      <noscript><button class="botao contorno pequeno" type="submit">Trocar</button></noscript>
    </form>
    <p class="pequeno" data-geo-resumo style="margin:8px 0"><strong><?= $geo['exatos'] ?></strong> de <?= $geo['total'] ?> loja(s) com endereço já estão no ponto exato
      (<?= $geo['manuais'] ?> ajustada(s) à mão) · <?= $geo['pendentes'] ?> esperando · <?= $geo['nao_achados'] ?> sem endereço preciso.</p>
    <?php if ($prov === 'geoapify'): ?>
      <p class="pequeno suave" style="margin:0 0 6px">Crie a conta grátis em geoapify.com › My Projects › crie um projeto › copie a <strong>API Key</strong> e cole aqui. Os mapas mostram "Powered by Geoapify", como pede o plano grátis.</p>
      <form method="post" action="<?= url('/administracao/geoapify') ?>" class="adm-chave" autocomplete="off" data-form-geoapify><?= csrf_campo() ?>
        <div class="adm-chave-linha">
          <label style="flex:1;min-width:240px">Chave da Geoapify <small class="suave"><?= $aChave ? '(cadastrada: …' . e(mb_substr($aChave, -4)) . '; para trocar, cole a nova)' : '(nenhuma cadastrada)' ?></small>
            <input type="password" name="chave" placeholder="cole a API Key" autocomplete="new-password" spellcheck="false" data-geoapify-chave></label>
          <button class="botao pequeno" type="submit">Guardar</button>
          <?php if ($aChave): ?><button class="botao contorno pequeno" type="submit" name="remover" value="1" data-confirmar="Remover a chave da Geoapify?">Remover</button><?php endif; ?>
        </div>
      </form>
    <?php else: ?>
      <p class="pequeno suave" style="margin:0 0 6px">A chave do Google é criada no Google Cloud (Credenciais › Criar chave de API) com a <strong>Geocoding API</strong> ativada. O Google pede cadastro de pagamento.</p>
      <form method="post" action="<?= url('/administracao/google-maps') ?>" class="adm-chave" autocomplete="off"><?= csrf_campo() ?>
        <div class="adm-chave-linha">
          <label style="flex:1;min-width:240px">Chave da API do Google Maps <small class="suave"><?= $gChave ? '(cadastrada: …' . e(mb_substr($gChave, -4)) . '; para trocar, cole a nova)' : '(nenhuma cadastrada)' ?></small>
            <input type="password" name="chave" placeholder="AIza..." autocomplete="new-password" spellcheck="false" data-google-chave></label>
          <button class="botao pequeno" type="submit">Guardar</button>
          <?php if ($gChave): ?><button class="botao contorno pequeno" type="submit" name="remover" value="1" data-confirmar="Remover a chave do Google Maps?">Remover</button><?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <form method="post" action="<?= url('/administracao/google-maps/testar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit" data-google-testar>Testar</button></form>
      <form method="post" action="<?= url('/administracao/google-maps/localizar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit" data-google-localizar <?= $chaveAtiva ? '' : 'disabled' ?>>Localizar agora</button></form>
      <span class="pequeno suave" style="align-self:center">Cron de hora em hora: <code>cron/geocodificar.php</code></span>
    </div>
  </section>
<?php endif; ?>
