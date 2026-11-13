<?php
/**
 * DEMONSTRAÇÃO das notificações: alguns avisos recentes para os logins de exemplo
 * (os pedidos para aprovar do Financeiro já foram avisados quando os pedidos de exemplo foram criados).
 */
use App\Core\Banco;
use App\Services\NotificacaoService as N;

$n = 0;
$vend = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas1@demo.local'");
$ultimo = Banco::um("SELECT id, cliente_id FROM pedidos WHERE vendedor_id = ? AND situacao = 'faturado' ORDER BY faturado_em DESC LIMIT 1", [$vend]);
if ($ultimo) {
    N::pedidoMudou((int) $ultimo['id'], 'faturado', null, 'NF de demonstração');
    $n++;
}
foreach (Banco::todos("SELECT cliente_id FROM leads_recebidos WHERE resultado = 'criado' ORDER BY id DESC LIMIT 2") as $l) {
    N::leadRecebido((int) $l['cliente_id'], true);
    $n++;
}
return "Notificações (demonstração): $n aviso(s) além dos pedidos para aprovar.";
