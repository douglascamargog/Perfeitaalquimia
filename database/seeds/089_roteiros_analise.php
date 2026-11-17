<?php
/**
 * Planejador de visitas e Análise de clientes (V15.2) — DADOS ESSENCIAIS (idempotente):
 *  - clientes.analise: Gestor e Diretoria (Gestão › Análise de clientes);
 *  - roteiros.gerenciar: Gestor (vê os roteiros de todos). Quem é da função Externo já monta os próprios roteiros.
 *  - configurações do planejador (raio e visitas por dia padrão).
 * (Admin tem tudo. Ajustes depois em Administração › Acessos e Configurações.)
 */
use App\Core\Banco;

$porPerfil = [
    'gestor' => ['clientes.analise', 'roteiros.gerenciar'],
    'diretoria' => ['clientes.analise', 'roteiros.gerenciar'],
];
if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'perm:roteiros_analise'")) {
    foreach ($porPerfil as $perfil => $perms) {
        foreach ($perms as $perm) {
            Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$perm, $perfil]);
        }
    }
    Banco::inserir('migracoes', ['arquivo' => 'perm:roteiros_analise']);
}

$configs = [
    ['roteiros.raio_km', 'Distância padrão da rota (km)', '15', 'numero', 'App do externo', 'Planejador de visitas: até quantos km do caminho o assistente procura lojas'],
    ['roteiros.visitas_dia', 'Visitas por dia (padrão)', '8', 'numero', 'App do externo', 'Planejador de visitas: usado para dividir as paradas nos dias da viagem'],
];
foreach ($configs as [$chave, $rotulo, $valor, $tipo, $grupo, $ajuda]) {
    Banco::executar('INSERT IGNORE INTO configuracoes (chave, rotulo, valor, tipo, grupo, descricao) VALUES (?, ?, ?, ?, ?, ?)', [$chave, $rotulo, $valor, $tipo, $grupo, $ajuda]);
}
// Passo a passo das telas novas (Análise de clientes e Planejador de visitas) e a dica da média mensal na ficha
require __DIR__ . '/dados/carregar_treinamento.php';
return null;
