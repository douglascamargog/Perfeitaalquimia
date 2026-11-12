<?php
/**
 * Post do Instagram na gaveta lateral (novo ou existente).
 * Variáveis: $p (post), $comentarios, $etapas, $campanhas, $responsaveis, $podeEditar, $podeAprovar
 */
use App\Models\Post;
use App\Services\MarketingService;

$icones = asset('img/icones.svg');
$id = $p['id'] ? (int) $p['id'] : null;
$tipo = $p['etapa_tipo'];
$acao = $id ? url("/marketing/posts/$id") : url('/marketing/posts');
?>
<header class="ficha-topo mk-post-topo">
  <div>
    <div class="sobretitulo">INSTAGRAM · <?= $id ? mb_strtoupper(Post::FORMATOS[$p['formato']]) : 'NOVO POST' ?></div>
    <h2><?= $id ? e($p['titulo']) : 'Novo post' ?></h2>
    <?php if ($id): ?>
      <p><?= data_br($p['data_publicacao']) ?><?= $p['hora'] ? ' às ' . substr($p['hora'], 0, 5) : '' ?><?= $p['campanha'] ? ' · ' . e($p['campanha']) : '' ?></p>
      <div class="ficha-selos">
        <span class="selo-etapa" style="--cor: <?= e($p['etapa_cor']) ?>"><?= e($p['etapa']) ?></span>
        <?php if ($p['aprovado_em']): ?><span class="selo verde">Aprovado por <?= e(explode(' ', (string) $p['aprovador'])[0]) ?> em <?= data_br($p['aprovado_em']) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <button class="botao-icone escuro" type="button" data-fechar-gaveta aria-label="Fechar"><svg class="icone"><use href="<?= $icones ?>#fechar"/></svg></button>
</header>

