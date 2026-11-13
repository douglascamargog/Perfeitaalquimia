<?php
/**
 * Academia: treinamento "Como usar o One" — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - Cria as aulas (chave sistema_one), no mesmo formato das aulas de CRM: objetivo, blocos, prática e resposta.
 *  - Na trilha, o módulo "Rotina no Leads2b" vira "Rotina no One" e as frases que citavam o Leads2b passam a citar o One.
 *  - As aulas antigas do Leads2b continuam guardadas no banco (chave crm), sem apagar.
 */
use App\Core\Banco;

$aulas = [
    [
        'id' => 'primeiro-acesso', 'title' => 'Primeiro acesso e o One', 'short' => 'Login, menu, Início e o ajudante',
        'goal' => 'Entrar com o seu login, achar cada parte do sistema e saber o que o One sugere para o seu dia.',
        'blocks' => [
            ['Em palavras simples', [
                ['Login individual', 'Cada pessoa tem o próprio e-mail e senha. Não existe mais senha compartilhada: tudo o que você faz fica registrado com o seu nome.'],
                ['Perfil', 'Define o que você enxerga no menu (Vendedor, Gestor, Financeiro, Marketing, Produção, Admin).'],
                ['Função', 'Define as filas que você recebe (Televendas, Externo, Prospecção e Reativação...). As regras seguem a função, não a pessoa.'],
                ['One', 'O ajudante no canto da tela. Ele avisa retornos atrasados, leads esperando, recompra e metas.'],
            ]],
            ['No seu primeiro acesso', [
                'Entre com o e-mail e a senha provisória que recebeu e crie a sua senha (mínimo 8 caracteres).',
                'Clique no seu nome, no alto da tela, para abrir o Meu perfil: coloque a sua foto, o seu aniversário e o seu WhatsApp.',
                'Abra o Início: a agenda do dia, a sua meta e os avisos do One aparecem ali.',
                'Use a busca rápida no topo (ou aperte a tecla /) para achar qualquer cliente pelo nome, CNPJ ou telefone.',
            ]],
        ],
        'media' => [],
        'practice' => 'Você esqueceu a senha. Pode pedir a senha de um colega para entrar?',
        'answer' => 'Não. Cada login é pessoal e registra o que a pessoa fez. Peça ao administrador para redefinir a sua senha: no próximo acesso você cria uma nova.',
    ],
    [
        'id' => 'carteira', 'title' => 'Carteira e ficha do cliente', 'short' => 'Lista, funil, mapa e a ficha',
        'goal' => 'Encontrar seus clientes, entender a situação de cada um e ler a ficha antes de ligar.',
        'blocks' => [
            ['As visões da Carteira', [
                ['Visão geral', 'Números da sua carteira: quantos clientes, quantos sem compra, atividade da semana.'],
                ['Lista', 'Todos os clientes com filtros por cidade, UF, segmento, etapa, prioridade e situação de compra.'],
                ['Funil', 'Os leads e clientes em colunas pelas etapas (Não abordado, Contato realizado, Em negociação...).'],
                ['Mapa', 'Onde estão as lojas, agrupadas por estado e cidade.'],
            ]],
            ['Na ficha do cliente', [
                'Clique no nome do cliente em qualquer tela para abrir a ficha na lateral.',
                'Leia a linha do tempo: contatos, ligações, pedidos e mudanças de etapa, com quem fez e quando.',
                'Confira os últimos pedidos, a previsão da próxima compra e se há título em atraso.',
                'Todo cliente tem um responsável. Sem compra há muito tempo, ele vai sozinho para a fila de reativação.',
            ]],
        ],
        'media' => [],
        'practice' => 'Você não encontrou um cliente na Lista. Deve cadastrar de novo?',
        'answer' => 'Não. Primeiro limpe os filtros e procure pelo CNPJ na busca rápida. O sistema não deixa duplicar CNPJ; se o cliente for de outro vendedor, fale com o gestor.',
    ],
    [
        'id' => 'contato-agenda', 'title' => 'Registrar contato e agenda', 'short' => 'Linha do tempo e próximo contato',
        'goal' => 'Registrar cada conversa e deixar sempre marcado o próximo contato.',
        'blocks' => [
            ['Depois de cada contato', [
                'Na ficha, use "Registrar contato": escolha o canal (WhatsApp, ligação, visita, e-mail) e escreva o que foi combinado.',
                'Ligou e não atendeu? Use "Registrar ligação" com um clique: Atendeu, Não atendeu, Caixa postal ou Número errado.',
                'Marque o próximo contato com data. "Ligar depois" sem data não organiza a carteira.',
                'Mude a etapa do funil só quando o critério da etapa foi cumprido.',
            ]],
            ['A Agenda', [
                'Em Comercial › Agenda ficam os retornos atrasados, os de hoje e os futuros.',
                'Comece o dia pelos atrasados. Retorno feito: registre o contato e marque o próximo.',
            ]],
        ],
        'media' => [],
        'practice' => 'O cliente pediu para você ligar na semana que vem. O que registrar?',
        'answer' => 'Registre o contato de hoje (canal e resumo do que conversaram) e marque o próximo contato com a data combinada. Ele aparece na sua Agenda no dia certo.',
    ],
    [
        'id' => 'prospeccao', 'title' => 'Prospecção', 'short' => 'Para abordar e Encontrar empresas',
        'goal' => 'Trabalhar a fila de leads e trazer empresas novas para o funil.',
        'blocks' => [
            ['As abas da Prospecção', [
                ['Para abordar', 'A fila dos leads que ainda não compram, de qualquer origem. Comece pelos de prioridade alta.'],
                ['Encontrar empresas', 'Empresas ativas da base de CNPJs, por cidade e atividade. Quem já está na carteira aparece marcado.'],
                ['Leads do site', 'Formulários do site e dos anúncios, que entram sozinhos no funil.'],
            ]],
            ['Para trazer empresas novas', [
                'Em Encontrar empresas, escolha a cidade e marque "Esconder as que já estão na carteira".',
                'Marque as empresas que interessam e clique em "Enviar para o funil". Elas entram na sua carteira como leads.',
                'O telefone da base pública pode ser do contador: confirme o contato no primeiro telefonema.',
            ]],
        ],
        'media' => [],
        'practice' => 'Uma empresa da lista aparece como "na carteira". Você pode enviá-la de novo para o funil?',
        'answer' => 'Não precisa e o sistema não deixa: ela já existe na carteira de alguém. Abra a ficha para ver quem é o responsável.',
    ],
    [
        'id' => 'mensagens', 'title' => 'Mensagens prontas de WhatsApp', 'short' => 'Texto pronto com o nome da loja',
        'goal' => 'Mandar mensagens padronizadas pelo WhatsApp sem digitar tudo de novo.',
        'blocks' => [
            ['Como usar', [
                'Abra a ficha do cliente e procure "Mensagem pronta".',
                'Escolha o modelo (prospecção, reativação, recompra, cobrança...): o texto já vem com o nome da loja e o seu link do catálogo.',
                'Ajuste o texto se quiser e clique em "Abrir no WhatsApp". O contato fica registrado na linha do tempo.',
            ]],
            ['As variáveis', [
                ['{nome}', 'Nome da loja.'],
                ['{vendedor}', 'O seu primeiro nome.'],
                ['{link_vendedor}', 'O seu link do catálogo: os pedidos feitos por ele ficam com você.'],
                ['{dias_sem_compra}', 'Quantos dias desde a última compra.'],
            ]],
        ],
        'media' => [],
        'practice' => 'O cliente não tem WhatsApp cadastrado. Dá para usar a mensagem pronta?',
        'answer' => 'Dá: o botão vira "Copiar texto". Você cola onde precisar (e-mail, outro número) e o contato fica registrado do mesmo jeito.',
    ],
    [
        'id' => 'pedido', 'title' => 'Fazer um pedido e o catálogo do cliente', 'short' => 'Do carrinho à aprovação',
        'goal' => 'Lançar o pedido certo e ensinar o cliente a pedir sozinho pelo catálogo.',
        'blocks' => [
            ['Pedido feito por você', [
                'Na ficha ou em Pedidos › Novo pedido, escolha o cliente. Os preços são os da tabela dele (Lojista, Distribuidor ou Terceirização).',
                'Busque o produto pelo nome ou pelo código. O sistema mostra quanto falta para o pedido mínimo da região.',
                'Escolha a forma de pagamento. Cliente com título em atraso não tem boleto: só Pix ou cartão.',
                'O pedido vai para o Financeiro aprovar. Depois de faturado, a nota aparece na linha do tempo do cliente.',
            ]],
            ['Catálogo do cliente', [
                'Na ficha, gere o acesso ao catálogo e mande o código pelo WhatsApp. O cliente entra com o CNPJ e cria a senha.',
                'Mande sempre o seu link de vendedor: os pedidos feitos por ele ficam na sua carteira.',
                'O cliente pode repetir o último pedido com um clique.',
            ]],
        ],
        'media' => [],
        'practice' => 'O cliente diz que o catálogo não mostra o preço de um produto. O que pode ser?',
        'answer' => 'O produto está sem preço na tabela dele no ERP. Avise o gestor ou o financeiro; enquanto isso, você pode lançar o pedido dos outros itens.',
    ],
    [
        'id' => 'app-externo', 'title' => 'App do externo sem internet', 'short' => 'Pedido no celular, offline',
        'goal' => 'Instalar o app no celular e vender mesmo onde não há sinal.',
        'blocks' => [
            ['Instalar', [
                'No celular, abra o endereço do sistema com /app, entre com o seu login e escolha "Adicionar à tela inicial" (ou "Instalar app").',
                'Abra o app uma vez com internet: ele guarda a sua carteira, os produtos e os preços no celular.',
            ]],
            ['Vender sem internet', [
                'Escolha o cliente, os produtos e a forma de pagamento normalmente. O pedido fica "aguardando envio".',
                'Quando a internet voltar, o app envia sozinho. Cada pedido tem um código único e nunca entra duplicado.',
                'Abra o app com internet pelo menos uma vez por semana para atualizar os preços.',
            ]],
        ],
        'media' => [],
        'practice' => 'Você fez um pedido sem sinal e, com medo de perder, fez de novo quando a internet voltou. O que acontece?',
        'answer' => 'Foram dois pedidos diferentes, e os dois vão para aprovação. Não refaça: o primeiro sobe sozinho. Se duplicou, avise o financeiro para recusar um deles.',
    ],
    [
        'id' => 'recompra-cobranca', 'title' => 'Recompra, reativação e cobrança', 'short' => 'Quem precisa de atenção',
        'goal' => 'Usar as filas certas para não deixar cliente esfriar nem título esquecido.',
        'blocks' => [
            ['As filas', [
                ['Recompra', 'Clientes que passaram do próprio ritmo de compra. Ligue antes que comprem de outro.'],
                ['Reativação', 'Clientes sem compra há muitos dias, na fila da função Prospecção e Reativação.'],
                ['Cobrança', 'Clientes com título em atraso, com funil próprio, promessas de pagamento e busca de contatos novos.'],
            ]],
            ['Na prática', [
                'Na Recompra, use "Novo pedido (repetir)" para começar pelo último pedido do cliente.',
                'Cliente reativado volta para um vendedor. O gestor acompanha pela Carteira.',
                'Na Cobrança, registre cada tentativa e a promessa de pagamento com data.',
            ]],
        ],
        'media' => [],
        'practice' => 'Um cliente da sua carteira apareceu em Cobrança. Você pode vender para ele?',
        'answer' => 'Pode, mas o boleto fica bloqueado: só Pix ou cartão até ele regularizar. Combine com o financeiro antes de oferecer prazo.',
    ],
    [
        'id' => 'meu-mes', 'title' => 'Meu mês e metas', 'short' => 'Meta, ritmo e resultado',
        'goal' => 'Acompanhar a sua meta e saber quanto precisa vender por dia.',
        'blocks' => [
            ['O que o Meu mês mostra', [
                ['Minha meta', 'Quanto você já faturou no mês e quanto falta.'],
                ['Ritmo', 'A projeção do mês pelo seu ritmo atual e quanto precisa por dia útil para bater a meta.'],
                ['Meta da equipe', 'Como a equipe toda está no mês.'],
            ]],
            ['Bom saber', [
                'O "vendido" é o faturado: o mesmo número do ERP.',
                'As metas por vendedor e por função são cadastradas pelo gestor em Gestão › Metas.',
            ]],
        ],
        'media' => [],
        'practice' => 'Você fechou um pedido grande hoje, mas a meta não mudou. Por quê?',
        'answer' => 'A meta conta o faturado. O pedido precisa ser aprovado pelo financeiro e faturado; aí entra no seu número.',
    ],
];

