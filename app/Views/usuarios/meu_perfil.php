<?php
/** Meu perfil. Variável: $u (usuário logado) */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">MINHA CONTA</div><h1>Meu perfil</h1>
  <p>Sua foto aparece no topo, no ranking da equipe e no card de aniversariantes.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/minha-senha') ?>">Trocar minha senha</a></div></div>
<form method="post" action="<?= url('/meu-perfil') ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <div class="grade-campos">
    <div class="campo"><label for="nome">Nome</label><input type="text" id="nome" name="nome" maxlength="120" required value="<?= e(antigo('nome', $u['nome'])) ?>"></div>
    <div class="campo"><label>E-mail de acesso</label><input type="email" value="<?= e($u['email']) ?>" disabled>
      <div class="ajuda"><?= e($u['perfil_nome']) ?><?= $u['funcoes'] ? ' · ' . e(implode(', ', array_column($u['funcoes'], 'nome'))) : '' ?></div></div>
  </div>
  <?= Visao::renderizar('usuarios/_ficha', ['u' => $u, 'completo' => false]) ?>
  <p class="suave pequeno">O que aparece no seu Início e no sino segue a sua função. Para mudar o e-mail de acesso, a função ou o que você acompanha, fale com o administrador.</p>
  <div class="acoes-form"><button class="botao" type="submit">Salvar</button></div>
</form>

