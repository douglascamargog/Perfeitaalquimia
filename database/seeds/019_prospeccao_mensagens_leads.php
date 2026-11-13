<?php
/**
 * Prospecção, mensagens prontas e captura de leads — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * A lista de CNAEs é um ponto de partida: a lista definitiva dos clientes atuais ainda está pendente
 * (edite em Configurações › Prospecção).
 */
use App\Core\Banco;

foreach ([['mensagens.usar', 'gestor'], ['mensagens.usar', 'vendedor'], ['mensagens.usar', 'financeiro'],
          ['prospeccao.usar', 'gestor'], ['prospeccao.usar', 'vendedor'], ['leads.ver', 'gestor'], ['leads.ver', 'marketing'],
          ['mensagens.gerenciar', 'gestor'], ['mensagens.gerenciar', 'marketing']] as [$permissao, $perfil]) {
    Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$permissao, $perfil]);
}

// A antiga "campanha em destaque" virou a Prospecção: a origem das lojas da Carteira V4 ganha um nome neutro.
// Só o nome muda (os clientes continuam ligados à mesma origem pelo id); nada é apagado.
Banco::executar("UPDATE origens SET nome = ? WHERE nome = 'Carteira Black Friday 2026'", ['Lista de prospecção — Grande Porto Alegre']);
Banco::executar("UPDATE configuracoes SET valor = '' WHERE chave IN ('carteira.campanha_origem', 'carteira.campanha_titulo', 'carteira.campanha_chamada')");

$configuracoes = [
    ['prospeccao.cnaes', '4729699 Produtos naturais e alimentos especializados (varejo),4771701 Farmácia sem manipulação,4771702 Farmácia de manipulação,4772500 Cosméticos e perfumaria,4637199 Atacadista de alimentos especializados,4639701 Atacadista de alimentos em geral,4644301 Atacadista de medicamentos',
        'lista', 'Prospecção', 'CNAEs do nosso público', 'Código (só números) e descrição, separados por vírgula. Placeholder: confirmar com os CNAEs dos clientes atuais', 70],
    ['prospeccao.etapa_inicial', 'Não abordado', 'texto', 'Prospecção', 'Etapa do funil das empresas enviadas pela prospecção', 'Nome da etapa (veja em Listas)', 71],
    ['leads.etapa_inicial', 'Não abordado', 'texto', 'Captura de leads', 'Etapa do funil dos leads do site e dos anúncios', 'Nome da etapa (veja em Listas)', 72],
    ['leads.criar_tarefa', '1', 'booleano', 'Captura de leads', 'Criar tarefa "Retornar lead" para hoje', 'Lead respondido no mesmo dia converte muito mais', 73],
    ['leads.limite_por_hora', '5', 'numero', 'Captura de leads', 'Máximo de formulários por hora do mesmo aparelho/rede', 'Proteção contra robôs', 74],
    ['leads.dominios_permitidos', '', 'lista', 'Captura de leads', 'Sites que podem enviar o formulário', 'Ex.: https://www.perfeitaalquimia.com.br (vazio = qualquer site)', 75],
    ['leads.mensagem_obrigado', 'Recebemos seu contato! Em breve alguém da nossa equipe fala com você pelo WhatsApp.', 'texto', 'Captura de leads', 'Mensagem de agradecimento', 'Aparece depois que a pessoa envia o formulário', 76],
    ['mensagens.categorias', 'Prospecção,Reativação,Recompra,Cobrança,Pós-venda,Catálogo,Outros', 'lista', 'Mensagens prontas', 'Categorias das mensagens prontas', 'Separadas por vírgula', 77],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}

// Modelos iniciais (só quando ainda não há nenhum): a equipe edita depois em Conteúdo › Mensagens prontas
if (!Banco::valor('SELECT 1 FROM mensagens_prontas LIMIT 1')) {
    $modelos = [
        ['Primeiro contato', 'Prospecção', "Olá, {nome}! Tudo bem? Aqui é {vendedor}, da {empresa}. Somos fabricantes de suplementos e produtos naturais aqui do RS e atendemos lojas como a sua em todo o Brasil. Posso te mandar nosso catálogo com as condições para lojistas? {link_vendedor}"],
        ['Seguimento (sem resposta)', 'Prospecção', "Oi, {nome}! Passando para saber se conseguiu ver o catálogo que mandei. Muitos clientes em {cidade} começaram com um pedido pequeno de teste. Quer que eu monte uma sugestão com os mais vendidos?"],
        ['Sentimos sua falta', 'Reativação', "Olá, {nome}! Aqui é {vendedor}, da {empresa}. Faz {dias_sem_compra} dias desde a sua última compra ({ultima_compra}) e queria saber como estão as vendas. Temos novidades no catálogo: {link_vendedor}"],
        ['Hora de repor', 'Recompra', "Oi, {nome}! Pelo seu ritmo de compras, já deve estar na hora de repor o estoque. Quer que eu prepare o pedido igual ao último? Pelo catálogo também é rapidinho: {link_vendedor}"],
        ['Lembrete de pagamento', 'Cobrança', "Olá, {nome}! Tudo bem? Aqui é {vendedor}, da {empresa}. Consta em aberto um título no nosso sistema. Consegue verificar para mim? Se já pagou, pode desconsiderar e me mandar o comprovante. Obrigado!"],
        ['Confirmação de promessa', 'Cobrança', "Oi, {nome}! Combinado então: fico no aguardo do pagamento na data que falamos. Qualquer coisa estou por aqui. Obrigado pela atenção!"],
        ['Pedido chegou?', 'Pós-venda', "Olá, {nome}! O seu pedido já chegou certinho? Se precisar de material de divulgação ou de algum treinamento sobre os produtos para a equipe, é só me chamar."],
        ['Catálogo do vendedor', 'Catálogo', "Oi, {nome}! Segue o nosso catálogo com preços para lojistas. Os pedidos feitos por este link ficam comigo e eu acompanho tudo: {link_vendedor}"],
    ];
    foreach ($modelos as $i => [$titulo, $categoria, $texto]) {
        Banco::inserir('mensagens_prontas', ['titulo' => $titulo, 'categoria' => $categoria, 'texto' => $texto, 'ordem' => $i]);
    }
}
