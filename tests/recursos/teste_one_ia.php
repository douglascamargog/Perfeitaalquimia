<?php
// Parte "com IA" do teste do Pergunte ao One, contra a API SIMULADA (tests/recursos/anthropic_simulada.php na porta 8099).
// Confere: o vaivém das consultas pelo SDK, o formato enviado (reserva por recusa, esforço, esquemas) e as permissões.
require __DIR__ . '/../../app/bootstrap.php';
use App\Core\Banco; use App\Core\Config; use App\Services\OneFerramentas as F;
$ok = fn($c, $m) => print(($c ? 'OK   ' : 'FALHA') . " $m\n");
$tv = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas1@demo.local'");
$_SESSION['usuario_id'] = $tv; $_SESSION['ultimo_uso'] = time();
@unlink('/tmp/anthropic_simulada.log');
$r = (new App\Integrations\OneIA\OneIAClaude('chave-de-teste', 'claude-opus-5-5', 'http://127.0.0.1:8099'))->responder('Quanto eu vendi este mês?', []);
$ok(str_contains($r['texto'], 'Você já vendeu') && $r['consultas'] === ['meus_numeros'] && $r['custo'] > 0, 'com IA: pede a consulta, recebe os dados da pessoa e responde (custo estimado ' . $r['custo'] . ')');
$pedidos = array_map(fn($l) => json_decode($l, true), file('/tmp/anthropic_simulada.log'));
$c = $pedidos[1]['corpo'] ?? [];
$ok(($c['fallbacks'] ?? '') === 'default' && str_contains($pedidos[0]['beta'], 'server-side-fallback') && ($c['output_config']['effort'] ?? '') === 'low', 'pedido à IA: reserva automática se recusar, esforço baixo');
$ok((end($c['messages'])['content'][0]['tool_use_id'] ?? '') === 'toolu_1', 'resultado da consulta volta ligado ao pedido dela');
$esquemas = array_column($c['tools'] ?? [], 'input_schema');
$ok($esquemas && !array_filter($esquemas, fn($e) => array_key_exists('properties', $e) && array_is_list($e['properties'])), 'esquemas das consultas em formato de objeto (nada de lista vazia)');
$ok(!in_array('buscar_clientes', array_column($c['tools'], 'name'), true), 'LGPD desligado (padrão): a IA nem recebe as consultas de clientes');
$ok(isset(F::executar('buscar_clientes', [])['erro']), 'e se pedir mesmo assim, a consulta é recusada');
Banco::executar("UPDATE configuracoes SET valor = '1' WHERE chave = 'one.ia_dados_clientes'"); Config::recarregar();
$b = F::executar('buscar_clientes', ['texto' => '', 'cidade' => '', 'uf' => '', 'sem_compra_dias' => 0, 'comprou_produto' => '']);
$ids = array_column($b['clientes'], 'id');
$ok($ids && !Banco::valor('SELECT COUNT(*) FROM clientes WHERE id IN (' . implode(',', $ids) . ') AND (responsavel_id <> ? OR responsavel_id IS NULL)', [$tv]), 'LGPD ligado: a vendedora só consulta a própria carteira');
$outro = (int) Banco::valor("SELECT id FROM clientes WHERE responsavel_id <> ? AND tipo = 'cliente' LIMIT 1", [$tv]);
$ok(isset(F::executar('cliente_resumo', ['cliente_id' => $outro])['erro']), 'cliente de outra vendedora: recusado');
$json = json_encode(F::executar('cliente_resumo', ['cliente_id' => $ids[0]]));
$cnpj = (string) Banco::valor('SELECT cnpj FROM clientes WHERE id = ?', [$ids[0]]);
$ok(!str_contains($json, $cnpj), 'resumo do cliente não leva CNPJ nem telefone para a IA');
$ok(isset(F::executar('apagar_tudo', [])['erro']), 'consulta inventada: recusada');
Banco::executar("UPDATE configuracoes SET valor = '0' WHERE chave = 'one.ia_dados_clientes'");
// "Testar a IA agora" (Administração)
[$certo, $msg] = (new App\Integrations\OneIA\OneIAClaude('chave-de-teste', 'claude-opus-5-5', 'http://127.0.0.1:8099'))->testar();
$ok($certo && str_contains($msg, 'Funcionando'), 'botão "Testar a IA agora": avisa que está funcionando');
[$certo, $msg] = (new App\Integrations\OneIA\OneIAClaude('', 'claude-opus-5-5'))->testar();
$ok(!$certo && str_contains($msg, 'Falta a chave'), 'sem chave: explica onde colocar');
[$certo, $msg] = (new App\Integrations\OneIA\OneIAClaude('x', 'claude-opus-5-5', 'http://127.0.0.1:8098'))->testar();
$ok(!$certo && str_contains($msg, 'api.anthropic.com'), 'sem conexão: explica o que pedir à hospedagem');
