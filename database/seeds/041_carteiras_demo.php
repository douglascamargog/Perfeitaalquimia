<?php
/**
 * DEMONSTRAÇÃO das Carteiras (só na instalação nova): uma carteira antiga do Televendas SEM vendedora
 * (a pessoa saiu), com clientes fictícios parados esperando alguém assumir.
 */
use App\Core\Banco;
use App\Services\CarteirasService;

$tv = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'televendas'");
$reat = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'prospeccao_reativacao'");
if (!$tv || Banco::valor("SELECT 1 FROM carteiras WHERE nome = 'Televendas · Carteira antiga (demo)'")) {
    return null;
}
$k = Banco::inserir('carteiras', ['nome' => 'Televendas · Carteira antiga (demo)', 'funcao_id' => $tv, 'responsavel_id' => null,
    'descricao' => 'Era de uma vendedora que saiu (demonstração)']);
$ids = array_column(Banco::todos("SELECT id FROM clientes WHERE funcao_id = ? AND excluido_em IS NULL AND uf <> 'RS' ORDER BY tipo = 'cliente' DESC, id LIMIT 25", [$reat]), 'id');
$n = CarteirasService::colocarNaCarteira($ids, $k, null, 'Carteira de vendedora que saiu (demonstração)');
return "Carteiras (demonstração): uma carteira do Televendas sem vendedora, com $n clientes.";
