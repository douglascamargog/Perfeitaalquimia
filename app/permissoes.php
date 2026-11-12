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
    'catalogo.acessos'        => ['Gerar acesso do cliente ao catálogo', ['gestor', 'vendedor']],
    'pedidos.criar'           => ['Criar pedidos para clientes', ['gestor', 'vendedor']],
    'pedidos.ver_todos'       => ['Ver pedidos de todos os vendedores', ['gestor', 'financeiro']],
    'pedidos.aprovar'         => ['Aprovar ou recusar pedidos', ['financeiro']],
    'credito.ver'             => ['Ver situação de crédito e títulos', ['financeiro', 'gestor']],
    'cobranca.ver'            => ['Ver a Cobrança (clientes com títulos em atraso)', ['financeiro', 'gestor']],
    'cobranca.gerenciar'      => ['Trabalhar a Cobrança: tentativas, promessas, etapas, importar títulos', ['financeiro']],
    'cobranca.enviar'         => ['Enviar um cliente da carteira para a Cobrança', ['gestor', 'vendedor', 'financeiro']],
    'contatos.buscar_ia'      => ['Buscar contatos novos do cliente na internet (IA)', ['gestor', 'vendedor', 'financeiro']],
    'painel.ver'              => ['Ver painel de vendas', ['gestor', 'financeiro']],
    'recompra.ver'            => ['Ver a fila de recompra (clientes que passaram do prazo)', ['gestor', 'vendedor']],
    'metas.gerenciar'         => ['Cadastrar metas e lançar o vendido da equipe', ['gestor']],
    'meu_mes.usar'            => ['Usar o Meu mês (meta individual, vendas, tarefas)', ['gestor', 'vendedor']],
    'marketing.ver'           => ['Ver o Marketing (mural de posts e planejamento)', ['marketing', 'gestor']],
    'marketing.editar'        => ['Criar e editar posts e campanhas', ['marketing']],
    'marketing.aprovar'       => ['Aprovar posts antes da publicação', []],
    'ecommerce.ver'           => ['Ver o desempenho do e-commerce (Tray)', ['marketing', 'gestor', 'financeiro']],
    'catalogo.editar'         => ['Editar conteúdo dos produtos e do catálogo', ['marketing']],
    'academia.ver'            => ['Acessar a Academia', ['gestor', 'vendedor', 'marketing']],
    'academia.editar'         => ['Editar conteúdo da Academia', ['marketing']],
    'midia.gerenciar'         => ['Biblioteca de mídia', ['marketing']],
    'mensagens.gerenciar'     => ['Editar mensagens prontas de WhatsApp', ['gestor', 'marketing']],
    'producao.gerenciar'      => ['Matérias-primas, fichas técnicas e ordens de produção', ['producao']],
    'usuarios.gerenciar'      => ['Cadastrar usuários e funções', []],
    'config.gerenciar'        => ['Alterar configurações e regras comerciais', []],
    'auditoria.ver'           => ['Ver o log de auditoria', []],
    'sistema.atualizar'       => ['Aplicar atualizações do sistema e baixar backup do banco', []],
    'integracao.gerenciar'    => ['Ver e disparar a sincronização com o Omie', []],
];
