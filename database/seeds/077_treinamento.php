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

// ---------- Tutoriais das telas ----------
$ordem = 0;
foreach (require __DIR__ . '/dados/tutoriais.php' as [$rota, $titulo, $passos]) {
    $ordem++;
    $json = json_encode(array_map(fn($p) => ['alvo' => $p[0], 'texto' => $p[1]], $passos), JSON_UNESCAPED_UNICODE);
    $atual = Banco::um('SELECT id, editado_em FROM tutorial_telas WHERE rota = ?', [$rota]);
    if (!$atual) {
        Banco::inserir('tutorial_telas', ['rota' => $rota, 'titulo' => $titulo, 'passos_json' => $json, 'ordem' => $ordem]);
    } elseif (!$atual['editado_em']) {
        Banco::executar('UPDATE tutorial_telas SET titulo = ?, passos_json = ?, ordem = ? WHERE id = ?', [$titulo, $json, $ordem, $atual['id']]);
    }
}

// ---------- Trilhas, módulos e provas ----------
$trilhas = array_merge(
    require __DIR__ . '/dados/treinamento_integracao.php',
    require __DIR__ . '/dados/treinamento_comercial.php',
    require __DIR__ . '/dados/treinamento_operacao.php',
);
foreach ($trilhas as $n => $t) {
    $dados = ['funcao_slug' => $t['funcao'], 'titulo' => $t['titulo'], 'descricao' => $t['descricao'], 'icone' => $t['icone'], 'vizinhos' => $t['vizinhos'], 'ordem' => $n + 1];
    $trilhaId = (int) Banco::valor('SELECT id FROM trein_trilhas WHERE chave = ?', [$t['chave']]);
    if ($trilhaId) {
        Banco::atualizar('trein_trilhas', $trilhaId, $dados);
    } else {
        $trilhaId = Banco::inserir('trein_trilhas', ['chave' => $t['chave']] + $dados);
    }
    foreach ($t['modulos'] as $k => [$chave, $tipo, $titulo, $resumo, $minutos, $texto, $perguntas]) {
        $mod = Banco::um('SELECT id, editado_em FROM trein_modulos WHERE chave = ?', [$chave]);
        $linha = ['trilha_id' => $trilhaId, 'tipo' => $tipo, 'titulo' => $titulo, 'resumo' => $resumo, 'conteudo' => $texto, 'tempo_min' => $minutos, 'ordem' => $k + 1];
        if (!$mod) {
            $moduloId = Banco::inserir('trein_modulos', ['chave' => $chave] + $linha);
        } elseif (!$mod['editado_em']) {
            $moduloId = (int) $mod['id'];
            Banco::atualizar('trein_modulos', $moduloId, $linha);
        } else {
            continue; // editado pela tela: fica como está (texto e prova)
        }
        foreach ($perguntas as $i => [$pergunta, $opcoes, $correta, $explicacao]) {
            $p = ['modulo_id' => $moduloId, 'pergunta' => $pergunta, 'opcoes_json' => json_encode($opcoes, JSON_UNESCAPED_UNICODE), 'correta' => $correta, 'explicacao' => $explicacao, 'ordem' => $i + 1];
            $existe = (int) Banco::valor('SELECT id FROM trein_perguntas WHERE modulo_id = ? AND ordem = ?', [$moduloId, $i + 1]);
            $existe ? Banco::atualizar('trein_perguntas', $existe, $p) : Banco::inserir('trein_perguntas', $p);
        }
    }
}
return null;
