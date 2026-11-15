<?php
/**
 * Pergunte ao One (V12.4) — ESSENCIAL (idempotente). A IA começa DESLIGADA: o admin liga em Configurações › One (IA).
 * Desligada, o One responde as dúvidas sobre o sistema com as aulas "Como usar o One" (nada sai do servidor).
 */
use App\Core\Banco;

$configs = [
    ['one.ia_ativa', '0', 'booleano', 'One (IA)', 'Pergunte ao One com IA (Claude)',
        'Ligado: entende qualquer pergunta e consulta o sistema (custa por uso; chave da Anthropic no config.php). Desligado: responde dúvidas do sistema, sem IA.', 10],
    ['one.ia_dados_clientes', '0', 'booleano', 'One (IA)', 'A IA pode consultar dados de clientes',
        'LGPD: ligado, nomes, cidades e compras de clientes vão para a Anthropic. Cada pessoa só consulta a própria carteira. Desligado: só sistema, produtos, campanhas e números.', 20],
    ['one.ia_limite_hora', '20', 'numero', 'One (IA)', 'Perguntas por pessoa por hora',
        'Limite de perguntas com IA por pessoa a cada hora (controla o custo).', 30],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
