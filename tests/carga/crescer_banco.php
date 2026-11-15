<?php
// Aumenta o banco de demonstração: ~20 mil clientes, ~150 mil eventos, ~15 mil pedidos (só para teste de carga)
require 'app/bootstrap.php';
use App\Core\Banco;
$cols = fn(string $t, array $fora) => array_values(array_diff(array_column(Banco::todos("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION", [$t]), 'COLUMN_NAME'), $fora));
$cc = $cols('clientes', ['id', 'cnpj', 'omie_codigo']);
$lista = implode(',', array_map(fn($c) => "`$c`", $cc));
$volta = 0;
while ((int) Banco::valor('SELECT COUNT(*) FROM clientes') < 20000) {
    $volta++;
    Banco::executar("INSERT INTO clientes ($lista, cnpj) SELECT $lista, CONCAT('9', LPAD($volta, 3, '0'), LPAD(id, 10, '0')) FROM clientes WHERE tipo <> 'consumidor' AND cnpj NOT LIKE '9%' LIMIT 4000");
}
$ids = 'SELECT id FROM clientes';
$ec = $cols('cliente_eventos', ['id', 'cliente_id']);
$le = implode(',', array_map(fn($c) => "e.`$c`", $ec)); $le2 = implode(',', array_map(fn($c) => "`$c`", $ec));
while ((int) Banco::valor('SELECT COUNT(*) FROM cliente_eventos') < 150000) {
    Banco::executar("INSERT INTO cliente_eventos (cliente_id, $le2) SELECT c.id, $le FROM (SELECT * FROM cliente_eventos ORDER BY RAND() LIMIT 100) e CROSS JOIN (SELECT id FROM clientes ORDER BY RAND() LIMIT 200) c");
}
$pc = $cols('pedidos', ['id', 'uuid', 'omie_codigo', 'omie_numero', 'cliente_id']);
$lp = implode(',', array_map(fn($c) => "p.`$c`", $pc)); $lp2 = implode(',', array_map(fn($c) => "`$c`", $pc));
while ((int) Banco::valor('SELECT COUNT(*) FROM pedidos') < 15000) {
    Banco::executar("INSERT INTO pedidos (uuid, cliente_id, $lp2) SELECT UUID(), c.id, $lp FROM (SELECT * FROM pedidos WHERE tipo_venda <> 'ecommerce' OR tipo_venda IS NULL ORDER BY RAND() LIMIT 30) p CROSS JOIN (SELECT id FROM clientes ORDER BY RAND() LIMIT 100) c");
}
Banco::executar("UPDATE clientes c SET responsavel_id = (SELECT u.id FROM usuarios u WHERE u.ativo = 1 AND u.email LIKE 'carga%' ORDER BY RAND() LIMIT 1) WHERE c.id % 3 = 0 AND c.responsavel_id IS NOT NULL");
foreach (['clientes', 'cliente_eventos', 'pedidos', 'pedido_itens', 'tarefas'] as $t) { echo "$t: " . Banco::valor("SELECT COUNT(*) FROM $t") . "\n"; }
