<?php
/**
 * Perfil de uma pessoa, estilo rede social (sem publicações): capa, foto com status do dia, frase, selos,
 * blocos na ordem que a pessoa escolheu (Sobre, Interesses, Conquistas, Elogios, Recados, Favoritos, Família).
 * Variáveis: $p, $eu, $aniversarioHoje, $mensagens, $recados, $elogios, $resumoElogios, $conquistas, $tiposElogio, $blocos
 */
use App\Core\Auth;
use App\Core\Visao;
use App\Services\EquipeService;
use App\Services\PerfilSocialService as Social;
use App\Services\PessoaService;

$gostos = ['comida' => ['🍽', 'Comida favorita'], 'musica' => ['🎵', 'Música'], 'filme_serie' => ['🎬', 'Filme ou série'],
    'time_futebol' => ['⚽', 'Time'], 'lugar_favorito' => ['📍', 'Lugar favorito']];
$temGosto = array_filter(array_keys($gostos), fn($k) => !empty($p[$k]));
$desde = $p['data_admissao'] ? ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) substr($p['data_admissao'], 5, 2) - 1] . ' de ' . substr($p['data_admissao'], 0, 4) : null;
$status = Social::status($p);
$cor = Social::cor($p);
$primeiro = explode(' ', trim($p['nome']))[0];
$admin = Auth::pode('usuarios.gerenciar');
$interesses = Social::etiquetas($p['interesses'] ?? null);
$pergunte = Social::etiquetas($p['pergunte_sobre'] ?? null);
$totalElogios = array_sum(array_column($resumoElogios, 'total'));
?>
<article class="pe-perfil" style="--pe-cor: <?= e($cor) ?>" data-perfil="<?= (int) $p['id'] ?>">
  <header class="pe-hero">
    <div class="pe-capa" style="background: <?= e(Social::fundoCapa($p)) ?>"></div>
    <div class="pe-hero-corpo">
      <div class="pe-foto-area">
        <span class="pe-foto"><?= EquipeService::avatar($p, 140) ?></span>
        <?php if ($status): ?><span class="pe-status-emoji" title="<?= e($status['texto']) ?>" aria-hidden="true"><?= e($status['emoji']) ?></span><?php endif; ?>
      </div>
      <div class="pe-id-txt">
        <h1><?= e($p['nome']) ?><?= $aniversarioHoje ? ' <span class="pe-festa">🎉 aniversário hoje</span>' : '' ?></h1>
        <?php if ($status): ?><span class="pe-status" data-status title="Status do dia"><b><?= e($status['emoji']) ?></b><?= $status['texto'] !== '' ? ' ' . e($status['texto']) : '' ?></span><?php endif; ?>
        <?php if (!empty($p['frase'])): ?><p class="pe-frase" data-frase>“<?= e($p['frase']) ?>”</p><?php endif; ?>
        <p class="pe-cargo"><?= e($p['funcoes'] ?: $p['perfil_nome']) ?><?= $desde ? ' · na empresa desde ' . e($desde) : '' ?></p>
        <div class="pe-redes">
          <?php if ($p['data_nascimento']): ?><span>🎂 <?= e(PessoaService::aniversario($p['data_nascimento'])) ?></span><?php endif; ?>
          <?php if ($p['whatsapp']): ?><a href="https://wa.me/55<?= e($p['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <?php if ($p['instagram']): ?><a href="<?= e($p['instagram']) ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
          <?php if ($p['linkedin']): ?><a href="<?= e($p['linkedin']) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a><?php endif; ?>
        </div>
      </div>
      <div class="pe-hero-acoes">
        <?php if ($eu): ?>
          <a class="botao contorno pequeno" href="<?= url('/meu-perfil#personalizar') ?>">🎨 Personalizar</a>
          <a class="botao contorno pequeno" href="<?= url('/meu-perfil#perfil-publico') ?>">Editar perfil</a>
        <?php else: ?>
          <a class="botao pequeno pe-botao-cor" href="#elogiar" data-abrir-elogio>💛 Elogiar</a>
          <a class="botao contorno pequeno" href="#recados" data-ir-recado>💬 Deixar recado</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($eu): ?>
      <form class="pe-status-form" method="post" action="<?= url('/meu-perfil/status') ?>" data-form-status>
        <?= csrf_campo() ?><input type="hidden" name="voltar" value="/pessoas/<?= (int) $p['id'] ?>">
        <span class="pe-status-rotulo">Status do dia</span>
        <div class="pe-emojis" role="radiogroup" aria-label="Emoji do status">
          <?php foreach (Social::EMOJIS as $em): ?>
            <label><input type="radio" name="status_emoji" value="<?= e($em) ?>" <?= ($status['emoji'] ?? '') === $em ? 'checked' : '' ?>><span><?= e($em) ?></span></label>
          <?php endforeach; ?>
        </div>
        <input type="text" name="status_texto" maxlength="80" value="<?= e($status['texto'] ?? '') ?>" placeholder="Ex.: focada em ligações hoje" aria-label="Texto do status">
        <button class="botao pequeno" type="submit">Publicar</button>
        <?php if ($status): ?><button class="botao contorno pequeno" type="submit" name="apagar" value="1">Apagar</button><?php endif; ?>
      </form>
    <?php endif; ?>

    <div class="pe-numeros">
      <a href="#elogios"><strong data-total-elogios><?= $totalElogios ?></strong><span>elogio<?= $totalElogios === 1 ? '' : 's' ?></span></a>
      <a href="#recados"><strong><?= count($recados) ?></strong><span>recado<?= count($recados) === 1 ? '' : 's' ?></span></a>
      <a href="#conquistas"><strong><?= count($conquistas) ?></strong><span>conquista<?= count($conquistas) === 1 ? '' : 's' ?></span></a>
      <?php if ($resumoElogios): ?><span class="pe-numeros-top" title="O que mais recebe"><?php foreach (array_slice($resumoElogios, 0, 3) as $r): ?><b title="<?= e($r['nome']) ?>"><?= e($r['emoji']) ?> <?= (int) $r['total'] ?></b><?php endforeach; ?></span><?php endif; ?>
    </div>
  </header>

  <?php if (!$eu): ?>
    <section class="card pe-elogiar" id="elogiar" data-painel-elogio hidden>
      <h2>Elogiar <?= e($primeiro) ?></h2>
      <form method="post" action="<?= url('/pessoas/' . (int) $p['id'] . '/elogios') ?>" data-form-elogio>
        <?= csrf_campo() ?>
        <div class="pe-tipos-elogio" role="radiogroup" aria-label="Tipo de elogio">
          <?php foreach ($tiposElogio as $chave => $t): ?>
            <label><input type="radio" name="tipo" value="<?= e($chave) ?>" required><span><b><?= e($t['emoji']) ?></b><?= e($t['nome']) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="campo"><label for="el-texto">Quer contar por quê? <small>(opcional)</small></label>
          <input type="text" id="el-texto" name="texto" maxlength="300" placeholder="Ex.: me ajudou a fechar o pedido da Drogaria Vital!"></div>
        <div class="acoes-form"><button class="botao pe-botao-cor" type="submit">Enviar elogio</button><button class="botao contorno" type="button" data-fechar-elogio>Cancelar</button></div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($aniversarioHoje): ?>
    <section class="card pe-mural" id="mural-aniversario"><h2>🎉 Mural de parabéns</h2>
      <?= Visao::renderizar('pessoas/_mural', ['a' => $p, 'mensagens' => $mensagens, 'voltar' => 'perfil']) ?></section>
  <?php endif; ?>

  <div class="pe-blocos">
    <?php foreach ($blocos as $b): if (!$b['visivel']) continue; ?>
      <?php switch ($b['chave']):
        case 'sobre': if (!$p['sobre_mim'] && !$p['hobbies'] && !$eu) break; ?>
          <section class="card pe-bloco" data-bloco="sobre"><h2>Sobre</h2>
            <?php if (!$p['sobre_mim'] && !$p['hobbies']): ?><p class="suave">Conte um pouco sobre você em <a href="<?= url('/meu-perfil#perfil-publico') ?>">Meu perfil</a>.</p><?php endif; ?>
            <?php if ($p['sobre_mim']): ?><p class="pe-sobre"><?= nl2br(e($p['sobre_mim'])) ?></p><?php endif; ?>
            <?php if ($p['hobbies']): ?><h3>Nas horas vagas</h3><p><?= nl2br(e($p['hobbies'])) ?></p><?php endif; ?>
          </section>
        <?php break;
        case 'interesses': if (!$interesses && !$pergunte && !$eu) break; ?>
          <section class="card pe-bloco" data-bloco="interesses"><h2>Interesses</h2>
            <?php if (!$interesses && !$pergunte): ?><p class="suave">Coloque seus interesses em <a href="<?= url('/meu-perfil#personalizar') ?>">Personalizar</a>.</p><?php endif; ?>
            <?php if ($interesses): ?><div class="pe-tags"><?php foreach ($interesses as $t): ?><span>#<?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if ($pergunte): ?><h3>Pergunte-me sobre</h3><div class="pe-tags pe-tags-pergunte"><?php foreach ($pergunte as $t): ?><span>💬 <?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
          </section>
        <?php break;
        case 'conquistas': ?>
          <section class="card pe-bloco" id="conquistas" data-bloco="conquistas"><h2>Conquistas</h2>
            <?php if (!$conquistas): ?><p class="suave">As conquistas aparecem aqui: tempo de casa, metas batidas, elogios recebidos…</p><?php endif; ?>
            <ul class="pe-selos"><?php foreach ($conquistas as [$em, $tit, $det]): ?>
              <li><span class="pe-selo-icone"><?= e($em) ?></span><div><strong><?= e($tit) ?></strong><small><?= e($det) ?></small></div></li>
            <?php endforeach; ?></ul>
          </section>
        <?php break;
        case 'elogios': ?>
          <section class="card pe-bloco" id="elogios" data-bloco="elogios">
            <header class="pe-bloco-topo"><h2>Elogios</h2><?php if (!$eu): ?><a class="botao contorno pequeno" href="#elogiar" data-abrir-elogio>💛 Elogiar</a><?php endif; ?></header>
            <?php if ($resumoElogios): ?><div class="pe-resumo-elogios"><?php foreach ($resumoElogios as $r): ?><span><b><?= e($r['emoji']) ?></b> <?= e($r['nome']) ?> <em>× <?= (int) $r['total'] ?></em></span><?php endforeach; ?></div><?php endif; ?>
            <?php if (!$elogios): ?><p class="suave"><?= $eu ? 'Quando um colega te elogiar, aparece aqui.' : 'Seja a primeira pessoa a elogiar ' . e($primeiro) . '!' ?></p><?php endif; ?>
            <ul class="pe-lista" data-elogios><?php foreach ($elogios as $el): ?>
              <li data-elogio="<?= (int) $el['id'] ?>"><?= EquipeService::avatar(['nome' => $el['autor'], 'foto' => $el['autor_foto']], 34) ?>
                <div><p><strong><?= e($el['autor']) ?></strong> elogiou: <b><?= e($el['emoji']) ?> <?= e($el['nome_tipo']) ?></b></p>
                  <?php if ($el['texto']): ?><p class="pe-lista-texto"><?= e($el['texto']) ?></p><?php endif; ?>
                  <small><?= data_br($el['criado_em'], true) ?></small></div>
                <?php if ((int) $el['de_id'] === Auth::id() || $admin): ?><form method="post" action="<?= url('/pessoas/elogios/' . (int) $el['id'] . '/apagar') ?>" data-confirmar="Remover este elogio?"><?= csrf_campo() ?><button class="pe-x" type="submit" title="Remover" aria-label="Remover elogio">×</button></form><?php endif; ?>
              </li>
            <?php endforeach; ?></ul>
          </section>
        <?php break;
        case 'recados': ?>
          <section class="card pe-bloco" id="recados" data-bloco="recados"><h2>Recados</h2>
            <form class="pe-recado-form" method="post" action="<?= url('/pessoas/' . (int) $p['id'] . '/recados') ?>" data-form-recado>
              <?= csrf_campo() ?>
              <?= EquipeService::avatar(['nome' => Auth::usuario()['nome'] ?? '', 'foto' => Auth::usuario()['foto'] ?? null], 34) ?>
              <textarea name="texto" rows="2" maxlength="500" required placeholder="<?= $eu ? 'Escreva um recado no seu mural…' : 'Deixe um recado para ' . e($primeiro) . '…' ?>" aria-label="Recado"></textarea>
              <button class="botao pequeno pe-botao-cor" type="submit">Enviar</button>
            </form>
            <?php if (!$recados): ?><p class="suave">Nenhum recado ainda.</p><?php endif; ?>
            <ul class="pe-lista" data-recados><?php foreach ($recados as $r): ?>
              <li data-recado="<?= (int) $r['id'] ?>"><a href="<?= url('/pessoas/' . (int) $r['autor_id']) ?>"><?= EquipeService::avatar(['nome' => $r['autor'], 'foto' => $r['autor_foto']], 34) ?></a>
                <div><p><a class="pe-autor" href="<?= url('/pessoas/' . (int) $r['autor_id']) ?>"><?= e($r['autor']) ?></a></p>
                  <p class="pe-lista-texto"><?= nl2br(e($r['texto'])) ?></p><small><?= data_br($r['criado_em'], true) ?></small></div>
                <?php if (in_array(Auth::id(), [(int) $r['autor_id'], (int) $r['perfil_id']], true) || $admin): ?><form method="post" action="<?= url('/pessoas/recados/' . (int) $r['id'] . '/apagar') ?>" data-confirmar="Remover este recado?"><?= csrf_campo() ?><button class="pe-x" type="submit" title="Remover" aria-label="Remover recado">×</button></form><?php endif; ?>
              </li>
            <?php endforeach; ?></ul>
          </section>
        <?php break;
        case 'favoritos': if (!$temGosto) break; ?>
          <section class="card pe-bloco" data-bloco="favoritos"><h2>Favoritos</h2>
            <div class="pe-gostos"><?php foreach ($gostos as $k => [$icone, $rotulo]): if (empty($p[$k])) continue; ?>
              <div class="pe-gosto"><span aria-hidden="true"><?= $icone ?></span><small><?= $rotulo ?></small><strong><?= e($p[$k]) ?></strong></div>
            <?php endforeach; ?></div></section>
        <?php break;
        case 'familia': if (!$p['familia'] && !$p['pets'] && !$p['pet_foto']) break; ?>
          <section class="card pe-bloco pe-familia" data-bloco="familia"><h2>Família e pets</h2>
            <?php if ($p['familia']): ?><p>👨‍👩‍👧 <?= nl2br(e($p['familia'])) ?></p><?php endif; ?>
            <?php if ($p['pets'] || $p['pet_foto']): ?><div class="pe-pet"><?php if ($p['pet_foto']): ?><img src="<?= e(url('/' . $p['pet_foto'])) ?>" alt="Pet de <?= e($p['nome']) ?>" width="120" height="120"><?php endif; ?><p>🐾 <?= e($p['pets'] ?? '') ?></p></div><?php endif; ?>
          </section>
        <?php break;
      endswitch; ?>
    <?php endforeach; ?>
  </div>
</article>
