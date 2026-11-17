<?php
// Parte "com IA" do teste dos Especialistas do One (V15.0), contra a API SIMULADA (tests/recursos/anthropic_simulada.php na porta 8099).
// Confere: as instruções do especialista vão para a IA, ele só recebe as consultas dele e consulta fora da lista é recusada.
require __DIR__ . '/../../app/bootstrap.php';
use App\Core\Banco;
$ok = fn($c, $m) => print(($c ? 'OK   ' : 'FALHA') . " $m\n");
$tec = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'tecnica@demo.local'");
$_SESSION['usuario_id'] = $tec; $_SESSION['ultimo_uso'] = time();
@unlink('/tmp/anthropic_simulada.log');
$esp = App\Services\OneEspecialistas::escolher('formulacao');
$r = (new App\Integrations\OneIA\OneIAClaude('chave-de-teste', 'claude-opus-5-5', 'http://127.0.0.1:8099'))->responder('Quanto custa a fórmula?', [], $esp);
$pedidos = array_map(fn($l) => json_decode($l, true), file('/tmp/anthropic_simulada.log'));
$c = $pedidos[0]['corpo'] ?? [];
$sistema = is_array($c['system'] ?? null) ? implode(' ', array_column($c['system'], 'text')) : (string) ($c['system'] ?? '');
$ok(str_contains($sistema, 'ESPECIALISTA EM FORMULAÇÃO') && str_contains($sistema, 'Anvisa'), 'as instruções do especialista em Formulação vão para a IA');
$nomes = array_column($c['tools'] ?? [], 'name');
$ok(in_array('materias_primas', $nomes, true) && in_array('ficha_de_produto', $nomes, true) && !in_array('meus_numeros', $nomes, true) && !in_array('casos_de_cobranca', $nomes, true),
    'Formulação só recebe as consultas dele (matérias-primas, fichas) e não as de vendas ou cobrança');
$resultado = json_decode((string) (end($pedidos[1]['corpo']['messages'])['content'][0]['content'] ?? '{}'), true);
$ok(isset($resultado['erro']), 'se a IA pedir uma consulta de outro setor (meus_numeros), ela é recusada');
$geral = App\Services\OneEspecialistas::escolher('geral');
@unlink('/tmp/anthropic_simulada.log');
(new App\Integrations\OneIA\OneIAClaude('chave-de-teste', 'claude-opus-5-5', 'http://127.0.0.1:8099'))->responder('oi', [], $geral);
$c = json_decode(file('/tmp/anthropic_simulada.log')[0], true)['corpo'];
$sistema = is_array($c['system'] ?? null) ? implode(' ', array_column($c['system'], 'text')) : (string) ($c['system'] ?? '');
$ok(!str_contains($sistema, 'AGORA VOCÊ ATUA COMO'), 'o One geral continua com as instruções de sempre');