if (!Banco::valor("SELECT 1 FROM academia_conteudo WHERE chave = 'sistema_one'")) {
    Banco::inserir('academia_conteudo', ['chave' => 'sistema_one', 'conteudo_json' => json_encode($aulas, JSON_UNESCAPED_UNICODE)]);
}

// Trilha: o módulo da rotina no CRM passa a falar do One (só se ainda estiver com o texto do Leads2b)
$json = Banco::valor("SELECT conteudo_json FROM academia_conteudo WHERE chave = 'modules'");
if ($json && stripos($json, 'leads2b') !== false) {
    $modulos = json_decode($json, true) ?: [];
    foreach ($modulos as &$m) {
        if (($m['id'] ?? '') === 'crm') {
            $m['title'] = 'Rotina no One';
            $m['lead'] = 'Cada cliente deve ter histórico, responsável e próximo contato marcado.';
            $m['sections'] = [
                ['Comece pela agenda', [
                    'Abra Comercial › Agenda e confira os retornos atrasados e os de hoje.',
                    'Abra a ficha do cliente (clique no nome) e leia a linha do tempo antes de entrar em contato.',
                    'Não cadastre o cliente de novo porque não o achou num filtro: use a busca rápida pelo CNPJ ou telefone.',
                ]],
                ['Depois de cada contato', [
                    'Registre o que aconteceu, o que o cliente pediu, as condições conversadas e o próximo passo.',
                    'Ligação sem resposta: use "Registrar ligação" (Não atendeu, Caixa postal...). Tentativa não é conversa.',
                    'Marque o próximo contato com data. Ele aparece na sua Agenda.',
                    'Avance a etapa do funil só quando o critério da etapa foi cumprido.',
                ]],
            ];
            $m['practice'] = 'Abra a ficha de um cliente da sua carteira, leia a linha do tempo e registre um contato de treinamento com o próximo contato marcado. Não contate o cliente por engano.';
        }
    }
    unset($m);
    $novo = json_encode($modulos, JSON_UNESCAPED_UNICODE);
    $novo = str_replace(
        ['O Leads2b organiza o relacionamento, as negociações e os próximos contatos.', 'Agende o retorno no Leads2b com data e motivo.'],
        ['O One organiza o relacionamento, as negociações e os próximos contatos.', 'Marque o próximo contato no One com data e motivo.'],
        $novo
    );
    Banco::executar("UPDATE academia_conteudo SET conteudo_json = ? WHERE chave = 'modules'", [$novo]);
}
