<?php
/**
 * DEMONSTRAÇÃO da Prospecção (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 *
 * Amostra SIMULADA da base pública de CNPJs do RS: ~1.800 empresas fictícias dos CNAEs do nosso público,
 * em 25 cidades. Nomes, CNPJs (com dígito verificador válido), endereços e telefones são inventados.
 * Os clientes da carteira que têm CNPJ no RS também entram na amostra, para aparecerem como "já na carteira".
 * A base real (dados abertos da Receita Federal) entra numa fase posterior, na mesma tabela.
 */
use App\Core\Banco;

mt_srand(2020);

// Cidade => [peso, bairros]
$cidades = [
    'Porto Alegre' => [30, ['Centro Histórico', 'Moinhos de Vento', 'Bom Fim', 'Cidade Baixa', 'Menino Deus', 'Petrópolis', 'Tristeza', 'Sarandi', 'Partenon', 'Cristal', 'Navegantes', 'Rio Branco']],
    'Canoas' => [9, ['Centro', 'Marechal Rondon', 'Niterói', 'Igara', 'Harmonia', 'São José']],
    'Caxias do Sul' => [10, ['Centro', 'São Pelegrino', 'Exposição', 'Pio X', 'Santa Catarina', 'Cinquentenário']],
    'Pelotas' => [7, ['Centro', 'Fragata', 'Três Vendas', 'Areal', 'Laranjal']],
    'Santa Maria' => [7, ['Centro', 'Camobi', 'Nossa Senhora de Fátima', 'Patronato', 'Dores']],
    'Novo Hamburgo' => [6, ['Centro', 'Hamburgo Velho', 'Canudos', 'Rondônia', 'Operário']],
    'São Leopoldo' => [5, ['Centro', 'Scharlau', 'Feitoria', 'Rio dos Sinos']],
    'Gravataí' => [5, ['Centro', 'Parque dos Anjos', 'Morada do Vale', 'São Geraldo']],
    'Viamão' => [4, ['Centro', 'Santa Isabel', 'Tarumã']],
    'Passo Fundo' => [6, ['Centro', 'Boqueirão', 'Vera Cruz', 'São Cristóvão']],
    'Santa Cruz do Sul' => [4, ['Centro', 'Universitário', 'Arroio Grande']],
    'Cachoeirinha' => [3, ['Centro', 'Vila Imbuí', 'Parque da Matriz']],
    'Alvorada' => [3, ['Centro', 'Jardim Algarve', 'Maria Regina']],
    'Sapucaia do Sul' => [2, ['Centro', 'Vargas', 'Capão da Cruz']],
    'Esteio' => [2, ['Centro', 'Parque Amador', 'Primavera']],
    'Guaíba' => [2, ['Centro', 'Colina', 'Jardim dos Lagos']],
    'Bento Gonçalves' => [4, ['Centro', 'Cidade Alta', 'São Bento']],
    'Lajeado' => [3, ['Centro', 'Moinhos', 'Florestal']],
    'Erechim' => [3, ['Centro', 'Três Vendas', 'Atlântico']],
    'Rio Grande' => [3, ['Centro', 'Cassino', 'Cidade Nova']],
    'Ijuí' => [2, ['Centro', 'Glória', 'Thomé de Souza']],
    'Santo Ângelo' => [2, ['Centro', 'Aliança', 'Haller']],
    'Uruguaiana' => [2, ['Centro', 'São Miguel', 'Cabo Luiz Quevedo']],
    'Torres' => [2, ['Centro', 'Praia Grande', 'Igra Norte']],
    'Tramandaí' => [2, ['Centro', 'Zona Nova', 'Barra']],
];
$ddd = ['Porto Alegre' => 51, 'Canoas' => 51, 'Novo Hamburgo' => 51, 'São Leopoldo' => 51, 'Gravataí' => 51, 'Viamão' => 51, 'Cachoeirinha' => 51,
    'Alvorada' => 51, 'Sapucaia do Sul' => 51, 'Esteio' => 51, 'Guaíba' => 51, 'Lajeado' => 51, 'Santa Cruz do Sul' => 51, 'Torres' => 51, 'Tramandaí' => 51,
    'Caxias do Sul' => 54, 'Bento Gonçalves' => 54, 'Passo Fundo' => 54, 'Erechim' => 54, 'Pelotas' => 53, 'Rio Grande' => 53,
    'Santa Maria' => 55, 'Ijuí' => 55, 'Santo Ângelo' => 55, 'Uruguaiana' => 55];

