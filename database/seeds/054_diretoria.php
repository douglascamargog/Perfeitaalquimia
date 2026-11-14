<?php
/**
 * Perfil DIRETORIA — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - perfil "Diretoria": vê todas as áreas e aprova o que for pedido a ela, mas não altera nada (permissão acompanhar.somente);
 *    nunca recebe configurações, usuários, atualização ou integração;
 *  - função "Diretoria" (para ser escolhida como aprovadora nas etapas do Desenvolvimento);
 *  - Configurações › Diretoria: liga/desliga cada tipo de aviso que vai para os donos.
 */
use App\Core\Banco;

Banco::executar("INSERT IGNORE INTO perfis (slug, nome, descricao)
    VALUES ('diretoria', 'Diretoria', 'Acompanha todas as áreas e aprova o que for pedido. Não altera configurações nem o dia a dia.')");

$ver = ['inicio.ver', 'carteira.ver_todas', 'pedidos.ver_todos', 'pedidos.aprovar', 'credito.ver', 'cobranca.ver', 'recompra.ver', 'prospeccao.ver',
    'terceirizacao.ver', 'painel.ver', 'equipe.ver', 'acompanhamento.ver', 'metas.ver', 'marketing.ver', 'marketing.aprovar', 'ecommerce.ver',
    'leads.ver', 'academia.ver', 'produtos.ver', 'mensagens.ver', 'producao.ver', 'desenvolvimento.ver', 'deposito.ver', 'compras.ver',
    'auditoria.ver', 'acompanhar.somente'];
foreach ($ver as $perm) {
    Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = 'diretoria'", [$perm]);
}

if (!Banco::valor("SELECT 1 FROM funcoes WHERE slug = 'diretoria'")) {
    Banco::executar("INSERT INTO funcoes (slug, nome, descricao, areas_foco, ordem)
        SELECT 'diretoria', 'Diretoria', 'Sócios: acompanham a empresa e aprovam o que for pedido', 'diretoria', COALESCE(MAX(ordem), 0) + 1 FROM funcoes");
}

$avisos = [
    ['etapa_dp', 'Etapa do Desenvolvimento concluída e produto lançado', 10],
    ['estoque', 'Matéria-prima, embalagem, rótulo ou produto acabado abaixo do mínimo', 20],
    ['op_criada', 'Ordem de produção criada', 30],
    ['op_concluida', 'Ordem de produção concluída (produtos entraram no estoque)', 40],
    ['meta', 'Alguém da equipe bateu a meta do mês', 50],
    ['problemas', 'Problemas: pedido recusado, despacho sem item, cobrança para jurídico/perda', 60],
];
foreach ($avisos as [$chave, $rotulo, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', ['diretoria.aviso_' . $chave])) {
        Banco::inserir('configuracoes', ['chave' => 'diretoria.aviso_' . $chave, 'valor' => '1', 'tipo' => 'booleano', 'grupo' => 'Diretoria',
            'rotulo' => 'Avisar: ' . $rotulo, 'descricao' => 'Vai para a Diretoria (e o Admin) que acompanham a área "Diretoria".', 'ordem' => $ordem]);
    }
}
return null;
