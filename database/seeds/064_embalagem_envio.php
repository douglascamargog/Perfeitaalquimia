<?php
/**
 * Depósito (V11.2) — DADOS ESSENCIAIS (idempotente): quais itens aparecem como "caixa / embalagem usada" na separação
 * e se é obrigatório informar a caixa para concluir.
 */
use App\Core\Banco;

$configs = [
    ['deposito.embalagem_categorias', 'Embalagem de envio', 'lista', 'Depósito', 'Categorias das caixas e embalagens de envio',
        'Os itens dessas categorias (Compras da empresa › Itens) aparecem na separação para anotar a caixa usada. Separadas por vírgula.', 40],
    ['deposito.embalagem_obrigatoria', '1', 'booleano', 'Depósito', 'Exigir a caixa usada para concluir a separação',
        'Ligado: só conclui a separação depois de informar a caixa (ou embalagem) usada. A baixa no estoque é automática.', 41],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
