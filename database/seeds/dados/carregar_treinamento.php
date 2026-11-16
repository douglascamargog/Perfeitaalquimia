<?php
/**
 * Carrega no banco o conteúdo do Treinamento e o passo a passo das telas (usado pelos seeds 077 e 078, e pelos próximos
 * que trouxerem conteúdo novo). Idempotente: entram os novos e são atualizados os que ninguém editou pela tela.
 */
use App\Core\Banco;

// ---------- Tutoriais das telas ----------
$ordem = 0;
foreach (require __DIR__ . '/tutoriais.php' as [$rota, $titulo, $passos]) {
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
    require __DIR__ . '/treinamento_integracao.php',
    require __DIR__ . '/treinamento_comercial.php',
    require __DIR__ . '/treinamento_produtos.php',
    require __DIR__ . '/treinamento_gestao.php',
    require __DIR__ . '/treinamento_operacao.php',
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
        // a prova nova tem menos perguntas que a antiga: as que sobraram saem (módulo não editado pela tela)
        Banco::executar('DELETE FROM trein_perguntas WHERE modulo_id = ? AND ordem > ?', [$moduloId, count($perguntas)]);
    }
}
