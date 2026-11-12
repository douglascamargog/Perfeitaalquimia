<?php
/**
 * Dados iniciais de ACESSO: perfis, permissões, funções e usuários de demonstração.
 *
 * Os usuários são FICTÍCIOS e identificados pela função, não por pessoas reais.
 * Senha de todos no protótipo: Demo@2026  (troque antes de usar com dados reais!)
 */
use App\Core\Banco;

// ---------- Perfis ----------
$perfis = [
    'admin'      => ['Admin', 'Tudo, incluindo configurações e usuários'],
    'gestor'     => ['Gestor', 'Todas as carteiras, relatórios e metas'],
    'vendedor'   => ['Vendedor', 'Só a própria carteira, catálogo, pedidos e Academia'],
    'financeiro' => ['Financeiro', 'Aprovação de pedidos, crédito, relatórios de vendas'],
    'marketing'  => ['Marketing', 'Leads de tráfego, Academia, conteúdo de produtos e catálogo'],
    'producao'   => ['Produção', 'Matérias-primas, fichas técnicas, ordens de produção, reabastecimento'],
];
$idPerfil = [];
foreach ($perfis as $slug => [$nome, $descricao]) {
    $idPerfil[$slug] = Banco::inserir('perfis', ['slug' => $slug, 'nome' => $nome, 'descricao' => $descricao]);
}

// ---------- Permissões por perfil ----------
Banco::inserir('perfil_permissoes', ['perfil_id' => $idPerfil['admin'], 'permissao' => '*']);
foreach (require RAIZ . '/app/permissoes.php' as $permissao => [, $perfisComAcesso]) {
    foreach ($perfisComAcesso as $slugPerfil) {
        Banco::inserir('perfil_permissoes', ['perfil_id' => $idPerfil[$slugPerfil], 'permissao' => $permissao]);
    }
}

// ---------- Funções comerciais (editáveis pelo admin) ----------
$funcoes = [
    'televendas'            => ['Televendas', 'Atendimento e vendas por telefone/WhatsApp'],
    'externo'               => ['Externo', 'Vendedor de rua, usa o app offline'],
    'prospeccao_reativacao' => ['Prospecção e Reativação', 'Recebe clientes sem compra e novos leads'],
    'terceirizacao'         => ['Terceirização e Marca Própria', 'Clientes de produção para terceiros'],
    'marketing'             => ['Marketing', 'Tráfego, conteúdo e Academia'],
    'producao'              => ['Produção', 'Fábrica e reabastecimento'],
];
$idFuncao = [];
$ordem = 0;
foreach ($funcoes as $slug => [$nome, $descricao]) {
    $idFuncao[$slug] = Banco::inserir('funcoes', ['slug' => $slug, 'nome' => $nome, 'descricao' => $descricao, 'ordem' => ++$ordem]);
}

// ---------- Usuários de demonstração (fictícios) ----------
$senha = password_hash('Demo@2026', PASSWORD_DEFAULT);
$usuarios = [
    // e-mail                      nome                          perfil        funções                      código
    ['admin@demo.local',          'Admin (demonstração)',         'admin',      [],                          null],
    ['gestor@demo.local',         'Gestor Comercial (demo)',      'gestor',     [],                          null],
    ['televendas1@demo.local',    'Televendas 1 (demo)',          'vendedor',   ['televendas'],              'TV01'],
    ['televendas2@demo.local',    'Televendas 2 (demo)',          'vendedor',   ['televendas'],              'TV02'],
    ['externo1@demo.local',       'Externo 1 (demo)',             'vendedor',   ['externo'],                 'EX01'],
    ['reativacao@demo.local',     'Prospecção e Reativação (demo)', 'vendedor', ['prospeccao_reativacao'],   'PR01'],
    ['terceirizacao@demo.local',  'Terceirização (demo)',         'vendedor',   ['terceirizacao'],           'TM01'],
    ['financeiro@demo.local',     'Financeiro (demo)',            'financeiro', [],                          null],
    ['marketing@demo.local',      'Marketing (demo)',             'marketing',  ['marketing'],               null],
    ['producao@demo.local',       'Produção (demo)',              'producao',   ['producao'],                null],
];
foreach ($usuarios as [$email, $nome, $perfil, $funcoesUsuario, $codigo]) {
    $id = Banco::inserir('usuarios', [
        'nome' => $nome, 'email' => $email, 'senha_hash' => $senha,
        'perfil_id' => $idPerfil[$perfil], 'codigo_vendedor' => $codigo,
    ]);
    foreach ($funcoesUsuario as $f) {
        Banco::inserir('usuario_funcoes', ['usuario_id' => $id, 'funcao_id' => $idFuncao[$f]]);
    }
}
