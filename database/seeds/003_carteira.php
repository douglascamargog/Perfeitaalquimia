<?php
/**
 * Dados da CARTEIRA para a demonstração.
 *
 * 1. Configurações novas da carteira e a origem "Carteira Black Friday 2026".
 * 2. Cidades com coordenadas (Grande Porto Alegre + cidades da amostra).
 * 3. As 499 lojas reais da Carteira V4 (lidas de referencias/dashboard.html — o arquivo NÃO vai para o git).
 *    Ficam na fila da função Prospecção e Reativação.
 * 4. Amostra FICTÍCIA de clientes ativos em outros estados, com vendedores, contatos e tarefas.
 *
 * Os números aleatórios usam semente fixa: toda instalação gera os mesmos dados.
 */
use App\Core\Banco;
use App\Services\CarteiraService;
use App\Services\CarteiraV4;
use App\Services\Normalizar;

mt_srand(2026);

// ---------- 1. Configurações ----------
$novasConfiguracoes = [
    ['carteira.prioridades', 'Alta,Média,Baixa', 'lista', 'Carteira', 'Prioridades', 'Separe por vírgula, da maior para a menor', 20],
    ['carteira.funcao_novos_leads', 'prospeccao_reativacao', 'texto', 'Carteira', 'Função que recebe leads novos e importados', 'Slug da função (veja em Funções)', 21],
    ['carteira.etapa_apos_contato', 'Contato realizado', 'texto', 'Carteira', 'Etapa após o primeiro contato', 'Lead nas etapas iniciais avança para esta etapa quando um contato é registrado', 22],
];
foreach ($novasConfiguracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
}
Banco::inserir('origens', ['nome' => 'Carteira Black Friday 2026']);

// ---------- 2. Cidades ----------
$cidades = [
    // Grande Porto Alegre (coordenadas da Carteira V4)
    'RS' => ['Porto Alegre' => [-30.0346, -51.2177], 'Canoas' => [-29.9178, -51.1838], 'São Leopoldo' => [-29.7547, -51.1498],
        'Novo Hamburgo' => [-29.6783, -51.1300], 'Gravataí' => [-29.9441, -50.9923], 'Cachoeirinha' => [-29.9511, -51.0939],
        'Sapucaia do Sul' => [-29.8333, -51.1453], 'Viamão' => [-30.0819, -51.0234], 'Alvorada' => [-29.9914, -51.0809],
        'Esteio' => [-29.8613, -51.1799], 'Sapiranga' => [-29.6381, -51.0061], 'Estância Velha' => [-29.6488, -51.1730],
        'Campo Bom' => [-29.6748, -51.0590], 'Montenegro' => [-29.6814, -51.4679], 'Guaíba' => [-30.1139, -51.3250],
        'Portão' => [-29.7017, -51.2419], 'Nova Santa Rita' => [-29.8567, -51.2713], 'Dois Irmãos' => [-29.5837, -51.0838],
        'Ivoti' => [-29.5906, -51.1607], 'Igrejinha' => [-29.5744, -50.7901], 'Taquara' => [-29.6505, -50.7802],
        'Nova Hartz' => [-29.5806, -51.0719], 'Triunfo' => [-29.9431, -51.7189], 'São Sebastião do Caí' => [-29.5899, -51.3769],
        'Rolante' => [-29.6473, -50.5756], 'Glorinha' => [-29.8808, -50.7731], 'Arroio dos Ratos' => [-30.0777, -51.7280],
        'Capela de Santana' => [-29.7070, -51.3278], 'Santo Antônio da Patrulha' => [-29.8267, -50.5175], 'Charqueadas' => [-29.9541, -51.6253],
        'Eldorado do Sul' => [-30.0845, -51.6183], 'Parobé' => [-29.6286, -50.8348], 'São Jerônimo' => [-29.9566, -51.7226],
        'Caraá' => [-29.7782, -50.4311], 'Caxias do Sul' => [-29.1678, -51.1794], 'Pelotas' => [-31.7654, -52.3376],
        'Santa Maria' => [-29.6842, -53.8069], 'Passo Fundo' => [-28.2612, -52.4083]],
    // Amostra de outros estados
    'SC' => ['Florianópolis' => [-27.5954, -48.5480], 'Joinville' => [-26.3045, -48.8487], 'Blumenau' => [-26.9194, -49.0661], 'Chapecó' => [-27.1004, -52.6152]],
    'PR' => ['Curitiba' => [-25.4284, -49.2733], 'Londrina' => [-23.3045, -51.1696], 'Maringá' => [-23.4205, -51.9333]],
    'SP' => ['São Paulo' => [-23.5505, -46.6333], 'Campinas' => [-22.9056, -47.0608], 'Ribeirão Preto' => [-21.1775, -47.8103], 'Santos' => [-23.9608, -46.3336]],
    'RJ' => ['Rio de Janeiro' => [-22.9068, -43.1729], 'Niterói' => [-22.8832, -43.1034]],
    'MG' => ['Belo Horizonte' => [-19.9167, -43.9345], 'Uberlândia' => [-18.9186, -48.2772]],
    'ES' => ['Vitória' => [-20.3155, -40.3128]],
    'GO' => ['Goiânia' => [-16.6869, -49.2648]],
    'DF' => ['Brasília' => [-15.7939, -47.8828]],
    'MS' => ['Campo Grande' => [-20.4697, -54.6201]],
    'MT' => ['Cuiabá' => [-15.6010, -56.0974]],
    'BA' => ['Salvador' => [-12.9777, -38.5016]],
    'PE' => ['Recife' => [-8.0476, -34.8770]],
    'CE' => ['Fortaleza' => [-3.7319, -38.5267]],
    'AM' => ['Manaus' => [-3.1190, -60.0217]],
    'PA' => ['Belém' => [-1.4558, -48.4902]],
];
foreach ($cidades as $uf => $lista) {
    foreach ($lista as $nome => [$lat, $lng]) {
        Banco::inserir('cidades', ['nome' => $nome, 'uf' => $uf, 'lat' => $lat, 'lng' => $lng]);
    }
}

