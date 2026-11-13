<?php
/**
 * Previsão do tempo do Início: São Leopoldo (pedido da empresa) — ESSENCIAL, roda também na atualização.
 * Só troca se ainda estiver no padrão antigo (Porto Alegre); se o admin já escolheu outra cidade, não mexe.
 * A cidade continua configurável em Configurações › Início.
 */
use App\Core\Banco;

$antigo = Banco::valor("SELECT valor FROM configuracoes WHERE chave = 'inicio.clima_cidade'");
if ($antigo === 'Porto Alegre') {
    Banco::executar("UPDATE configuracoes SET valor = 'São Leopoldo' WHERE chave = 'inicio.clima_cidade'");
    Banco::executar("UPDATE configuracoes SET valor = '-29.7604' WHERE chave = 'inicio.clima_latitude' AND valor = '-30.0346'");
    Banco::executar("UPDATE configuracoes SET valor = '-51.1497' WHERE chave = 'inicio.clima_longitude' AND valor = '-51.2177'");
    return 'Previsão do tempo do Início: São Leopoldo.';
}
return null;
