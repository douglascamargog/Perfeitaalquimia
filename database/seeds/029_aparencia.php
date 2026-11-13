<?php
/**
 * Aparência (essencial, roda também na atualização):
 *  - cores padrão da empresa (Configurações › Aparência): verde-petróleo e menta da Perfeita Alquimia;
 *  - lição "Suas cores e quem está online" na Academia › Como usar o One.
 * Idempotente: só cria o que não existe.
 */
use App\Core\Banco;

$configuracoes = [
    ['visual.cor_primaria', '#025150', 'cor', 'Aparência', 'Cor primária da empresa', 'Menu, topo, botões e títulos. Cada pessoa pode trocar a dela em Meu perfil › Aparência.', 10],
    ['visual.cor_secundaria', '#5cd4b6', 'cor', 'Aparência', 'Cor secundária da empresa', 'Destaques: item ativo do menu, selos e números. Também vale para o catálogo do cliente.', 20],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}

// Lição nova na Academia › Como usar o One (só se ainda não existe)
$conteudo = Banco::valor("SELECT conteudo_json FROM academia_conteudo WHERE chave = 'sistema_one'");
if ($conteudo !== null && $conteudo !== false) {
    $aulas = json_decode((string) $conteudo, true) ?: [];
    if (!in_array('aparencia-online', array_column($aulas, 'id'), true)) {
        $aulas[] = [
            'id' => 'aparencia-online', 'title' => 'Suas cores e quem está online', 'short' => 'Aparência do sistema e a equipe ao vivo',
            'goal' => 'Deixar o One com as cores que você prefere e ver quem da equipe está usando o sistema agora.',
            'blocks' => [
                ['Suas cores', [
                    'Clique no seu nome, no alto da tela, para abrir o Meu perfil.',
                    'Em "Aparência", escolha uma das combinações prontas ou monte a sua com a cor primária (menu e botões) e a secundária (destaques).',
                    'A tela muda na hora, para você ver antes de salvar. As cores valem só para você; o catálogo do cliente continua com as cores da empresa.',
                    'Não gostou? Clique em "Voltar ao padrão da empresa".',
                ]],
                ['Quem está online', [
                    ['Fotinhas no topo', 'Mostram quem da equipe está usando o One agora. Passe o mouse ou clique para ver o nome e em que aba a pessoa está.'],
                    ['Privacidade', 'Aparece só o nome da aba (ex.: Carteira, Pedidos). Nunca qual cliente a pessoa está vendo.'],
                ]],
            ],
            'media' => [],
            'practice' => 'Você trocou as cores do seu One. O cliente que abre o catálogo vai ver as suas cores?',
            'answer' => 'Não. As suas cores valem só para você. O catálogo do cliente sempre usa as cores da empresa.',
        ];
        Banco::executar("UPDATE academia_conteudo SET conteudo_json = ? WHERE chave = 'sistema_one'", [json_encode($aulas, JSON_UNESCAPED_UNICODE)]);
    }
}
return null;