// CNAE => [peso, descrição, prefixos de nome fantasia, sufixos]
$cnaes = [
    '4729699' => [36, 'Comércio varejista de produtos alimentícios em geral ou especializado em produtos alimentícios não especificados anteriormente',
        ['Empório', 'Casa', 'Armazém', 'Mundo', 'Espaço', 'Mercado', 'Celeiro', 'Natu'], ['Vida Natural', 'Grãos & Cia', 'Bem Viver', 'Saúde', 'Integral', 'da Terra', 'Verde', 'Orgânico', 'Natureza', 'do Campo', 'Zen', 'Equilíbrio']],
    '4771701' => [22, 'Comércio varejista de produtos farmacêuticos, sem manipulação de fórmulas',
        ['Farmácia', 'Drogaria', 'Farma'], ['Bem Estar', 'Popular', 'Saúde Total', 'São João', 'Santa Luzia', 'do Bairro', 'Vida', 'Preço Bom', 'Central', 'Nova']],
    '4771702' => [8, 'Comércio varejista de produtos farmacêuticos, com manipulação de fórmulas',
        ['Farmácia de Manipulação', 'Botica', 'Pharma'], ['Fórmula Certa', 'Essência', 'Alquímica', 'Magistral', 'Herbal', 'Bioativa']],
    '4772500' => [10, 'Comércio varejista de cosméticos, produtos de perfumaria e de higiene pessoal',
        ['Beleza', 'Casa da', 'Studio', 'Mundo da'], ['Natural', 'Pele', 'Cosméticos', 'Aroma', 'Botânica', 'Bela']],
    '4637199' => [6, 'Comércio atacadista especializado em outros produtos alimentícios não especificados anteriormente',
        ['Distribuidora', 'Atacado', 'Comercial'], ['Sul Natural', 'Gaúcha de Alimentos', 'Grãos do Sul', 'Fit Distribuição', 'Integral']],
    '4639701' => [5, 'Comércio atacadista de produtos alimentícios em geral',
        ['Distribuidora', 'Comercial', 'Atacadão'], ['Pampa', 'Serra Gaúcha', 'Litoral', 'Missões', 'Fronteira']],
    '4644301' => [3, 'Comércio atacadista de medicamentos e drogas de uso humano',
        ['Distribuidora', 'Medsul', 'Farmasul'], ['Hospitalar', 'Farmacêutica', 'de Medicamentos']],
];
$sobrenomes = ['Silva', 'Souza', 'Oliveira', 'Pereira', 'Rodrigues', 'Almeida', 'Lima', 'Ferreira', 'Costa', 'Schmidt', 'Becker', 'Müller', 'Rossi', 'Bortolini', 'Machado', 'Moraes', 'Rocha', 'Dias'];
$ruas = ['Rua dos Andradas', 'Av. Brasil', 'Rua Sete de Setembro', 'Av. Independência', 'Rua Marechal Floriano', 'Rua Bento Gonçalves', 'Av. Getúlio Vargas',
    'Rua General Osório', 'Rua XV de Novembro', 'Rua Duque de Caxias', 'Av. Presidente Vargas', 'Rua Júlio de Castilhos', 'Rua Tiradentes', 'Av. Borges de Medeiros'];

$sortear = function (array $pesos) {
    $total = array_sum($pesos);
    $r = mt_rand(1, $total);
    foreach ($pesos as $chave => $peso) {
        if (($r -= $peso) <= 0) {
            return $chave;
        }
    }
    return array_key_first($pesos);
};
$escolher = fn(array $lista) => $lista[mt_rand(0, count($lista) - 1)];
// CNPJ fictício com dígitos verificadores válidos (matriz 0001)
$cnpj = function () {
    $n = '';
    for ($i = 0; $i < 8; $i++) {
        $n .= mt_rand(0, 9);
    }
    $n .= '0001';
    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $p) {
            $soma += (int) $n[$i] * $p;
        }
        $n .= $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    }
    return $n;
};
$porte = ['ME' => 70, 'EPP' => 22, 'Demais' => 8];
$pesosCidades = array_map(fn($c) => $c[0], $cidades);
$pesosCnaes = array_map(fn($c) => $c[0], $cnaes);

