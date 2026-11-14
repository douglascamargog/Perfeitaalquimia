<?php
/**
 * Pedir ao Marketing (qualquer pessoa): formulário + meus pedidos e a situação de cada um.
 * Variáveis: $tipos, $pedidos
 */
use App\Services\MarketingTarefaService as T;

$hoje = date('Y-m-d');
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">MARKETING · PEDIDOS</div>
    <h1>Pedir ao Marketing</h1>
    <p>Precisa de uma arte, um material, um cadastro na loja ou um envio FULL? Peça aqui: o pedido vai direto para quem cuida daquele assunto e você é avisado quando ficar pronto.</p>
  </div>
</div>

<div class="mt-pedir">
  <form method="post" action="<?= url('/marketing/tarefas') ?>" enctype="multipart/form-data" class="card pd-form" data-pedir>
    <?= csrf_campo() ?>
    <input type="hidden" name="pedido" value="1">
    <h2>Novo pedido</h2>
    <label>O que você precisa?<select name="tipo_id" required><option value="">— escolha o assunto</option>
      <?php foreach ($tipos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= antigo('tipo_id') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?> · <?= e((string) $t['funcao']) ?></option><?php endforeach; ?></select></label>
    <label>Título <small>em poucas palavras</small><input type="text" name="titulo" maxlength="160" required value="<?= antigo('titulo') ?>" placeholder="Ex.: Arte da promoção de Magnésio para o WhatsApp"></label>
    <label>Detalhes<textarea name="descricao" rows="4" placeholder="Tamanho, texto, para quem é, referências…"><?= antigo('descricao') ?></textarea></label>
    <div class="pd-duas">
      <label>Para quando?<input type="date" name="prazo" min="<?= $hoje ?>" value="<?= antigo('prazo') ?>"></label>
      <label class="caixa-marcar mt-urgente"><input type="checkbox" name="urgente" value="1"> É urgente</label>
    </div>
    <label>Anexo <small>PDF ou imagem de referência (opcional)</small><input type="file" name="anexo" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
    <button class="botao lima" type="submit">Enviar pedido</button>
  </form>

  <section class="card sem-espaco">
    <h2 class="pr-titulo-tabela">Meus pedidos</h2>
    <div class="tabela-rolagem">
      <table class="tabela" data-meus-pedidos>
        <thead><tr><th>Pedido</th><th>Situação</th><th>Prazo</th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): [$sn, $sc] = T::SITUACOES[$p['situacao']]; ?>
          <tr data-pedido="<?= (int) $p['id'] ?>">
            <td><a href="<?= url('/marketing/tarefas/' . (int) $p['id']) ?>"><strong><?= e($p['titulo']) ?></strong></a><small class="suave" style="display:block"><?= e((string) $p['tipo_nome']) ?> · <?= e((string) $p['funcao']) ?> · <?= data_br($p['criado_em']) ?></small></td>
            <td><span class="selo <?= $sc ?>"><?= e($sn) ?></span></td>
            <td><?= $p['prazo'] ? data_br($p['prazo']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?><tr><td colspan="3" class="suave">Você ainda não pediu nada ao Marketing.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
