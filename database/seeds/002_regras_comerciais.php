<?php
/**
 * Regras comerciais INICIAIS. Tudo editável pelo admin depois.
 *
 * ATENÇÃO: pedido mínimo por região e condições de pagamento ainda estão PENDENTES na empresa.
 * Os valores abaixo são PLACEHOLDERS de demonstração, marcados como tal na tela.
 */
use App\Core\Banco;

// ---------- Segmentos padrão ----------
foreach (['Produtos naturais', 'Suplementos', 'Farmácia', 'Outros'] as $i => $nome) {
    Banco::inserir('segmentos', ['nome' => $nome, 'ordem' => $i + 1]);
}

// ---------- Origens ----------
foreach (['Carteira importada', 'Indicação', 'Site', 'Anúncio (tráfego pago)', 'Prospecção automática', 'Catálogo', 'Feira/Evento', 'Outros'] as $nome) {
    Banco::inserir('origens', ['nome' => $nome]);
}

// ---------- Etapas do funil (padrão da especificação) ----------
$etapas = [
    ['Validar cadastro',   '#94a3b8', 'aberta'],
    ['Não abordado',       '#64748b', 'aberta'],
    ['Contato realizado',  '#38bdf8', 'aberta'],
    ['Em negociação',      '#f59e0b', 'aberta'],
    ['Proposta enviada',   '#a78bfa', 'aberta'],
    ['Aguardando retorno', '#fb923c', 'aberta'],
    ['Cliente ativo',      '#22c55e', 'ganho'],
    ['Sem interesse',      '#ef4444', 'perdido'],
    ['Inativo',            '#78716c', 'inativo'],
];
foreach ($etapas as $i => [$nome, $cor, $tipo]) {
    Banco::inserir('funil_etapas', ['nome' => $nome, 'ordem' => $i + 1, 'cor' => $cor, 'tipo' => $tipo]);
}

// ---------- Regiões e UFs (pedido mínimo = PLACEHOLDER) ----------
$regioes = [
    'Sul'          => [500,  ['RS', 'SC', 'PR']],
    'Sudeste'      => [800,  ['SP', 'RJ', 'MG', 'ES']],
    'Centro-Oeste' => [1000, ['DF', 'GO', 'MT', 'MS']],
    'Nordeste'     => [1200, ['BA', 'SE', 'AL', 'PE', 'PB', 'RN', 'CE', 'PI', 'MA']],
    'Norte'        => [1500, ['AM', 'PA', 'AC', 'RO', 'RR', 'AP', 'TO']],
];
foreach ($regioes as $nome => [$minimo, $ufs]) {
    $id = Banco::inserir('regioes', ['nome' => $nome, 'pedido_minimo' => $minimo, 'observacao' => 'Valor de demonstração — tabela definitiva pendente']);
    foreach ($ufs as $uf) {
        Banco::inserir('regiao_ufs', ['uf' => $uf, 'regiao_id' => $id]);
    }
}

// ---------- Tabelas de preço ----------
foreach (['lojista' => 'Lojista', 'distribuidor' => 'Distribuidor', 'terceirizacao' => 'Terceirização'] as $slug => $nome) {
    Banco::inserir('tabelas_preco', ['slug' => $slug, 'nome' => $nome]);
}

// ---------- Formas e condições de pagamento (condições = PLACEHOLDER) ----------
$pix    = Banco::inserir('formas_pagamento', ['slug' => 'pix', 'nome' => 'Pix', 'ordem' => 1]);
$cartao = Banco::inserir('formas_pagamento', ['slug' => 'cartao', 'nome' => 'Cartão de crédito', 'ordem' => 2]);
$boleto = Banco::inserir('formas_pagamento', ['slug' => 'boleto', 'nome' => 'Boleto', 'bloqueia_com_atraso' => 1, 'ordem' => 3]);

Banco::inserir('condicoes_pagamento', ['forma_pagamento_id' => $pix,    'nome' => 'Pix à vista', 'parcelas' => 1, 'desconto_pct' => 3]);
Banco::inserir('condicoes_pagamento', ['forma_pagamento_id' => $cartao, 'nome' => 'Cartão em até 3x', 'parcelas' => 3]);
Banco::inserir('condicoes_pagamento', ['forma_pagamento_id' => $boleto, 'nome' => 'Boleto 28 dias', 'parcelas' => 1, 'prazo_dias' => '28']);
Banco::inserir('condicoes_pagamento', ['forma_pagamento_id' => $boleto, 'nome' => 'Boleto 28/56 dias', 'parcelas' => 2, 'prazo_dias' => '28,56', 'valor_minimo' => 1500]);

// ---------- Configurações gerais (tela Configurações) ----------
$configuracoes = [
    // chave, valor, tipo, grupo, rótulo, descrição
    ['empresa.nome', 'Perfeita Alquimia', 'texto', 'Empresa', 'Nome da empresa', 'Aparece no catálogo e nas mensagens'],
    ['empresa.whatsapp', '', 'texto', 'Empresa', 'WhatsApp da empresa', 'Somente números, com DDD. Ex.: 51999990000'],
    ['carteira.dias_inatividade', '90', 'numero', 'Carteira', 'Dias sem compra para ir à reativação', 'Cliente sem comprar há esse número de dias vai para a fila da função Prospecção e Reativação'],
    ['carteira.funcao_reativacao', 'prospeccao_reativacao', 'texto', 'Carteira', 'Função que recebe a fila de reativação', 'Slug da função (veja em Funções)'],
    ['carteira.faixas_sem_compra', '30,60,90', 'lista', 'Carteira', 'Faixas de "sem compra" no painel (dias)', 'Separe por vírgula'],
    ['carteira.itens_por_pagina', '50', 'numero', 'Carteira', 'Clientes por página na lista', ''],
    ['pedido.omie_etapa_inicial', '10', 'texto', 'Pedidos', 'Etapa inicial do pedido no Omie', 'Código da etapa "orçamento / a aprovar" no Omie'],
    ['pedido.exigir_aprovacao', '1', 'booleano', 'Pedidos', 'Todo pedido passa pelo Financeiro', ''],
    ['catalogo.codigo_validade_minutos', '10', 'numero', 'Catálogo', 'Validade do código de acesso (minutos)', 'Código enviado por e-mail/WhatsApp para o cliente entrar'],
    ['seguranca.sessao_minutos', '120', 'numero', 'Segurança', 'Sair automaticamente após (minutos sem uso)', ''],
    ['seguranca.login_max_tentativas', '5', 'numero', 'Segurança', 'Tentativas de senha antes de bloquear', ''],
    ['seguranca.login_bloqueio_minutos', '15', 'numero', 'Segurança', 'Tempo de bloqueio (minutos)', ''],
];
foreach ($configuracoes as $i => [$chave, $valor, $tipo, $grupo, $rotulo, $descricao]) {
    Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao') + ['ordem' => $i]);
}
