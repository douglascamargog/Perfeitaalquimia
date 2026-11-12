<?php
/**
 * Ficha do cliente (carregada dentro da gaveta lateral).
 * Variáveis: $c (cliente), $eventos, $retorno, $etapas, $prioridades, $vendedores, $canais, $cobranca (caso aberto ou null),
 *            $pedidos (últimos), $acesso (catálogo), $codigoCatalogo (código recém-gerado, aparece uma vez)
 */
use App\Core\Auth;
use App\Core\Visao;
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
      <?php if ($cobranca): ?>
        <span class="selo vermelho">Em cobrança<?= Auth::pode('cobranca.ver') ? ' · ' . e(dinheiro((float) $cobranca['valor_total'])) . ' · ' . (int) $cobranca['dias_atraso'] . ' dias' : '' ?></span>
      <?php elseif ($c['credito_situacao'] === 'atraso'): ?><span class="selo vermelho">Título em atraso</span><?php endif; ?>
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
    <?= Visao::renderizar('carteira/_busca_contatos', ['clienteId' => $id, 'destaque' => !$c['telefone'] && !$c['whatsapp']]) ?>
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

  <?php if (Auth::pode('cobranca.enviar')): ?>
    <!-- Cobrança -->
    <?php if ($cobranca): ?>
      <section class="ficha-secao">
        <h3>Cobrança</h3>
        <p class="pequeno">Este cliente está na Cobrança (etapa <strong><?= e($cobranca['etapa']) ?></strong>). Combine com o Financeiro antes de oferecer boleto.</p>
        <?php if (Auth::pode('cobranca.ver')): ?><a class="botao contorno pequeno" href="<?= url('/cobranca/lista') ?>?q=<?= rawurlencode((string) ($c['cnpj'] ?: $c['razao_social'])) ?>">Abrir na Cobrança</a><?php endif; ?>
      </section>
    <?php else: ?>
      <details class="ficha-secao">
        <summary><h3>Enviar para cobrança</h3></summary>
        <form method="post" action="<?= url("/carteira/clientes/$id/cobranca") ?>" data-ajax data-confirmar="Enviar este cliente para a Cobrança? O Financeiro passa a cuidar do título.">
          <?= csrf_campo() ?>
          <p class="pequeno suave">Use quando o cliente tem um título vencido e você não consegue resolver. O caso vai para a função Cobrança.</p>
          <div class="grade-campos">
            <div class="campo"><label for="cb-valor">Valor em aberto (R$)</label><input type="text" id="cb-valor" name="valor" inputmode="decimal" required placeholder="0,00"></div>
            <div class="campo"><label for="cb-venc">Venceu em</label><input type="date" id="cb-venc" name="vencimento" max="<?= date('Y-m-d') ?>" required></div>
            <div class="campo"><label for="cb-doc">Nota / boleto (opcional)</label><input type="text" id="cb-doc" name="documento" maxlength="40"></div>
          </div>
          <div class="campo"><label for="cb-obs">O que já foi tentado</label><input type="text" id="cb-obs" name="observacao" maxlength="300" placeholder="Ex.: não atende há 2 semanas, WhatsApp não responde"></div>
          <button class="botao contorno" type="submit">Enviar para cobrança</button>
        </form>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Pedidos -->
  <section class="ficha-secao">
    <h3>Pedidos</h3>
    <div class="ficha-acoes">
      <?php if (Auth::pode('pedidos.criar')): ?><a class="botao lima pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= $id ?>">Novo pedido</a><?php endif; ?>
      <?php if (Auth::pode('pedidos.criar') && $pedidos): ?><a class="botao contorno pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= $id ?>&repetir=1">Repetir último</a><?php endif; ?>
    </div>
    <?php if (!$pedidos): ?><p class="suave pequeno">Nenhum pedido ainda.</p><?php endif; ?>
    <ul class="ficha-pedidos">
      <?php foreach ($pedidos as $pd): [$rot, $cor] = \App\Services\PedidoService::SITUACOES[$pd['situacao']] ?? [$pd['situacao'], 'cinza']; ?>
        <li><a href="<?= url('/pedidos/' . (int) $pd['id']) ?>">#<?= (int) $pd['id'] ?></a> <span class="pequeno suave"><?= data_br($pd['criado_em']) ?></span>
          <b><?= e(dinheiro((float) $pd['total'])) ?></b> <span class="selo <?= $cor ?>"><?= $rot ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php if (Auth::pode('catalogo.acessos')): ?>
      <div class="ficha-catalogo">
        <p class="pequeno">Catálogo: <?= !empty($acesso['tem_senha']) ? 'cliente já tem senha' . (!empty($acesso['ultimo_acesso_em']) ? ' · último acesso ' . data_br($acesso['ultimo_acesso_em']) : '') : 'cliente ainda não acessou' ?>.</p>
        <?php if ($codigoCatalogo):
            $msg = 'Olá! Seu código para acessar o catálogo da ' . \App\Core\Config::get('empresa.nome', 'Perfeita Alquimia') . " é $codigoCatalogo (vale " . (int) \App\Core\Config::get('catalogo.codigo_validade_minutos', 10) . ' min). Entre em ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . url('/catalogo/entrar') . '?modo=codigo, informe o CNPJ e crie sua senha.'; ?>
          <p class="ficha-codigo">Código: <strong data-codigo-catalogo><?= e($codigoCatalogo) ?></strong></p>
          <?php if ($whats): ?><a class="botao lima pequeno" href="https://wa.me/55<?= e($whats) ?>?text=<?= rawurlencode($msg) ?>" target="_blank" rel="noopener noreferrer">Enviar pelo WhatsApp</a><?php endif; ?>
        <?php else: ?>
          <form method="post" action="<?= url("/carteira/clientes/$id/acesso-catalogo") ?>" data-ajax><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Gerar acesso ao catálogo</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
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
