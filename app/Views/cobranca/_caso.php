<?php
/**
 * Caso de cobrança (carregado dentro da gaveta lateral).
 * Variáveis: $c (caso + cliente), $titulos, $eventos, $etapas, $responsaveis, $mensagens, $resultados, $podeGerenciar
 */
use App\Core\Visao;
use App\Models\ClienteEvento;
use App\Services\Normalizar;

$icones = asset('img/icones.svg');
$id = (int) $c['id'];
$tel = $c['telefone'] ? Normalizar::telefoneFormatado($c['telefone']) : '';
$whats = $c['whatsapp'] ?: (strlen((string) $c['telefone']) === 11 ? $c['telefone'] : '');
$dias = (int) $c['dias_atraso'];
$aberto = $c['situacao'] === 'aberta';
?>
<header class="ficha-topo cob-topo">
  <div>
    <div class="sobretitulo">CASO DE COBRANÇA · <?= $c['origem'] === 'erp' ? 'VINDO DO ERP' : 'ENVIADO MANUALMENTE' ?></div>
    <h2><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></h2>
    <p><?= e($c['cidade']) ?> / <?= e($c['uf']) ?><?= $c['cnpj'] ? ' · CNPJ ' . e(cnpj_formatado($c['cnpj'])) : '' ?></p>
    <div class="ficha-selos">
      <span class="selo-etapa" style="--cor: <?= e($c['etapa_cor']) ?>"><?= e($c['etapa']) ?></span>
      <?php if ($aberto && $c['vencimento_mais_antigo']): ?><span class="selo <?= $dias > 90 ? 'vermelho' : ($dias > 30 ? 'laranja' : 'cinza') ?>"><?= $dias ?> dias de atraso</span><?php endif; ?>
      <?php if (!$aberto): ?><span class="selo cinza">Encerrado em <?= data_br($c['encerrada_em']) ?></span><?php endif; ?>
    </div>
  </div>
  <button class="botao-icone escuro" type="button" data-fechar-gaveta aria-label="Fechar"><svg class="icone"><use href="<?= $icones ?>#fechar"/></svg></button>
</header>