$inserir = Banco::pdo()->prepare('INSERT IGNORE INTO prospeccao_empresas (cnpj, razao_social, nome_fantasia, cnae, cnae_descricao, situacao, abertura,
    endereco, bairro, cidade, uf, cep, telefone, email, porte) VALUES (?, ?, ?, ?, ?, \'ATIVA\', ?, ?, ?, ?, \'RS\', ?, ?, ?, ?)');

// 1) Clientes da carteira do RS que têm CNPJ: entram na base (aparecem como "já na carteira")
$naCarteira = 0;
$cnaeDoSegmento = ['Farmácia' => '4771701', 'Suplementos' => '4729699', 'Produtos naturais' => '4729699'];
foreach (Banco::todos("SELECT c.cnpj, c.razao_social, c.nome_fantasia, c.endereco, c.bairro, c.cidade, c.cep, c.telefone, s.nome AS segmento
        FROM clientes c LEFT JOIN segmentos s ON s.id = c.segmento_id WHERE c.uf = 'RS' AND c.cnpj IS NOT NULL AND c.excluido_em IS NULL") as $c) {
    $codigo = $cnaeDoSegmento[$c['segmento'] ?? ''] ?? '4729699';
    $inserir->execute([$c['cnpj'], $c['razao_social'], $c['nome_fantasia'], $codigo, $cnaes[$codigo][1], date('Y-m-d', mt_rand(strtotime('2000-01-01'), strtotime('-2 years'))),
        $c['endereco'], $c['bairro'], $c['cidade'] ?: 'Porto Alegre', $c['cep'], $c['telefone'], null, $sortear($porte)]);
    $naCarteira += $inserir->rowCount();
}

// 2) Empresas fictícias
$total = 0;
$usados = [];
while ($total < 1750) {
    $cidade = $sortear($pesosCidades);
    $codigo = $sortear($pesosCnaes);
    [, $descricao, $prefixos, $sufixos] = $cnaes[$codigo];
    $fantasia = $escolher($prefixos) . ' ' . $escolher($sufixos);
    if (isset($usados["$fantasia|$cidade"])) {
        $fantasia .= ' ' . $escolher(['II', 'Filial Centro', mt_rand(2, 9)]);
    }
    $usados["$fantasia|$cidade"] = true;
    $dono = $escolher($sobrenomes);
    $razao = mb_strtoupper(in_array($codigo, ['4637199', '4639701', '4644301'], true) ? "$fantasia {$dono} Ltda" : "$dono & " . $escolher($sobrenomes) . ' Comércio de ' . (str_starts_with($codigo, '4771') ? 'Medicamentos' : (str_starts_with($codigo, '4772') ? 'Cosméticos' : 'Produtos Naturais')) . ' Ltda');
    // Aberturas: maioria antiga, ~7% nos últimos 12 meses (empresas novas = boas oportunidades)
    $abertura = mt_rand(1, 100) <= 7 ? date('Y-m-d', mt_rand(strtotime('-12 months'), strtotime('-5 days'))) : date('Y-m-d', mt_rand(strtotime('1995-01-01'), strtotime('-13 months')));
    $tel = mt_rand(1, 100) <= 80 ? $ddd[$cidade] . (mt_rand(1, 100) <= 45 ? '9' . mt_rand(81000000, 99999999) : mt_rand(30000000, 39999999)) : null;
    $email = mt_rand(1, 100) <= 45 ? 'contato@' . preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $fantasia))) . '.exemplo.com.br' : null;
    $inserir->execute([$cnpj(), $razao, $fantasia, $codigo, $descricao, $abertura, $escolher($ruas) . ', ' . mt_rand(10, 3999),
        $escolher($cidades[$cidade][1]), $cidade, '9' . mt_rand(1000000, 9999999), $tel, $email, $sortear($porte)]);
    $total += $inserir->rowCount();
}

return "Prospecção (demonstração): $total empresas fictícias do RS + $naCarteira da carteira na base de CNPJs.";
