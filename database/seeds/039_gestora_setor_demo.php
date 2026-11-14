<?php
/**
 * DEMONSTRAÇÃO do(a) gestor(a) de setor (só na instalação nova; "_demo" no nome = nunca roda em atualização):
 * "Gestora Televendas (demo)" coordena o setor Televendas E também vende — tem a própria carteira (20 clientes
 * fictícios vindos da fila de reativação) e vê a carteira de toda a equipe do Televendas.
 */
use App\Core\Banco;

$funcao = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'televendas'");
$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'vendedor'");
if (!$funcao || !$perfil || Banco::valor("SELECT 1 FROM usuarios WHERE email = 'gestora.televendas@demo.local'")) {
    return null;
}
$id = Banco::inserir('usuarios', ['nome' => 'Gestora Televendas (demo)', 'email' => 'gestora.televendas@demo.local',
    'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil, 'codigo_vendedor' => 'TV00']);
Banco::inserir('usuario_funcoes', ['usuario_id' => $id, 'funcao_id' => $funcao, 'gestor' => 1]);
$reativacao = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'prospeccao_reativacao'");
$n = Banco::executar("UPDATE clientes SET responsavel_id = ?, funcao_id = ? WHERE funcao_id = ? AND tipo = 'cliente' AND excluido_em IS NULL
    ORDER BY id DESC LIMIT 20", [$id, $funcao, $reativacao]);
return "Gestora do Televendas (demonstração): coordena o setor e tem $n clientes próprios.";
