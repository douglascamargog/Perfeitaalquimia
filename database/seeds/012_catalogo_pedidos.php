<?php
/**
 * Catálogo B2B e pedidos — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 */
use App\Core\Banco;

// ---------- Permissões ----------
$permissoes = [
    'catalogo.acessos' => ['vendedor', 'gestor'],     // gerar acesso do cliente ao catálogo
    'pedidos.criar'    => ['vendedor', 'gestor'],
    'pedidos.ver_todos' => ['gestor', 'financeiro'],
    'pedidos.aprovar'  => ['financeiro'],
];
foreach ($permissoes as $permissao => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$permissao, $perfil]);
    }
}

// ---------- Configurações ----------
$configuracoes = [
    ['catalogo.titulo', 'Catálogo Perfeita Alquimia', 'texto', 'Catálogo', 'Título do catálogo', 'Aparece no topo do catálogo dos clientes', 40],
    ['catalogo.chamada', 'Suplementos e produtos naturais fabricados no RS. Entre com o CNPJ da sua loja para ver os preços e fazer o pedido.',
        'texto', 'Catálogo', 'Texto de boas-vindas', '', 41],
    ['catalogo.mostrar_sem_preco', '0', 'booleano', 'Catálogo', 'Mostrar produtos sem preço para o cliente logado', 'Desligado = produto sem preço na tabela do cliente some da vitrine dele', 42],
    ['catalogo.email_remetente', '', 'texto', 'Catálogo', 'E-mail que envia o código de acesso', 'Ex.: catalogo@somosalquimia.com.br (crie no cPanel). Vazio = usa o padrão do servidor', 43],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
