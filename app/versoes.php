<?php
/**
 * Versões do One — o que mudou em cada uma (aparece para todos na janela "Novidades" e em /novidades).
 *
 * COMO NUMERAR (a mais nova sempre em PRIMEIRO na lista):
 *   - atualização pequena (ajustes, correções, uma tela nova)   → muda a decimal: 2.0 → 2.1 → 2.2
 *   - atualização grande (módulo novo, mudança no jeito de usar) → muda o número: 2.x → 3.0
 * O pacote sai com o nome da versão: ONE_V2.1.zip.
 *
 * Escreva para a EQUIPE (vendedores, financeiro, marketing...): frases curtas sobre o que a pessoa
 * percebe no dia a dia. Nada de "como instalar", backup, banco, telas de administração ou termos técnicos.
 *   'novo'     → o que apareceu de novo para quem usa
 *   'melhoria' → o que ficou melhor ou mais fácil
 *   'correcao' → problemas que a pessoa sentia e foram resolvidos
 *   'admin'    → (opcional) recado técnico só para quem administra o sistema
 *                (Usuários/Acessos, Configurações, Atualização, Integração, backup). A equipe não vê.
 */
return [
    [
        'versao' => '2.2', 'data' => '2026-11-13', 'titulo' => 'Novidades mais fáceis de ler',
        'admin' => [
            'As novidades de cada versão agora mostram para a equipe só o que muda no dia a dia dela.',
            'Recados técnicos das versões (instalação, banco, telas de administração) aparecem só para quem administra o sistema.',
        ],
    ],
    [
        'versao' => '2.1', 'data' => '2026-11-13', 'titulo' => 'Sistema mais estável nas atualizações',
        'correcao' => [
            'O One saía do ar logo depois de algumas atualizações. Agora as atualizações acontecem sem interromper o seu trabalho.',
        ],
        'admin' => [
            'Para atualizar, basta extrair o pacote: na primeira visita, o One faz o backup do banco e aplica sozinho as mudanças. O botão "Aplicar" não é mais necessário.',
            'Se a atualização do banco falhar, aparece uma página de manutenção e o erro fica em storage/logs/php-erros.log.',
        ],
    ],
    [
        'versao' => '2.0', 'data' => '2026-11-13', 'titulo' => 'Menu completo e Início do seu jeito',
        'novo' => [
            'Todas as abas aparecem no menu. O cadeado 🔒 indica que você não tem acesso; o olho 👁 indica que você pode ver, mas não alterar.',
            'O Início, o sino e as dicas do One mostram o que é da sua função. Você pode ajustar em Meu perfil › "O que aparece para mim".',
            'Novidades: depois de cada atualização, esta janela mostra o que mudou. Todas as versões ficam no link do rodapé do menu.',
        ],
        'melhoria' => [
            'Vendedores podem acompanhar o Mural de posts e o Planejamento do Marketing.',
            'Ao abrir uma aba sem acesso, aparece uma explicação de como pedir a liberação.',
        ],
        'admin' => [
            'Administração › Acessos: para cada perfil, cada aba fica Sem acesso, Ver ou Ver e editar, mais as ações especiais.',
            'Exceções por pessoa no cadastro do usuário ("Acessos desta pessoa").',
            'Áreas de foco de cada função em Administração › Funções. Quem é admin e trabalha numa área deve ter a função dela marcada.',
            'As mudanças de acesso ficam registradas na Auditoria.',
        ],
    ],
    [
        'versao' => '1.4', 'data' => '2026-11-13', 'titulo' => 'Notificações',
        'novo' => [
            'Sino no topo com o que aconteceu: pedido para aprovar, pedido aprovado, recusado ou faturado, lead novo, cliente que passou para você.',
            'Número vermelho no menu mostrando o que está pendente em cada aba.',
            'Etiqueta "novidade" nos clientes e pedidos que mudaram desde a última vez que você viu.',
        ],
        'correcao' => [
            'Depois de salvar, a página continua onde você estava, sem voltar para o topo.',
        ],
    ],
    [
        'versao' => '1.3', 'data' => '2026-11-13', 'titulo' => 'Código do produto, Meu perfil e busca rápida',
        'novo' => [
            'Código do produto no catálogo, nos pedidos, no app, na Academia e na Produção.',
            'Meu perfil: sua foto, aniversário e contatos. Os aniversariantes do mês aparecem no Início.',
            'Busca rápida no topo (tecla /): encontre um cliente pelo nome, CNPJ ou telefone, ou um produto pelo código.',
            'Academia › Como usar o One: o passo a passo do sistema.',
        ],
        'melhoria' => [
            'As mensagens de WhatsApp da Recompra e da Cobrança são as mesmas de Mensagens prontas.',
        ],
        'correcao' => [
            'O catálogo do lojista não mostrava produtos para colocar no carrinho.',
        ],
        'admin' => [
            'Cadastro e importação dos códigos por planilha em Conteúdo › Produtos.',
            'Cópia de segurança automática do banco todos os dias (data do último backup em Administração › Atualização).',
        ],
    ],
    [
        'versao' => '1.2', 'data' => '2026-11-13', 'titulo' => 'Prospecção, mensagens prontas e leads do site',
        'novo' => [
            'Prospecção: lista de lojas para abordar e busca de empresas por cidade para mandar ao funil.',
            'Mensagens prontas de WhatsApp com o nome do cliente e o seu link, direto da ficha do cliente.',
            'Quem preenche o formulário do site ou dos anúncios entra no funil como lead.',
        ],
        'admin' => [
            'Tela Administração › Integração Omie: o que vem e vai para o ERP, testar conexão e sincronizar.',
        ],
    ],
    [
        'versao' => '1.1', 'data' => '2026-11-13', 'titulo' => 'Produção e matérias-primas',
        'novo' => [
            'Matérias-primas com entrada de compras e previsão de quando acabam.',
            'Fichas técnicas e ordens de produção: ao concluir, o estoque das matérias-primas baixa sozinho.',
            'Estoque de produto pronto com aviso quando precisa produzir mais.',
        ],
    ],
    [
        'versao' => '1.0', 'data' => '2026-11-12', 'titulo' => 'Primeira versão do One',
        'novo' => [
            'Carteira de clientes com funil, mapa, linha do tempo, agenda de retornos e reativação.',
            'Catálogo para o cliente fazer pedidos, com o seu link de vendedor.',
            'Pedidos com aprovação do Financeiro, painel de vendas, metas e alerta de recompra.',
            'App para o vendedor externo, que funciona até sem internet.',
            'Academia, Cobrança e Marketing.',
        ],
    ],
];
