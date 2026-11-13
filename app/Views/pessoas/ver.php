<?php
/** Perfil de uma pessoa. Variáveis: $p, $eu (é o meu), $aniversarioHoje, $mensagens */
use App\Core\Visao;
use App\Services\EquipeService;
use App\Services\PessoaService;

$gostos = ['comida' => ['🍽', 'Comida favorita'], 'musica' => ['🎵', 'Música'], 'filme_serie' => ['🎬', 'Filme ou série'],
    'time_futebol' => ['⚽', 'Time'], 'lugar_favorito' => ['📍', 'Lugar favorito']];
$temGosto = array_filter(array_keys($gostos), fn($k) => !empty($p[$k]));
$desde = $p['data_admissao'] ? ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) substr($p['data_admissao'], 5, 2) - 1] . ' de ' . substr($p['data_admissao'], 0, 4) : null;
?>
<article class="pe-perfil">
  <header class="pe-topo">
    <div class="pe-capa" <?= $p['capa'] ? 'style="background-image:url(\'' . e(url('/' . $p['capa'])) . '\')"' : '' ?>></div>
    <div class="pe-id">
      <span class="pe-foto"><?= EquipeService::avatar($p, 128) ?></span>
      <div>
        <h1><?= e($p['nome']) ?><?= $aniversarioHoje ? ' <span class="pe-festa">🎉 aniversário hoje</span>' : '' ?></h1>
        <p><?= e($p['funcoes'] ?: $p['perfil_nome']) ?><?= $desde ? ' · na empresa desde ' . e($desde) : '' ?></p>
        <div class="pe-redes">
          <?php if ($p['data_nascimento']): ?><span>🎂 <?= e(PessoaService::aniversario($p['data_nascimento'])) ?></span><?php endif; ?>
          <?php if ($p['whatsapp']): ?><a href="https://wa.me/55<?= e($p['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <?php if ($p['instagram']): ?><a href="<?= e($p['instagram']) ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
          <?php if ($p['linkedin']): ?><a href="<?= e($p['linkedin']) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a><?php endif; ?>
        </div>
      </div>
      <?php if ($eu): ?><a class="botao contorno pequeno pe-editar" href="<?= url('/meu-perfil#perfil-publico') ?>">Editar meu perfil</a><?php endif; ?>
    </div>
  </header>

  <?php if ($aniversarioHoje): ?>
    <section class="card pe-mural" id="mural-aniversario"><h2>🎉 Mural de parabéns</h2>
      <?= Visao::renderizar('pessoas/_mural', ['a' => $p, 'mensagens' => $mensagens, 'voltar' => 'perfil']) ?></section>
  <?php endif; ?>

  <div class="pe-corpo">
    <section class="card">
      <h2>Sobre</h2>
      <?php if (!$p['sobre_mim'] && !$p['hobbies']): ?><p class="suave"><?= $eu ? 'Conte um pouco sobre você em Meu perfil › Meu perfil público.' : 'Ainda não contou nada por aqui.' ?></p><?php endif; ?>
      <?php if ($p['sobre_mim']): ?><p class="pe-sobre"><?= nl2br(e($p['sobre_mim'])) ?></p><?php endif; ?>
      <?php if ($p['hobbies']): ?><h3>Nas horas vagas</h3><p><?= nl2br(e($p['hobbies'])) ?></p><?php endif; ?>
    </section>
    <?php if ($temGosto): ?>
      <section class="card"><h2>Favoritos</h2>
        <div class="pe-gostos"><?php foreach ($gostos as $k => [$icone, $rotulo]): if (empty($p[$k])) continue; ?>
          <div class="pe-gosto"><span aria-hidden="true"><?= $icone ?></span><small><?= $rotulo ?></small><strong><?= e($p[$k]) ?></strong></div>
        <?php endforeach; ?></div></section>
    <?php endif; ?>
    <?php if ($p['familia'] || $p['pets'] || $p['pet_foto']): ?>
      <section class="card pe-familia"><h2>Família e pets</h2>
        <?php if ($p['familia']): ?><p>👨‍👩‍👧 <?= nl2br(e($p['familia'])) ?></p><?php endif; ?>
        <?php if ($p['pets'] || $p['pet_foto']): ?><div class="pe-pet"><?php if ($p['pet_foto']): ?><img src="<?= e(url('/' . $p['pet_foto'])) ?>" alt="Pet de <?= e($p['nome']) ?>" width="120" height="120"><?php endif; ?><p>🐾 <?= e($p['pets'] ?? '') ?></p></div><?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</article>
