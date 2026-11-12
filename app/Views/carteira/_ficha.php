<?php
/**
 * Ficha do cliente (carregada dentro da gaveta lateral).
 * Variáveis: $c (cliente), $eventos, $retorno, $etapas, $prioridades, $vendedores, $canais
 */
use App\Models\ClienteEvento;
use App\Services\Normalizar;

$icones = asset('img/icones.svg');
$id = (int) $c['id'];
$tel = $c['telefone'] ? Normalizar::telefoneFormatado($c['telefone']) : '';
$whats = $c['whatsapp'] ?: (strlen((string) $c['telefone']) === 11 ? $c['telefone'] : '');
$enderecoMapa = trim(implode(' ', array_filter([$c['endereco'], $c['cidade'], $c['uf']])));

/** Link externo seguro (só http/https) */
$linkSeguro = function (?string $url): string {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '@')) {
        return 'https://www.instagram.com/' . rawurlencode(substr($url, 1)) . '/';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
};
?>
<header class="ficha-topo">
  <div>
    <div class="sobretitulo"><?= e($c['segmento'] ?? 'Sem segmento') ?> · <?= e($c['tipo'] === 'cliente' ? 'Cliente' : 'Lead') ?></div>
    <h2><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></h2>
    <p><?= e($c['cidade']) ?> / <?= e($c['uf']) ?><?= $c['cnpj'] ? ' · CNPJ ' . e(cnpj_formatado($c['cnpj'])) : '' ?></p>
    <div class="ficha-selos">
      <span class="selo-etapa" style="--cor: <?= e($c['etapa_cor']) ?>"><?= e($c['etapa']) ?></span>
      <span class="selo prioridade-<?= e(Normalizar::semAcento($c['prioridade'])) ?>"><?= e($c['prioridade']) ?></span>
      <?php if ($c['credito_situacao'] === 'atraso'): ?><span class="selo vermelho">Título em atraso</span><?php endif; ?>
    </div>
  </div>
  <button class="botao-icone escuro" type="button" data-fechar-gaveta aria-label="Fechar ficha"><svg class="icone"><use href="<?= $icones ?>#fechar"/></svg></button>
</header>

