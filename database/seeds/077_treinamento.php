<?php
/**
 * Treinamento por setor + tutorial do One em cada tela (V13.0) — ESSENCIAL (instalação e atualizações; idempotente).
 *  - trilhas, módulos e provas (dados/treinamento_*.php) e o passo a passo das telas (dados/tutoriais.php):
 *    entram os novos e são atualizados os que ninguém editou pela tela (editado_em vazio);
 *  - permissão "treinamento.equipe" para Gestor de setor e Diretoria;
 *  - configuração da nota mínima da prova.
 * Usuários que já existem NÃO recebem o tutorial automático (tutorial_ativo = 0); quem for criado depois recebe.
 */
use App\Core\Banco;

Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'treinamento.equipe' FROM perfis WHERE slug IN ('gestor', 'gestor_setor', 'diretoria')");

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'treinamento.nota_minima'")) {
    Banco::inserir('configuracoes', ['chave' => 'treinamento.nota_minima', 'valor' => '70', 'tipo' => 'numero', 'grupo' => 'Treinamento',
        'rotulo' => 'Nota mínima da prova (%)', 'descricao' => 'Para concluir um módulo do Treinamento, a pessoa precisa acertar pelo menos esta porcentagem da prova.', 'ordem' => 10]);
}

// ---------- Conteúdo (trilhas, módulos, provas) e tutoriais das telas ----------
require __DIR__ . '/dados/carregar_treinamento.php';
return null;
