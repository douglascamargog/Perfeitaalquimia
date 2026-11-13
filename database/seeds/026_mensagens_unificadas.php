<?php
/**
 * Mensagens num lugar só — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Os textos que ficavam em Configurações (Recompra e Cobrança) viram modelos em Conteúdo › Mensagens prontas.
 * As telas Recompra e Cobrança passam a usar esses modelos. As configurações antigas continuam no banco
 * (nada é apagado), mas saem da tela de Configurações.
 */
use App\Core\Banco;

$criados = 0;
$migrar = [
    // [chave antiga, categoria, título, trocas de variável (o {dias} antigo vira o nome novo)]
    ['recompra.mensagem', 'Recompra', 'Lembrete de recompra', ['{dias}' => '{dias_sem_compra}', '{link_catalogo}' => '{link_vendedor}']],
    ['cobranca.msg_lembrete', 'Cobrança', 'Lembrete gentil', ['{dias}' => '{dias_atraso}']],
    ['cobranca.msg_segunda_via', 'Cobrança', 'Segunda via', ['{dias}' => '{dias_atraso}']],
    ['cobranca.msg_acordo', 'Cobrança', 'Proposta de acordo', ['{dias}' => '{dias_atraso}']],
];
foreach ($migrar as $ordem => [$chave, $categoria, $titulo, $trocas]) {
    $texto = trim((string) Banco::valor('SELECT valor FROM configuracoes WHERE chave = ?', [$chave]));
    if ($texto === '' || Banco::valor('SELECT 1 FROM mensagens_prontas WHERE categoria = ? AND titulo = ?', [$categoria, $titulo])) {
        continue;
    }
    // Ordem negativa: o texto que a equipe já usava continua sendo o primeiro da categoria
    Banco::inserir('mensagens_prontas', ['titulo' => $titulo, 'categoria' => $categoria, 'texto' => strtr($texto, $trocas), 'ordem' => -10 + $ordem]);
    $criados++;
}
return $criados ? "$criados texto(s) das Configurações viraram Mensagens prontas." : null;
