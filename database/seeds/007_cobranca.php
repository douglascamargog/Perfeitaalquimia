<?php
/**
 * Cobrança e busca de contatos com IA — DADOS ESSENCIAIS (rodam na instalação e nas atualizações).
 * Tudo idempotente: só cria o que ainda não existe. Tudo editável depois pelo admin.
 */
use App\Core\Banco;

// ---------- 1. Etapas do funil de cobrança (Configurações › Listas › Funil de cobrança) ----------
// tipo: aberta | sem_contato (pede busca de contatos) | promessa | pago | acordo | juridico | perda
if (!Banco::valor('SELECT 1 FROM cobranca_etapas LIMIT 1')) {
    $etapas = [
        ['Título vencido',               '#f59e0b', 'aberta'],
        ['Tentativa de contato',         '#38bdf8', 'aberta'],
        ['Sem contato — buscar contatos', '#ef4444', 'sem_contato'],
        ['Em negociação',                '#a78bfa', 'aberta'],
        ['Promessa de pagamento',        '#22c55e', 'promessa'],
        ['Pago',                         '#16a34a', 'pago'],
        ['Acordo fechado',               '#0ea5e9', 'acordo'],
        ['Jurídico / protesto',          '#7c3aed', 'juridico'],
        ['Perda',                        '#78716c', 'perda'],
    ];
    foreach ($etapas as $i => [$nome, $cor, $tipo]) {
        Banco::inserir('cobranca_etapas', ['nome' => $nome, 'ordem' => $i + 1, 'cor' => $cor, 'tipo' => $tipo]);
    }
}

// ---------- 2. Função "Cobrança" (os casos vão para quem tem esta função) ----------
if (!Banco::valor("SELECT 1 FROM funcoes WHERE slug = 'cobranca'")) {
    $ordem = (int) Banco::valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM funcoes');
    Banco::inserir('funcoes', ['slug' => 'cobranca', 'nome' => 'Cobrança', 'descricao' => 'Recupera títulos em atraso e clientes sem contato', 'ordem' => $ordem]);
}

// ---------- 3. Permissões (perfis que já existem num sistema em uso) ----------
$permissoes = [
    'cobranca.ver'       => ['financeiro', 'gestor'],
    'cobranca.gerenciar' => ['financeiro'],
    'cobranca.enviar'    => ['gestor', 'vendedor', 'financeiro'],
    'contatos.buscar_ia' => ['gestor', 'vendedor', 'financeiro'],
];
foreach ($permissoes as $permissao => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$permissao, $perfil]);
    }
}

// ---------- 4. Configurações ----------
$configuracoes = [
    // chave, valor, tipo, grupo, rótulo, descrição, ordem
    ['cobranca.dias_atraso_entrada', '5', 'numero', 'Cobrança', 'Dias de atraso para entrar na cobrança',
        'Título vencido há este número de dias entra sozinho na Cobrança (importação do ERP / cron)', 60],
    ['cobranca.tentativas_para_sem_contato', '3', 'numero', 'Cobrança', 'Tentativas sem sucesso até "Sem contato"',
        'Depois de X ligações/mensagens sem resposta, o caso vai para a etapa de buscar contatos', 61],
    ['cobranca.funcao', 'cobranca', 'texto', 'Cobrança', 'Função que recebe os casos',
        'Slug da função (Administração › Funções). Sem ninguém nela, o caso vai para o Financeiro', 62],
    ['cobranca.bloquear_boleto', '1', 'booleano', 'Cobrança', 'Bloquear boleto para cliente em cobrança',
        'No catálogo, cliente com título em atraso paga só com Pix ou cartão', 63],
    ['cobranca.link_pix', '', 'texto', 'Cobrança', 'Link ou chave Pix para as mensagens',
        'PENDENTE: preencher com a chave/link de pagamento da empresa. Usado em {link_pix}', 64],
    ['cobranca.msg_lembrete', 'Olá, {nome}! Tudo bem? Aqui é do financeiro da Perfeita Alquimia. Identificamos um valor de {valor} com vencimento em {vencimento} em aberto. Podemos te ajudar a regularizar? {link_pix}',
        'texto', 'Cobrança', 'Mensagem: lembrete gentil', 'Variáveis: {nome} {valor} {vencimento} {dias} {link_pix}', 65],
    ['cobranca.msg_segunda_via', 'Olá, {nome}! Segue a forma de pagamento do valor de {valor} (vencido em {vencimento}): {link_pix}. Se já pagou, por favor nos envie o comprovante. Obrigado!',
        'texto', 'Cobrança', 'Mensagem: segunda via', 'Variáveis: {nome} {valor} {vencimento} {dias} {link_pix}', 66],
    ['cobranca.msg_acordo', 'Olá, {nome}! Sabemos que imprevistos acontecem. Para o valor de {valor} em aberto há {dias} dias, conseguimos montar uma condição especial de pagamento. Podemos conversar?',
        'texto', 'Cobrança', 'Mensagem: proposta de acordo', 'Variáveis: {nome} {valor} {vencimento} {dias} {link_pix}', 67],

    ['busca_ia.ativa', '1', 'booleano', 'Busca de contatos (IA)', 'Botão "Buscar contatos com IA" ligado', '', 70],
    ['busca_ia.provedor', 'fake', 'texto', 'Busca de contatos (IA)', 'Provedor da busca',
        'fake = demonstração (sem custo, sem internet) · claude = busca real na web (precisa da chave no config.php)', 71],
    ['busca_ia.limite_diario', '30', 'numero', 'Busca de contatos (IA)', 'Limite de buscas por dia (empresa toda)', 'Controla o custo da busca real', 72],
    ['busca_ia.dias_entre_buscas', '7', 'numero', 'Busca de contatos (IA)', 'Dias até poder buscar a mesma empresa de novo',
        'Antes disso, mostra o resultado da última busca', 73],
    ['busca_ia.consultar_receita', '1', 'booleano', 'Busca de contatos (IA)', 'Consultar antes a Receita (BrasilAPI, gratuito)',
        'Telefone e e-mail do cadastro do CNPJ na Receita', 74],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
