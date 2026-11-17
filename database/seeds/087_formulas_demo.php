<?php
/**
 * Fórmulas (V15.0) — DEMONSTRAÇÃO: duas fórmulas de exemplo para a apresentação (só na instalação nova).
 */
use App\Core\Banco;

$mp = fn(string $nome) => Banco::valor('SELECT id FROM materias_primas WHERE nome LIKE ? LIMIT 1', [$nome . '%']) ?: null;
$tec = (int) (Banco::valor("SELECT id FROM usuarios WHERE email = 'tecnica@demo.local'") ?: Banco::valor('SELECT MIN(id) FROM usuarios'));
$produto = Banco::valor("SELECT id FROM produtos WHERE nome LIKE '%Magn%sio%' ORDER BY id LIMIT 1") ?: null;
if (!$mp('Magnésio dimalato')) {
    return 'Fórmulas (demonstração): matérias-primas de exemplo não encontradas — fórmulas não criadas.';
}

$formulas = [
    [['codigo' => 'F-0001', 'nome' => 'Magnésio dimalato + Cúrcuma — cápsulas', 'forma' => 'capsula', 'porcao_qtd' => 2, 'porcao_unidade' => 'cápsulas', 'porcoes_embalagem' => 30,
        'capacidade_capsula_mg' => 500, 'lote_piloto' => 100, 'situacao' => 'em_teste', 'produto_id' => $produto,
        'objetivo' => 'Fonte de magnésio com cúrcuma. Público adulto. Conferir limites de magnésio por porção na norma vigente da Anvisa.',
        'observacoes' => "Misturar o magnésio e a cúrcuma por 10 min; adicionar o estearato no fim (2 min).\nEncapsular em cápsula vegetal nº 0."],
        [['Magnésio dimalato', null, 600, 'mg', 'porcao', 'ativo'], ['Cúrcuma em pó', null, 300, 'mg', 'porcao', 'ativo'],
         [null, 'Estearato de magnésio', 10, 'mg', 'porcao', 'excipiente'], ['Cápsula vegetal', null, 2, 'un', 'porcao', 'capsula'],
         ['Pote PET 150 ml', null, 1, 'un', 'embalagem', 'embalagem'], ['Lacre de indução', null, 1, 'un', 'embalagem', 'embalagem'], ['Rótulo adesivo', null, 1, 'un', 'embalagem', 'embalagem']]],
    [['codigo' => 'F-0002', 'nome' => 'Colágeno Verisol + Ácido hialurônico — pó', 'forma' => 'po', 'porcao_qtd' => 5, 'porcao_unidade' => 'g', 'porcoes_embalagem' => 30,
        'capacidade_capsula_mg' => null, 'lote_piloto' => 50, 'situacao' => 'rascunho', 'produto_id' => null,
        'objetivo' => 'Pó para diluir em água, sabor neutro. Avaliar aromatizante e edulcorante.', 'observacoes' => null],
        [['Colágeno Verisol', null, 2.5, 'g', 'porcao', 'ativo'], ['Ácido hialurônico', null, 50, 'mg', 'porcao', 'ativo'],
         [null, 'Maltodextrina', 2.3, 'g', 'porcao', 'veiculo'], [null, 'Aroma natural de frutas vermelhas', 150, 'mg', 'porcao', 'aromatizante']]],
];
foreach ($formulas as [$cab, $itens]) {
    if (Banco::valor('SELECT 1 FROM formulas WHERE codigo = ?', [$cab['codigo']])) {
        continue;
    }
    $id = Banco::inserir('formulas', $cab + ['versao' => 1, 'responsavel_id' => $tec, 'criado_por' => $tec]);
    foreach ($itens as $k => [$nome, $livre, $qtd, $un, $base, $funcao]) {
        Banco::inserir('formula_itens', ['formula_id' => $id, 'materia_prima_id' => $nome ? $mp($nome) : null, 'nome_livre' => $nome ? null : $livre,
            'quantidade' => $qtd, 'unidade' => $un, 'base' => $base, 'funcao' => $funcao, 'ordem' => $k + 1]);
    }
    Banco::inserir('formula_historico', ['formula_id' => $id, 'usuario_id' => $tec, 'texto' => 'Fórmula criada (' . $cab['codigo'] . ', versão 1).']);
    if ($cab['situacao'] === 'em_teste') {
        Banco::inserir('formula_testes', ['formula_id' => $id, 'data' => date('Y-m-d', strtotime('-3 days')), 'lote_tamanho' => 20, 'resultado' => 'ajustar',
            'observacoes' => 'Cápsula ficou com pó grudando na encapsuladora: aumentar o estearato para 1,5%.', 'criado_por' => $tec]);
    }
}
return 'Fórmulas (demonstração): 2 fórmulas de exemplo.';