<?php // Meu perfil público: o que os colegas veem em Equipe (tipo rede social)
$pp = $publico ?? [];
$v = fn(string $c) => e((string) ($pp[$c] ?? ''));
?>
<?php // Personalizar o perfil (V11.0): capa, cor, frase, interesses e ordem dos blocos
use App\Services\PerfilSocialService as Social;
$corAtual = Social::cor($pp);
$livre = !in_array($corAtual, Social::CORES, true);
?>
<form method="post" action="<?= url('/meu-perfil/personalizar') ?>" class="card" style="max-width:820px; --pe-cor: <?= e($corAtual) ?>" id="personalizar" data-personalizar>
  <?= csrf_campo() ?>
  <h2>🎨 Personalizar meu perfil</h2>
  <p class="suave pequeno">Deixe o seu perfil com a sua cara. Os colegas veem em <a href="<?= url('/pessoas/' . (int) $u['id']) ?>">Equipe</a>.</p>
  <div class="pp-previa" data-previa-capa style="background: <?= e(Social::fundoCapa($pp)) ?>"></div>
  <div class="campo"><label for="pp-frase">Minha frase</label><input type="text" id="pp-frase" name="frase" maxlength="140" value="<?= $v('frase') ?>" placeholder="Ex.: Cliente bem atendido sempre volta 😉"></div>
  <h3>Capa</h3>
  <p class="ajuda">Escolha uma da galeria<?= !empty($pp['capa']) ? ' (você tem uma foto de capa enviada: ela aparece no lugar desta; para usar a galeria, remova a foto em "Meu perfil público")' : ' ou envie a sua foto em "Meu perfil público", logo abaixo' ?>.</p>
  <div class="pp-capas">
    <?php foreach (Social::CAPAS as $chave => [$nome, $fundo]): ?>
      <label><input type="radio" name="capa_tema" value="<?= e($chave) ?>" data-fundo="<?= e($fundo) ?>" <?= ($pp['capa_tema'] ?? 'aurora') === $chave ? 'checked' : '' ?>><span style="background: <?= e($fundo) ?>"></span><?= e($nome) ?></label>
    <?php endforeach; ?>
  </div>
  <h3>Cor do perfil</h3>
  <div class="pp-cores" role="radiogroup" aria-label="Cor do perfil">
    <?php foreach (Social::CORES as $c): ?>
      <label title="<?= e($c) ?>"><input type="radio" name="cor_perfil" value="<?= e($c) ?>" <?= $corAtual === $c ? 'checked' : '' ?>><span style="background: <?= e($c) ?>"></span></label>
    <?php endforeach; ?>
    <label class="opcao pequeno"><input type="radio" name="cor_perfil" value="livre" <?= $livre ? 'checked' : '' ?>> Outra: <input type="color" name="cor_livre" value="<?= e($corAtual) ?>" aria-label="Escolher outra cor"></label>
  </div>
  <div class="grade-campos" style="margin-top:14px">
    <div class="campo"><label for="pp-interesses">Interesses</label><input type="text" id="pp-interesses" name="interesses" maxlength="300" value="<?= $v('interesses') ?>" placeholder="Ex.: corrida, café, suplementação, viagens"><div class="ajuda">Separe por vírgula. Viram etiquetas (#corrida).</div></div>
    <div class="campo"><label for="pp-pergunte">Pergunte-me sobre</label><input type="text" id="pp-pergunte" name="pergunte_sobre" maxlength="200" value="<?= $v('pergunte_sobre') ?>" placeholder="Ex.: fitoterápicos, Excel, receitas fit"><div class="ajuda">Assuntos em que você pode ajudar os colegas.</div></div>
  </div>
  <h3>Ordem dos blocos</h3>
  <p class="ajuda">Use ↑ ↓ (ou o número) para escolher a ordem. Desmarque para esconder um bloco do seu perfil.</p>
  <ol class="pp-blocos" data-ordem-blocos>
    <?php foreach (Social::blocos($pp['blocos'] ?? null) as $i => $b): ?>
      <li><input type="checkbox" name="mostrar[<?= e($b['chave']) ?>]" value="1" <?= $b['visivel'] ? 'checked' : '' ?> aria-label="Mostrar <?= e(Social::BLOCOS[$b['chave']]) ?>">
        <span><?= e(Social::BLOCOS[$b['chave']]) ?></span>
        <input type="number" name="ordem[<?= e($b['chave']) ?>]" value="<?= $i + 1 ?>" min="1" max="20" aria-label="Posição de <?= e(Social::BLOCOS[$b['chave']]) ?>">
        <span class="pp-mover"><button type="button" data-mover="cima" aria-label="Subir <?= e(Social::BLOCOS[$b['chave']]) ?>">↑</button><button type="button" data-mover="baixo" aria-label="Descer <?= e(Social::BLOCOS[$b['chave']]) ?>">↓</button></span></li>
    <?php endforeach; ?>
  </ol>
  <div class="acoes-form"><button class="botao" type="submit">Salvar personalização</button><a class="botao contorno" href="<?= url('/pessoas/' . (int) $u['id']) ?>">Ver meu perfil</a></div>
</form>

<form method="post" action="<?= url('/meu-perfil/publico') ?>" class="card" style="max-width:820px" enctype="multipart/form-data" id="perfil-publico" data-perfil-publico>
  <?= csrf_campo() ?>
  <h2>Meu perfil público</h2>
  <p class="suave pequeno">O que os colegas veem quando abrem o seu perfil em <a href="<?= url('/pessoas/' . (int) $u['id']) ?>">Equipe</a>. Tudo é opcional. O ano do seu aniversário nunca aparece.</p>
  <div class="campo"><label for="pp-sobre">Sobre mim</label><textarea id="pp-sobre" name="sobre_mim" rows="3" maxlength="600" placeholder="Ex.: Sou de São Leopoldo, trabalho com vendas há 8 anos e adoro conversar com os clientes."><?= $v('sobre_mim') ?></textarea></div>
  <div class="campo"><label for="pp-hobbies">Nas horas vagas (hobbies)</label><input type="text" id="pp-hobbies" name="hobbies" maxlength="300" value="<?= $v('hobbies') ?>" placeholder="Ex.: corrida, churrasco com a família, crochê"></div>
  <h3>Favoritos</h3>
  <div class="grade-campos">
    <div class="campo"><label for="pp-comida">🍽 Comida</label><input type="text" id="pp-comida" name="comida" maxlength="120" value="<?= $v('comida') ?>"></div>
    <div class="campo"><label for="pp-musica">🎵 Música</label><input type="text" id="pp-musica" name="musica" maxlength="120" value="<?= $v('musica') ?>"></div>
    <div class="campo"><label for="pp-filme">🎬 Filme ou série</label><input type="text" id="pp-filme" name="filme_serie" maxlength="120" value="<?= $v('filme_serie') ?>"></div>
    <div class="campo"><label for="pp-time">⚽ Time</label><input type="text" id="pp-time" name="time_futebol" maxlength="80" value="<?= $v('time_futebol') ?>"></div>
    <div class="campo"><label for="pp-lugar">📍 Lugar favorito</label><input type="text" id="pp-lugar" name="lugar_favorito" maxlength="120" value="<?= $v('lugar_favorito') ?>"></div>
  </div>
  <h3>Família e pets</h3>
  <div class="grade-campos">
    <div class="campo"><label for="pp-familia">Família</label><input type="text" id="pp-familia" name="familia" maxlength="300" value="<?= $v('familia') ?>" placeholder="Ex.: casada, mãe da Júlia (6 anos)"></div>
    <div class="campo"><label for="pp-pets">Pets</label><input type="text" id="pp-pets" name="pets" maxlength="200" value="<?= $v('pets') ?>" placeholder="Ex.: o Thor, um vira-lata muito folgado"></div>
    <div class="campo"><label for="pp-pet-foto">Foto do pet</label><input type="file" id="pp-pet-foto" name="pet_foto" accept="image/jpeg,image/png,image/webp">
      <?php if (!empty($pp['pet_foto'])): ?><label class="opcao pequeno"><input type="checkbox" name="remover_pet_foto" value="1"> Remover a foto do pet</label><?php endif; ?></div>
  </div>
  <h3>Capa e redes</h3>
  <div class="grade-campos">
    <div class="campo"><label for="pp-capa">Imagem de capa</label><input type="file" id="pp-capa" name="capa" accept="image/jpeg,image/png,image/webp"><div class="ajuda">Uma imagem larga (paisagem). Até 5 MB.</div>
      <?php if (!empty($pp['capa'])): ?><label class="opcao pequeno"><input type="checkbox" name="remover_capa" value="1"> Remover a capa</label><?php endif; ?></div>
    <div class="campo"><label for="pp-insta">Instagram</label><input type="text" id="pp-insta" name="instagram" maxlength="160" value="<?= $v('instagram') ?>" placeholder="@seuusuario"></div>
    <div class="campo"><label for="pp-linkedin">LinkedIn</label><input type="text" id="pp-linkedin" name="linkedin" maxlength="200" value="<?= $v('linkedin') ?>" placeholder="linkedin.com/in/..."></div>
  </div>
  <div class="acoes-form"><button class="botao" type="submit">Salvar perfil público</button><a class="botao contorno" href="<?= url('/pessoas/' . (int) $u['id']) ?>">Ver como os colegas veem</a></div>
</form>

<?php // Aparência: cores do sistema só para esta pessoa (a prévia muda na hora; salva com o botão)
[$corP, $corS] = \App\Services\Aparencia::daPessoa($u);
$propria = !empty($u['cor_primaria']) || !empty($u['cor_secundaria']);
$atual = 'personalizada';
foreach (\App\Services\Aparencia::COMBINACOES as $chave => [, $p, $s]) {
    if ($p === $corP && $s === $corS) { $atual = $chave; break; }
} ?>
<form method="post" action="<?= url('/meu-perfil/one') ?>" class="card mp-one" style="max-width:820px" id="one" data-meu-one>
  <?= csrf_campo() ?>
  <h2><img src="<?= asset('img/one/one-ola-padrao.webp') ?>" alt="" width="30" height="33" style="vertical-align:middle"> O One</h2>
  <label class="opcao"><input type="checkbox" name="one_balao" value="1" <?= (int) ($u['one_balao'] ?? 1) ? 'checked' : '' ?>>
    O One pode puxar conversa sozinho quando houver algo importante na tela (atrasados, cliente para recomprar, pedidos esperando…)</label>
  <p class="pequeno suave">Desligado, ele só fala quando você clica nele. As comemorações e a ajuda das telas continuam.</p>
  <button class="botao contorno" type="submit">Salvar</button>
</form>

<form method="post" action="<?= url('/meu-perfil/aparencia') ?>" class="card mp-aparencia" style="max-width:820px" data-aparencia>
  <?= csrf_campo() ?>
  <h2>Aparência</h2>
  <fieldset class="mp-tema" data-mp-tema>
    <legend>Tema</legend>
    <?php $temaAtual = \App\Services\Aparencia::tema(\App\Core\Auth::usuario()); ?>
    <?php foreach (['claro' => ['☀️', 'Claro'], 'escuro' => ['🌙', 'Escuro'], 'auto' => ['🖥️', 'Automático (segue o celular ou computador)']] as $v => [$ic, $rot]): ?>
      <label class="mp-tema-opcao"><input type="radio" name="tema" value="<?= $v ?>" <?= $temaAtual === $v ? 'checked' : '' ?>> <span><?= $ic ?> <?= e($rot) ?></span></label>
    <?php endforeach; ?>
    <p class="pequeno suave">Também dá para trocar com um clique no botão 🌙/☀️ do topo.</p>
  </fieldset>
  <p class="suave pequeno">Escolha as cores do seu One. A primária vai no menu, no topo e nos botões; a secundária nos destaques.
    Valem só para você<?= $propria ? '' : ' (agora você está com as cores da empresa)' ?>.</p>
  <div class="mp-combinacoes">
    <?php foreach (\App\Services\Aparencia::COMBINACOES as $chave => [$nome, $p, $s]): ?>
      <label class="mp-combinacao">
        <input type="radio" name="combinacao" value="<?= e($chave) ?>" data-primaria="<?= e($p) ?>" data-secundaria="<?= e($s) ?>" <?= $atual === $chave ? 'checked' : '' ?>>
        <span class="mp-amostra" style="--a:<?= e($p) ?>;--b:<?= e($s) ?>" aria-hidden="true"></span>
        <span><?= e($nome) ?></span>
      </label>
    <?php endforeach; ?>
    <label class="mp-combinacao">
      <input type="radio" name="combinacao" value="personalizada" <?= $atual === 'personalizada' ? 'checked' : '' ?>>
      <span class="mp-amostra mp-amostra-livre" aria-hidden="true"></span><span>Personalizada</span>
    </label>
  </div>
  <div class="mp-cores">
    <label>Cor primária <input type="color" name="cor_primaria" value="<?= e($corP) ?>"></label>
    <label>Cor secundária <input type="color" name="cor_secundaria" value="<?= e($corS) ?>"></label>
  </div>
  <div class="acoes-form">
    <button class="botao" type="submit">Salvar cores</button>
    <?php if ($propria): ?><button class="botao contorno" type="submit" name="padrao" value="1">Voltar ao padrão da empresa</button><?php endif; ?>
  </div>
</form>
