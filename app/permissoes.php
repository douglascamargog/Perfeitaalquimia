<?php
/**
 * Catálogo de PERMISSÕES do sistema e quais PERFIS têm cada uma (padrão da instalação).
 *
 * Para dar uma permissão nova a um perfil, inclua o slug do perfil na lista.
 * Depois da instalação, a fonte da verdade é a tabela `perfil_permissoes`.
 * O perfil admin sempre tem '*' (tudo).
 */
return [
    // permissão              => [descrição, [perfis que têm]]
    'inicio.ver'              => ['Ver a tela inicial', ['gestor', 'vendedor', 'financeiro', 'marketing', 'producao']],
    'carteira.ver_propria'    => ['Ver a própria carteira de clientes', ['gestor', 'vendedor']],
    'carteira.ver_todas'      => ['Ver todas as carteiras', ['gestor']],
    'carteira.transferir'     => ['Trocar o vendedor responsável', ['gestor']],
    'carteira.importar'       => ['Importar e exportar planilhas', ['gestor']],
    'leads.ver'               => ['Ver leads de tráfego (site e anúncios)', ['gestor', 'marketing']],
    'prospeccao.usar'         => ['Usar a prospecção automática', ['gestor', 'vendedor']],
    'pedidos.criar'           => ['Criar pedidos para clientes', ['gestor', 'vendedor']],
    'pedidos.ver_todos'       => ['Ver pedidos de todos os vendedores', ['gestor', 'financeiro']],
    'pedidos.aprovar'         => ['Aprovar ou recusar pedidos', ['financeiro']],
    'credito.ver'             => ['Ver situação de crédito e títulos', ['financeiro', 'gestor']],
    'painel.ver'              => ['Ver painel de vendas', ['gestor', 'financeiro']],
    'metas.gerenciar'         => ['Cadastrar metas', ['gestor']],
    'catalogo.editar'         => ['Editar conteúdo dos produtos e do catálogo', ['marketing']],
    'academia.ver'            => ['Acessar a Academia', ['gestor', 'vendedor', 'marketing']],
    'academia.editar'         => ['Editar conteúdo da Academia', ['marketing']],
    'midia.gerenciar'         => ['Biblioteca de mídia', ['marketing']],
    'mensagens.gerenciar'     => ['Editar mensagens prontas de WhatsApp', ['gestor', 'marketing']],
    'producao.gerenciar'      => ['Matérias-primas, fichas técnicas e ordens de produção', ['producao']],
    'usuarios.gerenciar'      => ['Cadastrar usuários e funções', []],
    'config.gerenciar'        => ['Alterar configurações e regras comerciais', []],
    'auditoria.ver'           => ['Ver o log de auditoria', []],
    'integracao.gerenciar'    => ['Ver e disparar a sincronização com o Omie', []],
];
