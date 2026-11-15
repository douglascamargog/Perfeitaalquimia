<?php
/**
 * OP no modelo da empresa (V11.5) — DADOS ESSENCIAIS (idempotente): dados do documento impresso e
 * se o lote da matéria-prima é obrigatório na entrada.
 */
use App\Core\Banco;

$configs = [
    ['op.codigo_documento', 'PLA08.1', 'texto', 'Código do documento da ordem de produção', 'Aparece no canto da OP impressa (Cód.).', 60],
    ['op.elaboracao', '07/2023', 'texto', 'Elaboração do documento da OP', 'Mês/ano da elaboração do modelo (aparece na OP impressa).', 61],
    ['op.revisao', '', 'texto', 'Revisão do documento da OP', 'Número da revisão do modelo (aparece na OP impressa).', 62],
    ['producao.lote_mp_obrigatorio', '1', 'booleano', 'Exigir o lote na entrada de matéria-prima', 'Ligado: a entrada de matéria-prima pede lote e validade (embalagens e itens de consumo não pedem).', 63],
];
foreach ($configs as [$chave, $valor, $tipo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', ['chave' => $chave, 'valor' => $valor, 'tipo' => $tipo, 'grupo' => 'Produção', 'rotulo' => $rotulo, 'descricao' => $descricao, 'ordem' => $ordem]);
    }
}
return null;