// Ids de apoio
$usuario = fn(string $email) => (int) Banco::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
$etapa = fn(string $nome) => (int) Banco::valor('SELECT id FROM funil_etapas WHERE nome = ?', [$nome]);
$tabela = fn(string $slug) => (int) Banco::valor('SELECT id FROM tabelas_preco WHERE slug = ?', [$slug]);
$reativacao = $usuario('reativacao@demo.local');
$diasAtras = fn(int $dias) => date('Y-m-d', strtotime("-$dias days"));

// ---------- 3. As 499 lojas da Carteira V4 ----------
$arquivoV4 = RAIZ . '/referencias/dashboard.html';
$lojasIds = [];
if (is_file($arquivoV4)) {
    foreach (CarteiraV4::lojasDoHtml($arquivoV4) as $loja) {
        $c = CarteiraV4::paraCliente($loja);
        $c['origem'] = 'Carteira Black Friday 2026';
        $c['criado_em'] = '2026-11-09 08:00:00';
        $lojasIds[] = CarteiraService::criar($c, null, 'Importado da Carteira Comercial V4');
    }
} else {
    echo "Aviso: referencias/dashboard.html não encontrado — as 499 lojas não foram carregadas.\n";
}

// Movimento de demonstração em parte das lojas (últimos 7 dias): contatos e avanço no funil
$resumos = ['Apresentei a linha de suplementos; pediu catálogo.', 'Falei com o comprador, pediu retorno na semana que vem.',
    'Loja trabalha com granel, interesse em chás e cápsulas.', 'Enviei a tabela Lojista pelo WhatsApp.',
    'Responsável de compras estava fora; retornar.', 'Gostou do mix, vai avaliar pedido mínimo.'];
$canais = array_keys(CarteiraService::CANAIS);
shuffle($lojasIds);
foreach (array_slice($lojasIds, 0, 70) as $i => $id) {
    $quando = $diasAtras(mt_rand(0, 6));
    CarteiraService::registrarContato($id, $quando, $canais[mt_rand(0, 3)], $resumos[mt_rand(0, count($resumos) - 1)], $reativacao);
    if ($i < 28) { // parte delas avança no funil
        CarteiraService::mudarEtapa($id, $etapa(['Em negociação', 'Proposta enviada', 'Aguardando retorno'][$i % 3]), $reativacao);
    } elseif ($i < 33) {
        CarteiraService::mudarEtapa($id, $etapa('Sem interesse'), $reativacao);
    }
    if ($i < 50) { // próximos retornos: atrasados, hoje e futuros
        App\Models\Tarefa::definirRetorno($id, $reativacao, date('Y-m-d', strtotime(sprintf('%+d days', mt_rand(-4, 10)))));
    }
}
// Prioridade de exemplo para leads vindos da V4 continua a original.