<div class="ficha-corpo">
  <!-- Ações rápidas -->
  <div class="ficha-acoes">
    <?php if ($tel): ?><a class="botao contorno pequeno" href="tel:+55<?= e($c['telefone']) ?>"><svg class="icone"><use href="<?= $icones ?>#telefone"/></svg>Ligar</a><?php endif; ?>
    <?php if ($whats): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($whats) ?>" target="_blank" rel="noopener noreferrer"><svg class="icone"><use href="<?= $icones ?>#conversa"/></svg>WhatsApp</a><?php endif; ?>
    <?php if ($enderecoMapa): ?><a class="botao contorno pequeno" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($enderecoMapa) ?>" target="_blank" rel="noopener noreferrer"><svg class="icone"><use href="<?= $icones ?>#externo"/></svg>Rota</a><?php endif; ?>
    <a class="botao contorno pequeno" href="<?= url("/carteira/clientes/$id/editar") ?>"><svg class="icone"><use href="<?= $icones ?>#editar"/></svg>Editar cadastro</a>
  </div>

  <!-- Dados -->
  <section class="ficha-secao">
    <h3>Dados do estabelecimento</h3>
    <dl class="ficha-dados">
      <?php
      $dados = [
          'Razão social' => $c['razao_social'], 'Endereço' => trim(($c['endereco'] ?? '') . ($c['bairro'] ? ', ' . $c['bairro'] : '')),
          'Telefone' => $tel, 'WhatsApp' => $c['whatsapp'] ? Normalizar::telefoneFormatado($c['whatsapp']) : '', 'E-mail' => $c['email'],
          'Responsável' => $c['responsavel'] . ($c['funcao'] ? ' · ' . $c['funcao'] : ''), 'Tabela de preço' => $c['tabela_preco'],
          'Região' => $c['regiao'], 'Origem' => trim(($c['origem'] ?? '') . ($c['origem_detalhe'] ? ' — ' . $c['origem_detalhe'] : ''), ' —'),
          'Último contato' => $c['ultimo_contato_em'] ? data_br($c['ultimo_contato_em']) : '', 'Última compra' => $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : 'Ainda não comprou',
      ];
      foreach ($dados as $rotulo => $valor): if ((string) $valor === '') continue; ?>
        <div><dt><?= e($rotulo) ?></dt><dd><?= e($valor) ?></dd></div>
      <?php endforeach; ?>
      <?php foreach (['Instagram' => $c['instagram'], 'Facebook' => $c['facebook'], 'Site' => $c['site'], 'Fonte do cadastro' => $c['fonte_url']] as $rotulo => $valor):
          $url = $linkSeguro($valor); if (!$url) continue; ?>
        <div><dt><?= e($rotulo) ?></dt><dd><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e(mb_strimwidth((string) $valor, 0, 48, '…')) ?></a></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php if ($c['mix_produtos']): ?><p class="ficha-nota"><strong>Mix:</strong> <?= e($c['mix_produtos']) ?></p><?php endif; ?>
    <?php if ($c['validacao']): ?><p class="ficha-nota alerta"><strong>A validar:</strong> <?= e($c['validacao']) ?></p><?php endif; ?>
  </section>

  <!-- Gestão comercial -->
  <form class="ficha-secao" method="post" action="<?= url("/carteira/clientes/$id/ficha") ?>" data-ajax>
    <?= csrf_campo() ?>
    <input type="hidden" name="atualizado_em" value="<?= e($c['atualizado_em']) ?>">
    <h3>Gestão comercial</h3>
    <div class="grade-campos">
      <div class="campo">
        <label for="f-etapa">Etapa</label>
        <select id="f-etapa" name="etapa_id">
          <?php foreach ($etapas as $et): ?><option value="<?= (int) $et['id'] ?>" <?= (int) $et['id'] === (int) $c['etapa_id'] ? 'selected' : '' ?>><?= e($et['nome']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-prioridade">Prioridade</label>
        <select id="f-prioridade" name="prioridade">
          <?php foreach ($prioridades as $p): ?><option <?= $p === $c['prioridade'] ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php if ($vendedores): ?>
        <div class="campo">
          <label for="f-resp">Responsável</label>
          <select id="f-resp" name="responsavel_id">
            <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === (int) $c['responsavel_id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="campo">
        <label for="f-proximo">Próximo contato</label>
        <input type="date" id="f-proximo" name="proximo_contato" value="<?= e($retorno['vence_em'] ?? '') ?>">
      </div>
    </div>
    <div class="campo">
      <label for="f-obs">Observações</label>
      <textarea id="f-obs" name="observacoes" rows="3"><?= e($c['observacoes']) ?></textarea>
    </div>
    <button class="botao" type="submit">Salvar alterações</button>
  </form>

  <!-- Registrar contato -->
  <form class="ficha-secao" method="post" action="<?= url("/carteira/clientes/$id/contato") ?>" data-ajax>
    <?= csrf_campo() ?>
    <h3>Registrar contato</h3>
    <div class="grade-campos">
      <div class="campo"><label for="c-data">Data</label><input type="date" id="c-data" name="data" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
      <div class="campo">
        <label for="c-canal">Canal</label>
        <select id="c-canal" name="canal"><?php foreach ($canais as $canal): ?><option><?= e($canal) ?></option><?php endforeach; ?></select>
      </div>
      <div class="campo"><label for="c-proximo">Próximo contato (opcional)</label><input type="date" id="c-proximo" name="proximo_contato" min="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="campo"><label for="c-resumo">Resumo da conversa</label><textarea id="c-resumo" name="resumo" rows="3" placeholder="O que foi conversado, objeções, próximos passos…"></textarea></div>
    <button class="botao lima" type="submit">Registrar contato</button>
  </form>

  <!-- Ligação com 1 clique (plano B do Ligue Talk) -->
  <section class="ficha-secao">
    <h3>Registrar ligação <span class="selo cinza">1 clique</span></h3>
    <div class="ficha-acoes">
      <?php foreach (['Atendeu', 'Não atendeu', 'Caixa postal', 'Número errado'] as $resultado): ?>
        <form method="post" action="<?= url("/carteira/clientes/$id/ligacao") ?>" data-ajax>
          <?= csrf_campo() ?><input type="hidden" name="resultado" value="<?= e($resultado) ?>">
          <button class="botao contorno pequeno" type="submit"><?= e($resultado) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Pedidos (etapa 4) -->
  <section class="ficha-secao">
    <h3>Pedidos</h3>
    <p class="suave pequeno">Pedidos e faturamento chegam do Omie no módulo de Pedidos (próxima etapa do protótipo).</p>
  </section>

  <!-- Linha do tempo -->
  <section class="ficha-secao">
    <h3>Linha do tempo</h3>
    <?php if (!$eventos): ?><p class="suave pequeno">Nenhum registro ainda.</p><?php endif; ?>
    <ol class="linha-tempo">
      <?php foreach ($eventos as $ev): ?>
        <li class="tipo-<?= e($ev['tipo']) ?>">
          <strong><?= e(ClienteEvento::TIPOS[$ev['tipo']] ?? $ev['tipo']) ?><?= $ev['canal'] && $ev['tipo'] !== 'ligacao' ? ' · ' . e($ev['canal']) : '' ?></strong>
          <?php if ($ev['descricao']): ?><span><?= e($ev['descricao']) ?></span><?php endif; ?>
          <small><?= data_br($ev['ocorrido_em'], true) ?> · <?= e($ev['usuario_nome'] ?? 'Sistema') ?></small>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>
