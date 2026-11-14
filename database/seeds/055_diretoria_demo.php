<?php
/**
 * DEMONSTRAÇÃO da Diretoria (só na instalação nova):
 *  - "Diretoria (demo)": só acompanha e aprova;
 *  - "Diretoria que vende (demo)": também vende (exceção "diretoria.vende" + permissões de vendedor) com 20 clientes da carteira.
 */
use App\Core\Banco;

$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'diretoria'");
if (!$perfil || Banco::valor("SELECT 1 FROM usuarios WHERE email = 'diretoria@demo.local'")) {
    return null;
}
$funcao = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'diretoria'");
$criar = function (string $email, string $nome) use ($perfil, $funcao): int {
    $id = Banco::inserir('usuarios', ['nome' => $nome, 'email' => $email, 'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil]);
    if ($funcao) {
        Banco::executar('INSERT IGNORE INTO usuario_funcoes (usuario_id, funcao_id) VALUES (?, ?)', [$id, $funcao]);
    }
    return $id;
};
$criar('diretoria@demo.local', 'Diretoria (demo)');
$vende = $criar('diretoria.vende@demo.local', 'Diretoria que vende (demo)');
foreach (['diretoria.vende', 'carteira.ver_propria', 'pedidos.criar', 'meu_mes.usar', 'mensagens.usar', 'catalogo.acessos', 'app.usar'] as $perm) {
    Banco::executar('INSERT IGNORE INTO usuario_permissoes (usuario_id, permissao, concedida) VALUES (?, ?, 1)', [$vende, $perm]);
}
$tele = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas2@demo.local'");
Banco::executar('UPDATE clientes SET responsavel_id = ? WHERE responsavel_id = ? ORDER BY id LIMIT 20', [$vende, $tele]);
return 'Diretoria (demonstração): diretoria@demo.local e diretoria.vende@demo.local (com 20 clientes).';
