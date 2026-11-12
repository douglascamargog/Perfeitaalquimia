<?php
/**
 * Campanha em destaque na Carteira (padrão: Black Friday 2026).
 * Para destacar outra campanha no futuro: crie a origem em Configurações › Listas e troque os valores em Configurações.
 * Deixe "Origem da campanha" vazia para tirar o item do menu.
 */
use App\Core\Banco;

$configuracoes = [
    ['carteira.campanha_origem', 'Carteira Black Friday 2026', 'texto', 'Campanha', 'Origem da campanha em destaque', 'Nome exato da origem (Configurações › Listas). Vazio = sem campanha no menu', 30],
    ['carteira.campanha_titulo', 'Black Friday 2026', 'texto', 'Campanha', 'Nome da campanha no menu', '', 31],
    ['carteira.campanha_chamada', 'Prospecção em movimento', 'texto', 'Campanha', 'Frase da faixa da campanha', '', 32],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