<div class="ficha-corpo">
  <div class="cob-valor-grande">
    <div><span>Em aberto</span><strong><?= dinheiro((float) $c['valor_total']) ?></strong></div>
    <?php if ($c['promessa_em']): ?><div><span>Promessa</span><strong><?= dinheiro((float) $c['promessa_valor']) ?></strong><small><?= data_br($c['promessa_em']) ?></small></div><?php endif; ?>
    <div><span>Tentativas sem sucesso</span><strong><?= (int) $c['tentativas_sem_sucesso'] ?></strong></div>
  </div>

  <!-- Contato -->
  <section class="ficha-secao">
    <h3>Contato</h3>
    <div class="ficha-acoes">
      <?php if ($tel): ?><a class="botao contorno pequeno" href="tel:+55<?= e($c['telefone']) ?>"><svg class="icone"><use href="<?= $icones ?>#telefone"/></svg><?= e($tel) ?></a><?php endif; ?>
      <?php if ($whats): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($whats) ?>" target="_blank" rel="noopener noreferrer"><svg class="icone"><use href="<?= $icones ?>#conversa"/></svg>WhatsApp</a><?php endif; ?>
      <?php if ($c['email']): ?><a class="botao contorno pequeno" href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif; ?>
      <?php if (!$tel && !$whats && !$c['email']): ?><span class="selo vermelho">Nenhum contato no cadastro</span><?php endif; ?>
    </div>
    <p class="pequeno suave">Vendedor da carteira: <?= e($c['vendedor'] ?? '—') ?></p>
    <?= Visao::renderizar('carteira/_busca_contatos', ['clienteId' => (int) $c['cliente_id'], 'destaque' => $c['etapa_tipo'] === 'sem_contato' || (!$tel && !$whats)]) ?>
  </section>

  <!-- Títulos -->
  <section class="ficha-secao">
    <h3>Títulos</h3>
    <table class="tabela cob-titulos">
      <thead><tr><th>Documento</th><th>Vencimento</th><th class="direita">Valor</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($titulos as $t): ?>
        <tr class="<?= $t['pago_em'] ? 'pago' : '' ?>">
          <td><?= e(trim(($t['documento'] ?? '—') . ' ' . ($t['parcela'] ?? ''))) ?></td>
          <td><?= data_br($t['vencimento']) ?><?= !$t['pago_em'] && $t['dias'] > 0 ? ' <small class="suave">(' . (int) $t['dias'] . 'd)</small>' : '' ?></td>
          <td class="direita"><?= dinheiro((float) $t['valor']) ?></td>
          <td class="direita">
            <?php if ($t['pago_em']): ?><span class="selo verde">Pago <?= data_br($t['pago_em']) ?></span>
            <?php elseif ($podeGerenciar): ?>
              <form method="post" action="<?= url('/cobranca/titulos/' . (int) $t['id'] . '/pago') ?>" data-ajax data-confirmar="Marcar este título como pago?">
                <?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Marcar pago</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <?php if ($podeGerenciar && $aberto): ?>
    <!-- Tentativa de contato -->
    <form class="ficha-secao" method="post" action="<?= url("/cobranca/$id/tentativa") ?>" data-ajax>
      <?= csrf_campo() ?>
      <h3>Registrar tentativa</h3>
      <div class="grade-campos">
        <div class="campo"><label for="t-canal">Canal</label>
          <select id="t-canal" name="canal"><?php foreach (['Ligação', 'WhatsApp', 'E-mail', 'Visita presencial'] as $canal): ?><option><?= $canal ?></option><?php endforeach; ?></select>
        </div>
        <div class="campo"><label for="t-resultado">Resultado</label>
          <select id="t-resultado" name="resultado"><?php foreach ($resultados as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="campo"><label for="t-nota">Anotação (opcional)</label><input type="text" id="t-nota" name="nota" maxlength="500" placeholder="Ex.: disse que paga sexta, pediu 2ª via…"></div>
      <button class="botao lima" type="submit">Registrar tentativa</button>
    </form>

    <!-- Promessa -->
    <form class="ficha-secao" method="post" action="<?= url("/cobranca/$id/promessa") ?>" data-ajax>
      <?= csrf_campo() ?>
      <h3>Promessa de pagamento</h3>
      <div class="grade-campos">
        <div class="campo"><label for="p-data">Vai pagar em</label><input type="date" id="p-data" name="data" min="<?= date('Y-m-d') ?>" required></div>
        <div class="campo"><label for="p-valor">Valor (R$)</label><input type="text" id="p-valor" name="valor" inputmode="decimal" value="<?= number_format((float) $c['valor_total'], 2, ',', '.') ?>"></div>
      </div>
      <button class="botao contorno" type="submit">Registrar promessa</button>
    </form>
  <?php endif; ?>

  <?php if ($mensagens): ?>
    <!-- Mensagens prontas -->
    <section class="ficha-secao">
      <h3>Mensagens prontas</h3>
      <?php foreach ($mensagens as $titulo => $texto): ?>
        <div class="cob-mensagem">
          <strong><?= e($titulo) ?></strong>
          <p><?= e($texto) ?></p>
          <div class="ficha-acoes">
            <?php if ($whats): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($whats) ?>?text=<?= rawurlencode($texto) ?>" target="_blank" rel="noopener noreferrer">Abrir no WhatsApp</a><?php endif; ?>
            <button class="botao contorno pequeno" type="button" data-copiar-texto="<?= e($texto) ?>">Copiar</button>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if ($podeGerenciar): ?>
    <!-- Gestão do caso -->
    <section class="ficha-secao">
      <h3>Gestão do caso</h3>
      <div class="grade-campos">
        <form method="post" action="<?= url("/cobranca/$id/etapa") ?>" data-ajax class="campo">
          <?= csrf_campo() ?>
          <label for="g-etapa">Etapa</label>
          <div class="linha-campo">
            <select id="g-etapa" name="etapa_id"><?php foreach ($etapas as $et): ?><option value="<?= (int) $et['id'] ?>" <?= (int) $et['id'] === (int) $c['etapa_id'] ? 'selected' : '' ?>><?= e($et['nome']) ?></option><?php endforeach; ?></select>
            <button class="botao contorno pequeno" type="submit">Mudar</button>
          </div>
        </form>
        <form method="post" action="<?= url("/cobranca/$id/responsavel") ?>" data-ajax class="campo">
          <?= csrf_campo() ?>
          <label for="g-resp">Responsável</label>
          <div class="linha-campo">
            <select id="g-resp" name="responsavel_id"><?php foreach ($responsaveis as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === (int) $c['responsavel_id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option><?php endforeach; ?></select>
            <button class="botao contorno pequeno" type="submit">Passar</button>
          </div>
        </form>
      </div>
      <form method="post" action="<?= url("/cobranca/$id/observacoes") ?>" data-ajax>
        <?= csrf_campo() ?>
        <div class="campo"><label for="g-obs">Observações do caso</label><textarea id="g-obs" name="observacoes" rows="3"><?= e($c['observacoes']) ?></textarea></div>
        <button class="botao contorno pequeno" type="submit">Salvar observações</button>
      </form>
    </section>
  <?php elseif ($c['observacoes']): ?>
    <p class="ficha-nota"><strong>Observações:</strong> <?= e($c['observacoes']) ?></p>
  <?php endif; ?>

  <!-- Linha do tempo do cliente (inclui a cobrança) -->
  <section class="ficha-secao">
    <h3>Linha do tempo</h3>
    <ol class="linha-tempo">
      <?php foreach ($eventos as $ev): ?>
        <li class="tipo-<?= e($ev['tipo']) ?>">
          <strong><?= e(ClienteEvento::TIPOS[$ev['tipo']] ?? $ev['tipo']) ?><?= $ev['canal'] ? ' · ' . e($ev['canal']) : '' ?></strong>
          <?php if ($ev['descricao']): ?><span><?= e($ev['descricao']) ?></span><?php endif; ?>
          <small><?= data_br($ev['ocorrido_em'], true) ?> · <?= e($ev['usuario_nome'] ?? 'Sistema') ?></small>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>
