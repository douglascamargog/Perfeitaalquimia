<?php
/**
 * V15.5 — DADOS ESSENCIAIS (idempotente): qual serviço de mapas o One usa.
 * Geoapify (grátis, sem pré-pagamento) é o padrão; quem já tinha a chave do Google cadastrada continua no Google
 * até trocar em ⚙️ Administração › Mapas.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'mapas.provedor'")) {
    Banco::inserir('configuracoes', ['chave' => 'mapas.provedor', 'rotulo' => 'Serviço de mapas (geoapify ou google)',
        'valor' => (string) config('integracoes.google.maps_chave', '') !== '' ? 'google' : 'geoapify', 'tipo' => 'texto', 'grupo' => 'Carteira',
        'descricao' => 'Localiza as lojas no mapa e calcula as rotas do Planejador de visitas. Troque em Administração › Mapas.']);
}
return null;