<div class="ficha-corpo">
  <?php if ($tipo === 'ajuste' && $p['motivo_ajuste']): ?>
    <p class="aviso erro mk-ajuste"><strong>Ajuste pedido:</strong> <?= e($p['motivo_ajuste']) ?></p>
  <?php endif; ?>

  <?php if ($id): ?>
    <!-- Ações do fluxo -->
    <div class="mk-fluxo">
      <?php if ($podeEditar && in_array($tipo, ['rascunho', 'producao', 'ajuste'], true)): ?>
        <form method="post" action="<?= url("/marketing/posts/$id/enviar") ?>" data-ajax><?= csrf_campo() ?><button class="botao lima" type="submit">Enviar para aprovação</button></form>
      <?php endif; ?>
      <?php if ($podeAprovar && in_array($tipo, ['aprovacao', 'ajuste', 'producao', 'rascunho'], true)): ?>
        <form method="post" action="<?= url("/marketing/posts/$id/aprovar") ?>" data-ajax><?= csrf_campo() ?><button class="botao" type="submit"><svg class="icone"><use href="<?= $icones ?>#check"/></svg>Aprovar</button></form>
        <details class="mk-pedir-ajuste">
          <summary class="botao contorno">Pedir ajuste</summary>
          <form method="post" action="<?= url("/marketing/posts/$id/ajuste") ?>" data-ajax>
            <?= csrf_campo() ?>
            <textarea name="motivo" rows="2" required placeholder="O que precisa mudar? Ex.: trocar a foto, encurtar a legenda…" aria-label="Motivo do ajuste"></textarea>
            <button class="botao pequeno" type="submit">Enviar pedido de ajuste</button>
          </form>
        </details>
      <?php endif; ?>
      <?php if ($podeEditar && $tipo !== 'publicado'): ?>
        <details class="mk-publicar">
          <summary class="botao contorno">Marcar como publicado</summary>
          <form method="post" action="<?= url("/marketing/posts/$id/publicar") ?>" data-ajax>
            <?= csrf_campo() ?>
            <input type="url" name="link" required placeholder="https://www.instagram.com/p/…" aria-label="Link do post no Instagram">
            <button class="botao pequeno" type="submit">Confirmar publicação</button>
          </form>
        </details>
      <?php endif; ?>
      <?php if ($p['link_publicado']): ?><a class="botao contorno" href="<?= e($p['link_publicado']) ?>" target="_blank" rel="noopener noreferrer"><svg class="icone"><use href="<?= $icones ?>#externo"/></svg>Ver no Instagram</a><?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Prévia -->
  <?php if ($p['imagem'] || $p['legenda']): ?>
    <section class="mk-previa">
      <?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="Imagem do post"><?php endif; ?>
      <div class="ficha-acoes">
        <?php if ($p['legenda']): ?><button class="botao contorno pequeno" type="button" data-copiar-texto="<?= e(trim($p['legenda'] . "\n\n" . $p['hashtags'])) ?>">Copiar legenda</button><?php endif; ?>
        <?php if ($p['imagem']): ?><a class="botao contorno pequeno" href="<?= url('/' . $p['imagem']) ?>" download><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>Baixar imagem</a><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Formulário -->
  <?php if ($podeEditar): ?>
    <form class="ficha-secao" method="post" action="<?= $acao ?>" enctype="multipart/form-data" data-ajax>
      <?= csrf_campo() ?>
      <h3><?= $id ? 'Editar post' : 'Dados do post' ?></h3>
      <div class="campo"><label for="pt-titulo">Título (só para a equipe)</label><input type="text" id="pt-titulo" name="titulo" maxlength="160" required value="<?= e($p['titulo']) ?>" placeholder="Ex.: Reels Magnésio — benefícios"></div>
      <div class="grade-campos">
        <div class="campo"><label for="pt-formato">Formato</label>
          <select id="pt-formato" name="formato"><?php foreach (Post::FORMATOS as $k => $n): ?><option value="<?= $k ?>" <?= $p['formato'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
        <div class="campo"><label for="pt-data">Data</label><input type="date" id="pt-data" name="data_publicacao" required value="<?= e($p['data_publicacao']) ?>"></div>
        <div class="campo"><label for="pt-hora">Hora</label><input type="time" id="pt-hora" name="hora" value="<?= e($p['hora'] ? substr($p['hora'], 0, 5) : '') ?>"></div>
      </div>
      <div class="grade-campos">
        <div class="campo"><label for="pt-campanha">Campanha</label>
          <select id="pt-campanha" name="campanha_id"><option value="">Sem campanha</option><?php foreach ($campanhas as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['campanha_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="campo"><label for="pt-resp">Responsável</label>
          <select id="pt-resp" name="responsavel_id"><?php foreach ($responsaveis as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $p['responsavel_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="campo"><label for="pt-imagem">Imagem <?= $p['imagem'] ? '(enviar outra substitui)' : '' ?></label>
        <input type="file" id="pt-imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="ajuda">JPG, PNG ou WEBP até 8 MB. Feed: 1080 × 1350 px · Stories/Reels: 1080 × 1920 px (capa).</div></div>
      <div class="campo"><label for="pt-legenda">Legenda <small class="suave" data-contador="pt-legenda" data-limite="<?= MarketingService::LIMITE_LEGENDA ?>"></small></label>
        <textarea id="pt-legenda" name="legenda" rows="6" maxlength="<?= MarketingService::LIMITE_LEGENDA ?>"><?= e($p['legenda']) ?></textarea></div>
      <div class="campo"><label for="pt-hashtags">Hashtags <small class="suave">(máx. <?= MarketingService::LIMITE_HASHTAGS ?>)</small></label>
        <input type="text" id="pt-hashtags" name="hashtags" maxlength="1000" value="<?= e($p['hashtags']) ?>" placeholder="#suplementos #saudenatural"></div>
      <button class="botao" type="submit"><?= $id ? 'Salvar alterações' : 'Criar post' ?></button>
      <?php if ($id && $p['aprovado_em']): ?><p class="pequeno suave">Mudar imagem ou legenda tira a aprovação: o post volta para "Aguardando aprovação".</p><?php endif; ?>
    </form>
  <?php elseif ($p['legenda']): ?>
    <section class="ficha-secao"><h3>Legenda</h3><p class="mk-legenda-texto"><?= nl2br(e($p['legenda'])) ?></p><p class="pequeno suave"><?= e($p['hashtags']) ?></p></section>
  <?php endif; ?>

  <?php if ($id): ?>
    <?php if ($podeEditar || $podeAprovar): ?>
      <form class="ficha-secao" method="post" action="<?= url("/marketing/posts/$id/etapa") ?>" data-ajax>
        <?= csrf_campo() ?>
        <h3>Etapa</h3>
        <div class="linha-campo">
          <select name="etapa_id" aria-label="Etapa"><?php foreach ($etapas as $e): ?><option value="<?= (int) $e['id'] ?>" <?= (int) $e['id'] === (int) $p['etapa_id'] ? 'selected' : '' ?>><?= e($e['nome']) ?></option><?php endforeach; ?></select>
          <button class="botao contorno pequeno" type="submit">Mudar</button>
        </div>
      </form>
    <?php endif; ?>

    <!-- Comentários e histórico -->
    <section class="ficha-secao">
      <h3>Comentários e histórico</h3>
      <form method="post" action="<?= url("/marketing/posts/$id/comentar") ?>" data-ajax class="mk-comentar">
        <?= csrf_campo() ?>
        <input type="text" name="texto" maxlength="2000" required placeholder="Escreva um comentário…" aria-label="Comentário">
        <button class="botao pequeno" type="submit">Enviar</button>
      </form>
      <ol class="linha-tempo">
        <?php foreach ($comentarios as $c): ?>
          <li class="tipo-<?= $c['tipo'] === 'ajuste' ? 'cobranca' : ($c['tipo'] === 'aprovacao' ? 'pedido' : 'nota') ?>">
            <strong><?= ['comentario' => 'Comentário', 'ajuste' => 'Ajuste pedido', 'aprovacao' => 'Aprovação', 'etapa' => 'Histórico'][$c['tipo']] ?? 'Histórico' ?></strong>
            <span><?= e($c['texto']) ?></span>
            <small><?= data_br($c['criado_em'], true) ?> · <?= e($c['usuario_nome'] ?? 'Sistema') ?></small>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>
</div>