// ---------- 4. Amostra fictícia de clientes ativos em outros estados ----------
$gerarCnpj = function (): string {
    $n = array_map(fn() => mt_rand(0, 9), range(1, 8));
    $n = [...$n, 0, 0, 0, 1];
    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $p) {
            $soma += $n[$i] * $p;
        }
        $n[] = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    }
    return implode('', $n);
};
$inicio = ['Empório', 'Casa', 'Armazém', 'Mercado', 'Espaço', 'Nutri', 'Vida', 'Mundo', 'Ponto', 'Recanto', 'Farmácia', 'Drogaria'];
$fim = ['Natural', 'Verde', 'Integral', 'da Terra', 'Bem-Estar', 'Saudável', 'Grãos', 'Vital', 'Orgânico', 'Equilíbrio', 'Essência', 'Raiz'];
$segmentoPorInicio = ['Farmácia' => 'Farmácia', 'Drogaria' => 'Farmácia', 'Nutri' => 'Suplementos', 'Vida' => 'Suplementos'];
$vendedores = [
    // e-mail do vendedor => [UFs que atende, tabela]
    'televendas1@demo.local'   => [['SP', 'RJ', 'MG', 'ES'], 'lojista'],
    'televendas2@demo.local'   => [['SC', 'PR', 'GO', 'DF', 'MS', 'MT', 'BA', 'PE', 'CE', 'AM', 'PA'], 'lojista'],
    'externo1@demo.local'      => [['RS'], 'lojista'],
    'terceirizacao@demo.local' => [['SP', 'PR', 'SC', 'MG'], 'terceirizacao'],
];
$cidadesAmostra = [];
foreach ($cidades as $uf => $lista) {
    foreach (array_keys($lista) as $nome) {
        if ($uf !== 'RS' || in_array($nome, ['Caxias do Sul', 'Pelotas', 'Santa Maria', 'Passo Fundo', 'Porto Alegre'], true)) {
            $cidadesAmostra[$uf][] = $nome;
        }
    }
}
$usados = [];
for ($i = 0; $i < 120; $i++) {
    $email = array_keys($vendedores)[$i % 4];
    [$ufs, $slugTabela] = $vendedores[$email];
    $uf = $ufs[mt_rand(0, count($ufs) - 1)];
    $cidade = $cidadesAmostra[$uf][mt_rand(0, count($cidadesAmostra[$uf]) - 1)];
    do {
        $prefixo = $inicio[mt_rand(0, count($inicio) - 1)];
        $nome = $prefixo . ' ' . $fim[mt_rand(0, count($fim) - 1)];
        if ($slugTabela === 'terceirizacao') {
            $nome = ['Marca', 'Linha', 'Laboratório'][mt_rand(0, 2)] . ' ' . $fim[mt_rand(0, count($fim) - 1)];
        }
    } while (isset($usados[$nome . $cidade]));
    $usados[$nome . $cidade] = true;

    // Última compra: 3/4 compraram nos últimos 80 dias; 1/4 está há mais de 90 dias (vão para a reativação)
    $diasSemCompra = $i % 4 === 3 ? mt_rand(95, 320) : mt_rand(2, 80);
    $responsavel = $usuario($email);
    $id = CarteiraService::criar([
        'tipo' => 'cliente', 'razao_social' => $nome . ' Ltda', 'nome_fantasia' => $nome, 'cnpj' => $gerarCnpj(),
        'cidade' => $cidade, 'uf' => $uf, 'endereco' => 'Rua Exemplo, ' . mt_rand(10, 2500),
        'segmento' => $slugTabela === 'terceirizacao' ? 'Outros' : ($segmentoPorInicio[$prefixo] ?? 'Produtos naturais'),
        'telefone' => sprintf('%02d3%07d', mt_rand(11, 99), mt_rand(0, 9999999)), 'whatsapp' => sprintf('%02d9%08d', mt_rand(11, 99), mt_rand(0, 99999999)),
        'email' => 'compras' . $i . '@exemplo.com.br', 'etapa' => 'Cliente ativo', 'prioridade' => ['Alta', 'Média', 'Média', 'Baixa'][mt_rand(0, 3)],
        'origem' => ['Indicação', 'Site', 'Anúncio (tráfego pago)', 'Feira/Evento', 'Catálogo'][mt_rand(0, 4)],
        'contatado' => 1, 'ultimo_contato_em' => $diasAtras(mt_rand(1, 40)), 'ultima_compra_em' => $diasAtras($diasSemCompra),
        'intervalo_medio_dias' => mt_rand(25, 60), 'responsavel_id' => $responsavel,
        'criado_em' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(380, 900) . ' days')),
        'observacoes' => 'Cliente fictício (demonstração).',
    ], null, 'Cliente cadastrado (demonstração)');
    Banco::executar('UPDATE clientes SET tabela_preco_id = ? WHERE id = ?', [$tabela($slugTabela === 'lojista' && $i % 7 === 0 ? 'distribuidor' : $slugTabela), $id]);

    // Histórico e próximos contatos
    for ($k = 0; $k < mt_rand(1, 3); $k++) {
        CarteiraService::registrarContato($id, $diasAtras(mt_rand(0, 30)), $canais[mt_rand(0, 3)], ['Pedido de reposição combinado.', 'Cliente pediu novidades de lançamento.', 'Confirmou recebimento do último pedido.'][mt_rand(0, 2)], $responsavel);
    }
    if ($i % 3 === 0) {
        App\Models\Tarefa::definirRetorno($id, $responsavel, date('Y-m-d', strtotime(sprintf('%+d days', mt_rand(-3, 12)))), 'Oferecer reposição');
    }
}
