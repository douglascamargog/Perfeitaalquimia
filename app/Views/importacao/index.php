<?php /** Importação: envio do arquivo e histórico. Variáveis: $importacoes */
$status = ['previa' => ['Aguardando confirmação', 'lima'], 'concluida' => ['Concluída', ''], 'cancelada' => ['Cancelada', 'cinza']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA</div><h1>Importar carteira</h1>
    <p>Planilhas por vendedor, a carteira da Black Friday ou o Backup JSON da Carteira V4. Nada entra antes de você conferir.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/carteira/lista') ?>">Voltar para a carteira</a></div>
</div>

<div class="grade-importacao">
  <form class="card" method="post" action="<?= url('/carteira/importar') ?>" enctype="multipart/form-data">
    <?= csrf_campo() ?>
    <h2>1. Enviar arquivo</h2>
    <div class="campo">
      <label for="arquivo">Arquivo (CSV, XLSX ou JSON — até 10 MB)</label>
      <input type="file" id="arquivo" name="arquivo" accept=".csv,.xlsx,.json" required>
    </div>
    <button class="botao" type="submit">Ler e pré-visualizar</button>
    <p class="suave pequeno" style="margin-top:12px">O arquivo é lido e apagado do servidor na hora; só ficam os dados que você confirmar.</p>
  </form>

  <div class="card">
    <h2>Como preparar a planilha</h2>
    <p class="pequeno">A primeira linha deve ter os nomes das colunas. Reconheço automaticamente:</p>
    <ul class="pequeno lista-colunas">
      <li><strong>Nome</strong> (ou razão social, estabelecimento) e <strong>Cidade</strong> e <strong>UF</strong> — obrigatórios</li>
      <li>CNPJ, nome fantasia, endereço, bairro, CEP</li>
      <li>Segmento (qualquer escrita: vira Produtos naturais, Suplementos, Farmácia ou Outros)</li>
      <li>Telefone, WhatsApp, e-mail, Instagram, Facebook, site</li>
      <li>Etapa, prioridade, observações</li>
      <li><strong>Vendedor</strong>: e-mail ou código do vendedor (ex.: TV01). Sem vendedor, o cliente vai para a função de novos leads.</li>
    </ul>
    <p class="pequeno suave">Duplicados: pelo CNPJ; sem CNPJ, pelo nome + cidade. CNPJ já cadastrado só completa os campos vazios.</p>
  </div>
</div>

<section class="card">
  <h2>Últimas importações</h2>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Arquivo</th><th>Quem</th><th>Linhas</th><th>Novos</th><th>Atualizar</th><th>Duplicados</th><th>Erros</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($importacoes as $i): [$rotulo, $cor] = $status[$i['status']]; ?>
        <tr>
          <td><?= data_br($i['criado_em'], true) ?></td>
          <td><a href="<?= url('/carteira/importar/' . (int) $i['id']) ?>"><?= e($i['arquivo']) ?></a></td>
          <td class="pequeno"><?= e($i['usuario_nome']) ?></td>
          <td><?= (int) $i['total'] ?></td><td><?= (int) $i['novos'] ?></td><td><?= (int) $i['atualizados'] ?></td>
          <td><?= (int) $i['duplicados'] ?></td><td><?= (int) $i['erros'] ?></td>
          <td><span class="selo <?= $cor ?>"><?= $rotulo ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$importacoes): ?><tr><td colspan="9" class="vazio">Nenhuma importação ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
