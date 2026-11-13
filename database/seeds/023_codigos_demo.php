<?php
/**
 * DEMONSTRAÇÃO: códigos fictícios dos produtos (PA-1001, PA-1002...), na ordem das categorias.
 * Só na instalação nova ("_demo"). No servidor real o código vem do Omie (Fase 2) ou é cadastrado em Conteúdo › Produtos.
 */
use App\Core\Banco;

$n = 0;
foreach (Banco::todos("SELECT p.id FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id WHERE p.codigo IS NULL OR p.codigo = ''
        ORDER BY c.ordem, c.nome, p.nome") as $i => ['id' => $id]) {
    $n += Banco::executar('UPDATE produtos SET codigo = ? WHERE id = ?', ['PA-' . (1001 + $i), $id]);
}
return "Códigos de produto (demonstração): $n produtos com código PA-1001 em diante.";
