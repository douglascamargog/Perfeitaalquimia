<?php
/** Comunicados (DEMONSTRAÇÃO): um para toda a empresa e um importante só para Televendas e Externo. */
use App\Core\Banco;
use App\Services\ComunicadoService;

$admin = (int) Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'admin' ORDER BY u.id LIMIT 1");
$funcoes = array_column(Banco::todos("SELECT id FROM funcoes WHERE slug IN ('televendas', 'externo')"), 'id');
ComunicadoService::emitir(['titulo' => 'Bem-vindos ao One', 'texto' => "A partir de agora todos os pedidos, contatos e metas ficam no One.\nQualquer dúvida, veja a Academia › Como usar o One.",
    'prioridade' => 'normal', 'toda_empresa' => 1], $admin, false);
if ($funcoes) {
    ComunicadoService::emitir(['titulo' => 'Campanha de novembro: frete grátis acima de R$ 1.500', 'texto' => "Até o fim do mês, pedidos acima de R\$ 1.500,00 têm frete grátis para todo o Sul.\nAproveitem para ligar para os clientes parados.",
        'prioridade' => 'importante', 'funcoes' => $funcoes, 'fim' => date('Y-m-t')], $admin, false);
}
return 'Comunicados (demonstração): 2.';
