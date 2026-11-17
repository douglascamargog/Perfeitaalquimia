<?php
/**
 * E-commerce (V14.1) — ESSENCIAL (idempotente):
 *  - "Pedir ao Marketing": tira os assuntos "Pergunta de cliente no marketplace ou site" e "Cadastrar produto na loja
 *    ou nos marketplaces" (ficam inativos; os pedidos antigos continuam com o assunto). Dá para reativar em Configurações › Listas.
 *  - Pedidos da Tray: relê o cabeçalho de todos (confirmação de pagamento, igual à "Performance da loja" da Tray),
 *    sem ler de novo o pedido completo.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'eco:v14.1'")) {
    Banco::executar("UPDATE mkt_tipos SET ativo = 0 WHERE nome IN ('Pergunta de cliente no marketplace ou site', 'Cadastrar produto na loja ou nos marketplaces')");
    Banco::executar("UPDATE ecommerce_pedidos SET modificado_em = NULL WHERE origem = 'tray'");
    Banco::executar("UPDATE configuracoes SET valor = '' WHERE chave = 'ecommerce.sinc_ate'");
    Banco::inserir('migracoes', ['arquivo' => 'eco:v14.1']);
}
return null;
