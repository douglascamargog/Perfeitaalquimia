<?php
/**
 * Versões do One — o que mudou em cada uma (aparece para todos na janela "Novidades" e em /novidades).
 *
 * COMO NUMERAR (a mais nova sempre em PRIMEIRO na lista):
 *   - atualização pequena (ajustes, correções, uma tela nova)   → muda a decimal: 2.0 → 2.1 → 2.2
 *   - atualização grande (módulo novo, mudança no jeito de usar) → muda o número: 2.x → 3.0
 * O pacote sai com o nome da versão: ONE_V2.1.zip.
 *
 * Cada item é uma frase curta, do ponto de vista de quem usa (sem termos técnicos).
 *   'novo'     → o que foi adicionado
 *   'melhoria' → o que ficou melhor
 *   'correcao' → erros corrigidos
 */
return [
    [
        'versao' => '2.1', 'data' => '2026-11-13', 'titulo' => 'Atualização automática do banco',
        'melhoria' => [
            'Para atualizar, basta extrair o pacote: na primeira visita, o One faz o backup e aplica sozinho as mudanças do banco.',
        ],
        'correcao' => [
            'Depois de extrair uma versão nova, o site inteiro dava "erro 500" até a atualização ser aplicada, e a própria tela de Atualização não abria.',
        ],
    ],
    [
        'versao' => '2.0', 'data' => '2026-11-13', 'titulo' => 'Acessos por aba e o que aparece para cada um',
        'novo' => [
            'Administração › Acessos: o administrador define, para cada perfil, se cada aba fica Sem acesso, Ver ou Ver e editar.',
            'Exceções por pessoa: dar ou tirar uma aba só de alguém, no cadastro do usuário.',
            'Todas as abas aparecem no menu: o cadeado 🔒 indica sem acesso e o olho 👁 indica só leitura.',
            'O Início, o sino e as dicas do One mostram o que é da sua função. Ajuste em Meu perfil › "O que aparece para mim".',
            'Novidades da versão: depois de cada atualização, esta janela mostra o que mudou. O histórico fica no rodapé do menu.',
        ],
        'melhoria' => [
            'Vendedores podem ver o Mural e o Planejamento do Marketing, sem alterar nada.',
            'Metas, Produtos, Mensagens prontas e Prospecção abrem em modo só leitura para quem tem apenas "Ver".',
            'Ao abrir uma aba sem acesso, aparece uma página explicando a quem pedir a liberação.',
        ],
        'correcao' => [
            'O registro das mudanças de acesso na Auditoria passou a mostrar as listas certinho.',
        ],
    ],
    [
        'versao' => '1.4', 'data' => '2026-11-13', 'titulo' => 'Notificações',
        'novo' => [
            'Sino no topo com o que aconteceu: pedido para aprovar, pedido aprovado, recusado ou faturado, lead novo, cliente que passou para você.',
            'Contador vermelho no menu com o que está pendente em cada aba (Aprovação, Agenda, Cobrança, Recompra...).',
            'Etiqueta "novidade" nos clientes e pedidos que mudaram, e aviso flutuante quando chega algo novo.',
        ],
        'correcao' => [
            'Depois de salvar, a página continua onde você estava, sem voltar para o topo.',
        ],
    ],
    [
        'versao' => '1.3', 'data' => '2026-11-13', 'titulo' => 'Equipe, código do produto e busca rápida',
        'novo' => [
            'Código do produto em todo o sistema (catálogo, pedidos, app, Academia e Produção), com importação por planilha.',
            'Ficha do funcionário e Meu perfil: foto, aniversário e contatos. Aniversariantes do mês no Início.',
            'Busca rápida no topo (tecla /): cliente por nome, CNPJ ou telefone e produto pelo código.',
            'Academia › Como usar o One, no lugar das aulas do Leads2b.',
            'Cópia de segurança automática do banco, todos os dias.',
        ],
        'melhoria' => [
            'Recompra e Cobrança usam os modelos de Mensagens prontas (tudo num lugar só).',
        ],
        'correcao' => [
            'Catálogo do lojista sem produtos para colocar no carrinho.',
        ],
    ],
    [
        'versao' => '1.2', 'data' => '2026-11-13', 'titulo' => 'Prospecção, mensagens prontas e leads do site',
        'novo' => [
            'Prospecção completa: Para abordar, Encontrar empresas (base de CNPJs) e Leads do site.',
            'Mensagens prontas de WhatsApp com {nome} e {link_vendedor}, usadas direto da ficha do cliente.',
            'Formulários do site e dos anúncios viram leads no funil, com a campanha de origem.',
            'Tela Integração Omie: o que vem e vai para o ERP, testar conexão e sincronizar.',
        ],
    ],
    [
        'versao' => '1.1', 'data' => '2026-11-13', 'titulo' => 'Produção e matérias-primas',
        'novo' => [
            'Matérias-primas com entrada de compras, inventário e previsão de quando acaba.',
            'Fichas técnicas e ordens de produção com baixa automática das matérias-primas.',
            'Estoque de produto acabado com aviso de reabastecimento.',
        ],
    ],
    [
        'versao' => '1.0', 'data' => '2026-11-12', 'titulo' => 'Primeira versão do One',
        'novo' => [
            'Carteira e CRM no lugar do Leads2b: funil, mapa, linha do tempo, agenda e reativação.',
            'Catálogo B2B com login do cliente, pedido mínimo por região e link do vendedor.',
            'Pedidos com aprovação do Financeiro, painel de vendas, metas e recompra.',
            'App do vendedor externo que funciona sem internet.',
            'Academia, Cobrança, Marketing e atualização do sistema sem perder dados.',
        ],
    ],
];
