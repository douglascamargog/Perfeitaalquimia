<?php
// Cria vendedoras extras (clones de televendas1/externo1) até completar 30 usuários ativos, para o teste de carga
require 'app/bootstrap.php';
use App\Core\Banco;
$total = (int) Banco::valor('SELECT COUNT(*) FROM usuarios WHERE ativo = 1');
$n = 0;
while ($total < 30) {
    $modelo = $n % 3 === 2 ? 'externo1@demo.local' : 'televendas1@demo.local';
    $u = Banco::um('SELECT * FROM usuarios WHERE email = ?', [$modelo]);
    $id = $u['id']; unset($u['id']);
    $u['email'] = 'carga' . ($n + 1) . '@demo.local'; $u['nome'] = 'Carga ' . ($n + 1); $u['apelido'] = null;
    foreach (['codigo_vendedor', 'liguetalk_usuario', 'codigo'] as $c) { if (array_key_exists($c, $u)) { $u[$c] = null; } }
    $novo = Banco::inserir('usuarios', $u);
    Banco::executar('INSERT INTO usuario_funcoes (usuario_id, funcao_id, gestor) SELECT ?, funcao_id, 0 FROM usuario_funcoes WHERE usuario_id = ?', [$novo, $id]);
    // reparte alguns clientes do modelo para a nova vendedora ter carteira
    Banco::executar('UPDATE clientes SET responsavel_id = ? WHERE responsavel_id = ? AND MOD(id, 4) = ?', [$novo, $id, $n % 4]);
    $n++; $total++;
}
Banco::executar("UPDATE usuarios SET versao_vista = '99', trocar_senha = 0");
echo "$n usuários criados; total ativos: $total\n";
