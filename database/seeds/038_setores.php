<?php
/**
 * Setores de venda — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Televendas, Externo, Terceirização e Prospecção e Reativação viram setores separados:
 * cada um tem a sua carteira (clientes.funcao_id) e o seu grupo no menu lateral.
 * Só marca na primeira vez (depois o admin liga/desliga em Administração › Funções).
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'setores:padrao'")) {
    foreach (['televendas' => 'telefone', 'externo' => 'loja', 'terceirizacao' => 'etiqueta', 'prospeccao_reativacao' => 'lupa'] as $slug => $icone) {
        Banco::executar('UPDATE funcoes SET setor_venda = 1, icone = COALESCE(icone, ?) WHERE slug = ?', [$icone, $slug]);
    }
    Banco::executar("INSERT IGNORE INTO migracoes (arquivo) VALUES ('setores:padrao')");
}
return null;
