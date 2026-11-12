<?php
/**
 * Tela Início — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Cidade da previsão do tempo. Para trocar: Configurações › Início (latitude/longitude no Google Maps:
 * clique com o botão direito no lugar e copie os números). Latitude vazia = esconde a previsão.
 */
use App\Core\Banco;

$configuracoes = [
    ['inicio.clima_cidade', 'Porto Alegre', 'texto', 'Início', 'Cidade da previsão do tempo', 'Nome que aparece no card do tempo', 80],
    ['inicio.clima_latitude', '-30.0346', 'texto', 'Início', 'Latitude da cidade', 'Ex.: -30.0346 (Porto Alegre). Vazio = sem previsão do tempo', 81],
    ['inicio.clima_longitude', '-51.2177', 'texto', 'Início', 'Longitude da cidade', 'Ex.: -51.2177 (Porto Alegre)', 82],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
