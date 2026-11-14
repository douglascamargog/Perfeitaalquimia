<?php
/**
 * Depósito e Compras — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - perfil "Depósito" e função "Separação e Expedição" (foco: depósito);
 *  - permissões: deposito.ver (Gestor, Financeiro, Depósito), deposito.operar (Depósito),
 *    compras.ver (Gestor, Financeiro). A Qualidade faz os orçamentos pela FUNÇÃO (configuração compras.funcao);
 *  - áreas de foco "deposito" e "compras" (Qualidade passa a acompanhar Compras);
 *  - configurações do depósito (lote obrigatório, transportadoras sugeridas).
 */
use App\Core\Banco;

Banco::executar("INSERT IGNORE INTO perfis (slug, nome, descricao)
    VALUES ('deposito', 'Depósito', 'Separa e despacha os pedidos, anota lote e rastreio, recebe as compras')");

if (!Banco::valor("SELECT 1 FROM funcoes WHERE slug = 'deposito'")) {
    Banco::executar("INSERT INTO funcoes (slug, nome, descricao, areas_foco, ordem)
        SELECT 'deposito', 'Separação e Expedição', 'Fila de separação, lote e validade, despacho com rastreio e recebimento das compras', 'deposito', COALESCE(MAX(ordem), 0) + 1 FROM funcoes");
}
Banco::executar("UPDATE funcoes SET areas_foco = CONCAT(COALESCE(NULLIF(areas_foco, ''), ''), IF(COALESCE(areas_foco, '') = '', '', ','), 'compras')
    WHERE slug = 'qualidade' AND FIND_IN_SET('compras', COALESCE(areas_foco, '')) = 0");

$permissoes = [
    'deposito.ver'    => ['gestor', 'financeiro', 'deposito'],
    'deposito.operar' => ['deposito'],
    'compras.ver'     => ['gestor', 'financeiro'],
    'inicio.ver'      => ['deposito'],
    'produtos.ver'    => ['deposito'],
    'academia.ver'    => ['deposito'],
];
foreach ($permissoes as $perm => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$perm, $perfil]);
    }
}

$configs = [
    ['deposito.lote_obrigatorio', '1', 'booleano', 'Depósito', 'Lote obrigatório na separação', 'Cada item separado precisa ter o lote anotado (rastreabilidade).', 10],
    ['deposito.transportadoras', "Retirada no balcão,Correios", 'lista', 'Depósito', 'Transportadoras sugeridas', 'Separadas por vírgula. No despacho dá para digitar outra.', 20],
    ['deposito.dias_atraso', '2', 'numero', 'Depósito', 'Dias para considerar atrasado', 'Pedido faturado há mais dias que isso sem despachar aparece como atrasado.', 30],
    ['compras.funcao', 'qualidade', 'texto', 'Compras', 'Função que faz os orçamentos', 'Slug da função (Administração › Funções) que cria orçamentos e compras.', 10],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
