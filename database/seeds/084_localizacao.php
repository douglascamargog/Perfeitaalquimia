<?php
/**
 * Localização exata das lojas (V14.2) — DADOS ESSENCIAIS (idempotente): quantas lojas o cron localiza por rodada.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'carteira.geo_por_rodada'")) {
    Banco::inserir('configuracoes', ['chave' => 'carteira.geo_por_rodada', 'valor' => '200', 'tipo' => 'numero', 'grupo' => 'Carteira',
        'rotulo' => 'Mapa: lojas localizadas por rodada (Google Maps)',
        'descricao' => 'O cron localiza esse tanto de lojas de cada vez (de hora em hora). Cada loja gasta 1 consulta da cota do Google.', 'ordem' => 90]);
}
return null;
