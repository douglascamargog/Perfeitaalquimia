<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Integrations\LigueTalk\LigueTalkFake;

/**
 * Administração › Dados de teste: gera MOVIMENTO SIMULADO (últimos 60 dias) para as pessoas que já estão
 * cadastradas, para testar todos os setores — e apaga tudo depois com um botão.
 * Tudo o que é gerado fica com simulado = 1 (ou origem 'demonstracao' nas ligações); o que a equipe fez de verdade
 * nunca é tocado. Não mexe em estoques nem no ERP: só registros de movimento.
 *
 *   Vendas (pessoas das funções que são setor de venda): ligações com gravação de exemplo, contatos, WhatsApp
 *     (inclusive mensagens prontas), mudanças de etapa, retornos (feitos, atrasados, futuros), pedidos e meta do mês.
 *   Cobrança (função Cobrança, ou o responsável padrão): casos com títulos vencidos, tentativas, promessas
 *     (cumpridas e quebradas), títulos pagos e ligações.
 *   Produção: ordens planejadas. Marketing: posts em várias etapas.
 */
class SimuladorService
{
    private const DIAS = 60;

    /** Quanto existe de simulado agora (para a tela) */
    public static function contagem(): array
    {
        $n = fn(string $sql) => (int) Banco::valor($sql);
        return [
            'clientes' => $n('SELECT COUNT(*) FROM clientes WHERE simulado = 1'),
            'ligacoes' => $n('SELECT COUNT(*) FROM ligacoes WHERE simulado = 1'),
            'contatos' => $n('SELECT COUNT(*) FROM cliente_eventos WHERE simulado = 1'),
            'tarefas'  => $n('SELECT COUNT(*) FROM tarefas WHERE simulado = 1'),
            'pedidos'  => $n('SELECT COUNT(*) FROM pedidos WHERE simulado = 1'),
            'cobrancas' => $n('SELECT COUNT(*) FROM cobrancas WHERE simulado = 1'),
            'ordens'   => $n('SELECT COUNT(*) FROM ordens_producao WHERE simulado = 1'),
            'posts'    => $n('SELECT COUNT(*) FROM mkt_posts WHERE simulado = 1'),
        ];
    }

    /** Pessoas de cada área que vão receber movimento */
    public static function pessoas(): array
    {
        $vendas = Banco::todos('SELECT DISTINCT u.id, u.nome FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id
            JOIN funcoes f ON f.id = uf.funcao_id WHERE u.ativo = 1 AND f.setor_venda = 1 ORDER BY u.nome');
        $funcaoCob = CarteiraService::funcaoId((string) \App\Core\Config::get('cobranca.funcao', 'cobranca')) ?? 0;
        $cobranca = Banco::todos('SELECT DISTINCT u.id, u.nome FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id
            WHERE u.ativo = 1 AND uf.funcao_id = ? ORDER BY u.nome', [$funcaoCob]);
        if (!$cobranca && ($r = CobrancaService::responsavel())) {
            $cobranca = Banco::todos('SELECT id, nome FROM usuarios WHERE id = ?', [$r]);
        }
        return ['vendas' => $vendas, 'cobranca' => $cobranca];
    }

    /** Gera o movimento. Devolve o resumo do que foi criado. */
    public static function gerar(?int $quem): array
    {
        mt_srand();
        $antes = self::contagem();
        $p = self::pessoas();
        $gravacoes = [LigueTalkFake::gerarGravacao('sim-a', 4), LigueTalkFake::gerarGravacao('sim-b', 6), LigueTalkFake::gerarGravacao('sim-c', 3)];
        @set_time_limit(300); // hospedagem compartilhada: dá tempo para gerar tudo
        self::$fila = [];
        Banco::transacao(function () use ($p, $gravacoes, $quem) {
            foreach ($p['vendas'] as $pessoa) {
                self::vendas((int) $pessoa['id'], $gravacoes);
                self::esvaziar();
            }
            foreach ($p['cobranca'] as $pessoa) {
                self::cobranca((int) $pessoa['id'], $gravacoes);
            }
            self::esvaziar();
            self::producao($quem);
            self::marketing($quem);
        });
        self::recalcularCompras('SELECT DISTINCT cliente_id FROM pedidos WHERE simulado = 1');
        $depois = self::contagem();
        $criado = array_map(fn($k) => $depois[$k] - $antes[$k], array_combine(array_keys($depois), array_keys($depois)));
        Auditoria::registrar('simulador', null, 'gerar', null, $criado + ['pessoas_vendas' => count($p['vendas']), 'pessoas_cobranca' => count($p['cobranca'])]);
        PendenciasService::limpar();
        return $criado;
    }

    /** Apaga só o que foi simulado (com backup do banco antes). Devolve o que foi apagado. */
    public static function apagar(?int $quem): array
    {
        BackupBanco::automatico();
        $antes = self::contagem();
        $comPedidoSimulado = array_column(Banco::todos('SELECT DISTINCT p.cliente_id FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.simulado = 1 AND c.simulado = 0'), 'cliente_id');
        Banco::transacao(function () {
            $simulados = 'SELECT id FROM clientes WHERE simulado = 1';
            Banco::executar("DELETE FROM pedido_item_lotes WHERE pedido_id IN (SELECT id FROM pedidos WHERE simulado = 1 OR cliente_id IN ($simulados))");
            Banco::executar("DELETE FROM pedidos WHERE simulado = 1 OR cliente_id IN ($simulados)");      // itens e histórico vão junto
            Banco::executar("DELETE FROM cobrancas WHERE simulado = 1 OR cliente_id IN ($simulados)");    // títulos vão junto
            Banco::executar("DELETE FROM ligacoes WHERE simulado = 1 OR cliente_id IN ($simulados)");
            Banco::executar('DELETE FROM tarefas WHERE simulado = 1');
            Banco::executar('DELETE FROM cliente_eventos WHERE simulado = 1');
            Banco::executar('DELETE FROM ordens_producao WHERE simulado = 1 AND situacao = \'planejada\'');
            Banco::executar('DELETE FROM mkt_posts WHERE simulado = 1');
            Banco::executar('DELETE FROM metas WHERE simulado = 1');
            Banco::executar('DELETE FROM compra_cotacoes WHERE compra_id IN (SELECT id FROM compras WHERE simulado = 1)');
            Banco::executar('DELETE FROM compra_historico WHERE compra_id IN (SELECT id FROM compras WHERE simulado = 1)');
            Banco::executar('DELETE FROM compras WHERE simulado = 1');
            Banco::executar("DELETE FROM roteiro_paradas WHERE cliente_id IN ($simulados)");
            Banco::executar('DELETE FROM clientes WHERE simulado = 1');                                 // eventos e tarefas deles vão junto
        });
        // Lojas reais que tinham pedidos simulados: última compra volta a ser a dos pedidos que ficaram
        foreach ($comPedidoSimulado as $cid) {
            PedidoService::atualizarCompras((int) $cid);
        }
        Auditoria::registrar('simulador', null, 'apagar', $antes, null);
        PendenciasService::limpar();
        return $antes;
    }

    // ------------------------------------------------------------------
    // Base de clientes de teste (ex.: 5.000 lojas variadas)
    // ------------------------------------------------------------------

    /** Cidades da base de teste: [nome, UF, lat, lng, peso] — a maior parte no RS */
    private const CIDADES_BASE = [
        ['Porto Alegre', 'RS', -30.0346, -51.2177, 30], ['Caxias do Sul', 'RS', -29.1678, -51.1794, 14], ['Pelotas', 'RS', -31.7654, -52.3376, 9],
        ['Canoas', 'RS', -29.9178, -51.1839, 9], ['Santa Maria', 'RS', -29.6842, -53.8069, 9], ['Gravataí', 'RS', -29.9440, -50.9919, 7],
        ['Viamão', 'RS', -30.0819, -51.0194, 6], ['Novo Hamburgo', 'RS', -29.6783, -51.1309, 8], ['São Leopoldo', 'RS', -29.7545, -51.1498, 7],
        ['Rio Grande', 'RS', -32.0350, -52.0986, 5], ['Alvorada', 'RS', -29.9914, -51.0809, 4], ['Passo Fundo', 'RS', -28.2620, -52.4083, 7],
        ['Sapucaia do Sul', 'RS', -29.8276, -51.1453, 3], ['Uruguaiana', 'RS', -29.7547, -57.0883, 3], ['Santa Cruz do Sul', 'RS', -29.7175, -52.4258, 5],
        ['Cachoeirinha', 'RS', -29.9472, -51.0936, 3], ['Bagé', 'RS', -31.3297, -54.1069, 3], ['Bento Gonçalves', 'RS', -29.1714, -51.5192, 5],
        ['Erechim', 'RS', -27.6364, -52.2697, 4], ['Guaíba', 'RS', -30.1086, -51.3233, 3], ['Lajeado', 'RS', -29.4669, -51.9614, 4],
        ['Ijuí', 'RS', -28.3880, -53.9147, 3], ['Santo Ângelo', 'RS', -28.2992, -54.2631, 3], ['Esteio', 'RS', -29.8517, -51.1794, 2],
        ['Sapiranga', 'RS', -29.6380, -51.0069, 2], ['Santana do Livramento', 'RS', -30.8908, -55.5328, 2], ['Cachoeira do Sul', 'RS', -30.0392, -52.8939, 2],
        ['Farroupilha', 'RS', -29.2250, -51.3478, 2], ['Venâncio Aires', 'RS', -29.6061, -52.1919, 2], ['Campo Bom', 'RS', -29.6789, -51.0528, 2],
        ['Montenegro', 'RS', -29.6886, -51.4614, 2], ['Vacaria', 'RS', -28.5122, -50.9339, 2], ['Gramado', 'RS', -29.3786, -50.8739, 2],
        ['Canela', 'RS', -29.3658, -50.8158, 1], ['Torres', 'RS', -29.3353, -49.7269, 2], ['Tramandaí', 'RS', -29.9847, -50.1336, 2],
        ['Capão da Canoa', 'RS', -29.7456, -50.0097, 2], ['Cruz Alta', 'RS', -28.6386, -53.6064, 2], ['Carazinho', 'RS', -28.2839, -52.7864, 2],
        ['Santa Rosa', 'RS', -27.8708, -54.4814, 2], ['Estrela', 'RS', -29.5019, -51.9658, 1], ['Garibaldi', 'RS', -29.2564, -51.5336, 1],
        ['Frederico Westphalen', 'RS', -27.3592, -53.3944, 1], ['São Borja', 'RS', -28.6608, -56.0044, 1], ['Alegrete', 'RS', -29.7831, -55.7919, 1],
        ['Camaquã', 'RS', -30.8511, -51.8122, 1], ['Osório', 'RS', -29.8869, -50.2697, 1], ['Taquara', 'RS', -29.6505, -50.7753, 1],
        ['Florianópolis', 'SC', -27.5954, -48.5480, 8], ['Joinville', 'SC', -26.3045, -48.8487, 7], ['Blumenau', 'SC', -26.9194, -49.0661, 5],
        ['Chapecó', 'SC', -27.1004, -52.6152, 4], ['Criciúma', 'SC', -28.6775, -49.3697, 3], ['Itajaí', 'SC', -26.9078, -48.6619, 3],
        ['Curitiba', 'PR', -25.4284, -49.2733, 10], ['Londrina', 'PR', -23.3045, -51.1696, 4], ['Maringá', 'PR', -23.4205, -51.9333, 4],
        ['Cascavel', 'PR', -24.9555, -53.4552, 3], ['Ponta Grossa', 'PR', -25.0945, -50.1633, 2],
        ['São Paulo', 'SP', -23.5505, -46.6333, 8], ['Campinas', 'SP', -22.9099, -47.0626, 3], ['Ribeirão Preto', 'SP', -21.1704, -47.8103, 2],
        ['Rio de Janeiro', 'RJ', -22.9068, -43.1729, 4], ['Belo Horizonte', 'MG', -19.9167, -43.9345, 3], ['Goiânia', 'GO', -16.6869, -49.2648, 2],
        ['Brasília', 'DF', -15.7939, -47.8828, 2], ['Campo Grande', 'MS', -20.4697, -54.6201, 1], ['Salvador', 'BA', -12.9777, -38.5016, 2],
        ['Recife', 'PE', -8.0476, -34.8770, 1], ['Fortaleza', 'CE', -3.7319, -38.5267, 1],
    ];

    /**
     * Cria $quantidade clientes/leads SIMULADOS (simulado = 1) bem variados — cidades (a maioria no RS), segmentos,
     * etapas, prioridades, origens, vendedoras, localização no mapa — e o histórico de compras de 12 meses
     * (pedidos faturados), para testar filtros, Análise de clientes, mapa, recompra e o Planejador de visitas.
     * Tudo sai com "Apagar dados simulados". Gravação em lotes (rápido em hospedagem compartilhada).
     */
    public static function gerarBase(int $quantidade, ?int $quem): array
    {
        $quantidade = max(10, min(10000, $quantidade));
        @set_time_limit(900);
        @ini_set('memory_limit', '512M');
        mt_srand();
        $vendedores = array_map(fn($v) => ['id' => (int) $v['id'], 'funcao' => CarteiraService::funcaoPrincipal((int) $v['id'])], self::pessoas()['vendas']);
        $etapas = array_column(Banco::todos('SELECT id, nome, tipo FROM funil_etapas WHERE ativa = 1 ORDER BY ordem'), null, 'id');
        $porTipo = fn(string $tipo) => array_values(array_map(fn($e) => (int) $e['id'], array_filter($etapas, fn($e) => $e['tipo'] === $tipo)));
        [$abertas, $ganho, $perdido, $inativo] = [$porTipo('aberta'), $porTipo('ganho'), $porTipo('perdido'), $porTipo('inativo')];
        $segmentos = array_map('intval', array_column(Banco::todos('SELECT id FROM segmentos WHERE ativo = 1'), 'id')) ?: [null];
        $origens = array_map('intval', array_column(Banco::todos('SELECT id FROM origens WHERE ativo = 1'), 'id')) ?: [null];
        $tabelas = array_map('intval', array_column(Banco::todos('SELECT id FROM tabelas_preco WHERE ativa = 1 ORDER BY id'), 'id')) ?: [null];
        $prioridades = \App\Models\Listas::prioridades();
        $forma = (int) Banco::valor('SELECT id FROM formas_pagamento WHERE ativa = 1 ORDER BY id LIMIT 1');
        $produtos = Banco::todos('SELECT p.id, p.nome, p.codigo, pp.tabela_preco_id, pp.preco FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id
            WHERE p.ativo = 1 AND p.cliente_exclusivo_id IS NULL AND pp.preco > 0');
        $precos = [];
        foreach ($produtos as $pr) {
            $precos[(int) $pr['tabela_preco_id']][] = $pr;
        }
        // Cidades (com o ponto no mapa) e o sorteio pelo peso
        $cidades = [];
        foreach (self::CIDADES_BASE as [$nome, $uf, $lat, $lng, $peso]) {
            $id = \App\Models\Cidade::idOuCriar($nome, $uf);
            Banco::executar('UPDATE cidades SET lat = ?, lng = ? WHERE id = ? AND lat IS NULL', [$lat, $lng, $id]);
            $cidades[] = ['id' => $id, 'nome' => $nome, 'uf' => $uf, 'lat' => $lat, 'lng' => $lng, 'peso' => $peso, 'regiao' => \App\Models\Listas::regiaoDaUf($uf)];
        }
        $pesoTotal = array_sum(array_column($cidades, 'peso'));
        $sortearCidade = function () use ($cidades, $pesoTotal) {
            $n = mt_rand(1, $pesoTotal);
            foreach ($cidades as $c) {
                if (($n -= $c['peso']) <= 0) {
                    return $c;
                }
            }
            return $cidades[0];
        };
        $tipos = ['Farmácia', 'Drogaria', 'Empório', 'Mercado', 'Casa Natural', 'Armazém', 'Loja', 'Suplementos', 'Nutri', 'Mercearia', 'Bio', 'Espaço'];
        $nomes = ['Vida', 'Saúde', 'Verde', 'Bem-Estar', 'Raiz', 'Essência', 'Equilíbrio', 'Natureza', 'Grão', 'Integral', 'Sol', 'Alecrim', 'Camomila',
            'Girassol', 'Jasmim', 'Oliva', 'Semente', 'Folha', 'Terra', 'Luz', 'Harmonia', 'Ervas', 'Pomar', 'Colheita'];
        $ruas = ['Rua das Flores', 'Av. Brasil', 'Rua XV de Novembro', 'Av. Independência', 'Rua Sete de Setembro', 'Rua dos Andradas', 'Av. Getúlio Vargas',
            'Rua Marechal Floriano', 'Rua Bento Gonçalves', 'Av. Presidente Vargas', 'Rua Júlio de Castilhos', 'Rua Santos Dumont'];
        $bairros = ['Centro', 'Moinhos', 'São João', 'Petrópolis', 'Cidade Baixa', 'Floresta', 'Rio Branco', 'Menino Deus', 'Jardim', 'Vila Nova'];
        $sorteio = fn(array $l) => $l[array_rand($l)];
        $seq = (int) Banco::valor('SELECT COUNT(*) FROM clientes WHERE simulado = 1') + 1;
        $carteiras = [];
        $cnpj = function (int $n): string { // CNPJ válido (dígitos conferidos), único pela sequência
            $base = str_pad((string) (90000000 + $n), 8, '0', STR_PAD_LEFT) . '0001';
            foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
                $soma = 0;
                foreach ($pesos as $i => $p) {
                    $soma += (int) $base[$i] * $p;
                }
                $base .= ($soma % 11) < 2 ? '0' : (string) (11 - $soma % 11);
            }
            return $base;
        };

        $criados = ['clientes' => 0, 'leads' => 0, 'pedidos' => 0];
        $hoje = strtotime('today');
        foreach (array_chunk(range(1, $quantidade), 250) as $lote) {
            $linhas = [];
            $planos = [];
            foreach ($lote as $_) {
                $n = $seq++;
                $cid = $sortearCidade();
                $vend = $vendedores ? $vendedores[array_rand($vendedores)] : ['id' => null, 'funcao' => null];
                $chaveCarteira = ($vend['id'] ?? 0) . '|' . ($vend['funcao'] ?? 0);
                $carteiras[$chaveCarteira] ??= $vend['id'] || $vend['funcao'] ? \App\Services\CarteirasService::carteiraDe($vend['id'], $vend['funcao']) : null;
                $cliente = mt_rand(1, 100) <= 72; // 72% clientes, 28% leads
                $ultima = null;
                $intervalo = null;
                $etapa = $abertas ? $sorteio($abertas) : null;
                if ($cliente) {
                    $intervalo = mt_rand(15, 75);
                    $parado = mt_rand(1, 100) <= 30 ? mt_rand(60, 300) : mt_rand(0, $intervalo + 10); // 30% parados
                    $ultima = $hoje - $parado * 86400;
                    $etapa = $parado > 120 && $inativo ? $sorteio($inativo) : ($ganho ? $sorteio($ganho) : $etapa);
                    $planos[$n] = ['intervalo' => $intervalo, 'ultima' => $ultima, 'ticket' => mt_rand(250, 2500), 'tabela' => $sorteio($tabelas), 'vendedor' => $vend['id']];
                } elseif ($perdido && mt_rand(1, 100) <= 15) {
                    $etapa = $sorteio($perdido);
                }
                $tel = fn() => '(' . mt_rand(11, 99) . ') 9' . mt_rand(1000, 9999) . '-' . mt_rand(1000, 9999);
                $endereco = $sorteio($ruas) . ', ' . mt_rand(10, 3500);
                $bairro = $sorteio($bairros);
                $fantasia = $sorteio($tipos) . ' ' . $sorteio($nomes) . ' ' . $n;
                $linhas[] = ['tipo' => $cliente ? 'cliente' : 'lead', 'razao_social' => $fantasia . ' Teste Ltda', 'nome_fantasia' => $fantasia, 'cnpj' => $cnpj($n),
                    'endereco' => $endereco, 'bairro' => $bairro, 'cep' => sprintf('%05d%03d', mt_rand(80000, 99999), mt_rand(0, 999)),
                    'cidade' => $cid['nome'], 'uf' => $cid['uf'], 'cidade_id' => $cid['id'], 'regiao_id' => $cid['regiao'],
                    'lat' => round($cid['lat'] + (mt_rand(-400, 400) / 10000), 6), 'lng' => round($cid['lng'] + (mt_rand(-400, 400) / 10000), 6),
                    'geo_precisao' => 'telhado', 'geo_hash' => sha1(implode('|', [$endereco, $bairro, '', $cid['nome'], $cid['uf']])), // não gasta o Google
                    'segmento_id' => $sorteio($segmentos), 'origem_id' => $sorteio($origens), 'tabela_preco_id' => $cliente ? $planos[$n]['tabela'] : null,
                    'telefone' => $tel(), 'whatsapp' => mt_rand(1, 100) <= 80 ? $tel() : null, 'email' => mt_rand(1, 100) <= 60 ? 'contato' . $n . '@loja-teste.com.br' : null,
                    'prioridade' => $sorteio($prioridades), 'responsavel_id' => $vend['id'], 'funcao_id' => $vend['funcao'], 'carteira_id' => $carteiras[$chaveCarteira],
                    'etapa_id' => $etapa, 'contatado' => $cliente || mt_rand(1, 100) <= 40 ? 1 : 0,
                    'ultimo_contato_em' => $cliente || mt_rand(1, 100) <= 40 ? date('Y-m-d', $hoje - mt_rand(0, 90) * 86400) : null,
                    'ultima_compra_em' => $ultima ? date('Y-m-d', $ultima) : null, 'intervalo_medio_dias' => $intervalo, 'simulado' => 1];
                $criados[$cliente ? 'clientes' : 'leads']++;
            }
            foreach ($linhas as $l) {
                self::guardar('clientes', $l);
            }
            self::esvaziar();
            // o geo_hash precisa bater com o do One (com o CEP): recalcula no banco
            Banco::executar("UPDATE clientes c SET c.geo_hash = " . self::SQL_HASH_GEO . " WHERE c.simulado = 1 AND c.geo_precisao = 'telhado' AND c.cnpj IN ("
                . implode(',', array_fill(0, count($linhas), '?')) . ')', array_column($linhas, 'cnpj'));
            if ($planos && $precos) {
                $ids = array_column(Banco::todos('SELECT id, cnpj FROM clientes WHERE cnpj IN (' . implode(',', array_fill(0, count($linhas), '?')) . ')',
                    array_column($linhas, 'cnpj')), 'id', 'cnpj');
                $criados['pedidos'] += self::historicoBase($planos, $ids, $cnpj, $precos, $forma, $hoje);
            }
        }
        Auditoria::registrar('simulador', null, 'gerar_base', null, $criados + ['quantidade' => $quantidade]);
        PendenciasService::limpar();
        \App\Models\Listas::esquecer();
        return $criados;
    }

    /**
     * Última compra e intervalo médio das lojas com pedidos simulados (igual ao faturamento de verdade:
     * PedidoService::atualizarCompras). Sem isso o filtro "sem compra há X dias" mostraria quem acabou de comprar.
     */
    private static function recalcularCompras(string $sqlIds): void
    {
        foreach (array_column(Banco::todos($sqlIds), 'cliente_id') as $cid) {
            PedidoService::atualizarCompras((int) $cid);
        }
    }

    /** Mesma conta do LocalizacaoService (endereço + bairro + CEP + cidade + UF) */
    private const SQL_HASH_GEO = "SHA1(CONCAT_WS('|', COALESCE(c.endereco, ''), COALESCE(c.bairro, ''), COALESCE(c.cep, ''), COALESCE(c.cidade, ''), COALESCE(c.uf, '')))";

    /** Pedidos FATURADOS dos últimos 12 meses de cada cliente do lote (no ritmo do intervalo dele). Devolve quantos. */
    private static function historicoBase(array $planos, array $ids, callable $cnpj, array $precos, int $forma, int $hoje): int
    {
        $pedidos = [];
        $itensPorUuid = [];
        $inicio = $hoje - 365 * 86400;
        foreach ($planos as $n => $pl) {
            $clienteId = (int) ($ids[$cnpj($n)] ?? 0);
            $lista = $precos[$pl['tabela']] ?? reset($precos);
            if (!$clienteId || !$lista) {
                continue;
            }
            for ($t = $pl['ultima']; $t >= $inicio; $t -= ($pl['intervalo'] + mt_rand(-5, 5)) * 86400) {
                $uuid = self::uuid();
                $total = 0.0;
                $itens = [];
                foreach ((array) array_rand($lista, min(count($lista), mt_rand(1, 4))) as $k) {
                    $pr = $lista[$k];
                    $q = max(1, (int) round($pl['ticket'] / max(1, (float) $pr['preco']) / mt_rand(1, 4)));
                    $itens[] = ['produto_id' => (int) $pr['id'], 'codigo' => $pr['codigo'], 'nome' => $pr['nome'], 'quantidade' => $q, 'preco_unitario' => (float) $pr['preco'],
                        'total' => round($q * (float) $pr['preco'], 2)];
                    $total += $q * (float) $pr['preco'];
                }
                $dia = date('Y-m-d', $t);
                $pedidos[] = ['uuid' => $uuid, 'cliente_id' => $clienteId, 'vendedor_id' => $pl['vendedor'], 'origem' => ['sistema', 'catalogo', 'app'][mt_rand(0, 2)],
                    'tabela_preco_id' => $pl['tabela'], 'forma_pagamento_id' => $forma, 'subtotal' => round($total, 2), 'total' => round($total, 2),
                    'situacao' => 'faturado', 'faturado_em' => $dia . ' 15:00:00', 'criado_por_usuario' => $pl['vendedor'], 'simulado' => 1,
                    'criado_em' => date('Y-m-d H:i:s', $t - 86400 + mt_rand(8, 17) * 3600)];
                $itensPorUuid[$uuid] = $itens;
            }
        }
        foreach (array_chunk($pedidos, 400) as $bloco) {
            foreach ($bloco as $p) {
                self::guardar('pedidos', $p);
            }
            self::esvaziar();
            $idPorUuid = array_column(Banco::todos('SELECT id, uuid FROM pedidos WHERE uuid IN (' . implode(',', array_fill(0, count($bloco), '?')) . ')',
                array_column($bloco, 'uuid')), 'id', 'uuid');
            foreach ($bloco as $p) {
                foreach ($itensPorUuid[$p['uuid']] as $it) {
                    self::guardar('pedido_itens', $it + ['pedido_id' => (int) $idPorUuid[$p['uuid']]]);
                }
            }
            self::esvaziar();
        }
        return count($pedidos);
    }

    // ------------------------------------------------------------------
    // Vendas
    // ------------------------------------------------------------------

    private static function vendas(int $uid, array $gravacoes): void
    {
        $clientes = self::clientesDe($uid);
        if (!$clientes) {
            return;
        }
        $etapas = array_column(Banco::todos('SELECT nome FROM funil_etapas WHERE ativa = 1 ORDER BY ordem'), 'nome');
        $prontas = array_column(Banco::todos('SELECT titulo FROM mensagens_prontas WHERE ativo = 1 LIMIT 8'), 'titulo') ?: ['Retomar contato'];
        $produtos = Banco::todos('SELECT p.id, p.nome, p.codigo, (SELECT MIN(pp.preco) FROM produto_precos pp WHERE pp.produto_id = p.id AND pp.preco > 0) AS preco
            FROM produtos p WHERE p.ativo = 1 AND EXISTS (SELECT 1 FROM produto_precos pp WHERE pp.produto_id = p.id AND pp.preco > 0) ORDER BY RAND() LIMIT 40');
        $tabela = (int) Banco::valor('SELECT id FROM tabelas_preco ORDER BY id LIMIT 1');
        $forma = (int) Banco::valor('SELECT id FROM formas_pagamento ORDER BY id LIMIT 1');
        $ritmo = mt_rand(5, 14);
        $faturadoMes = 0.0;
        for ($d = self::DIAS; $d >= 0; $d--) {
            $dia = strtotime("-$d days");
            if ((int) date('N', $dia) >= 6) {
                continue;
            }
            $data = date('Y-m-d', $dia);
            // Ligações (com gravação nas atendidas) e o contato na linha do tempo
            for ($i = 0, $n = max(0, $ritmo + mt_rand(-3, 4)); $i < $n; $i++) {
                $c = $clientes[array_rand($clientes)];
                $quando = self::hora($data);
                if ($quando > date('Y-m-d H:i:s')) {
                    continue;
                }
                $s = mt_rand(1, 100);
                $sit = $s <= 63 ? 'atendida' : ($s <= 90 ? 'nao_atendida' : 'ocupado');
                $dur = $sit === 'atendida' ? mt_rand(30, 480) : 0;
                self::guardar('ligacoes', ['id_externo' => 'sim-' . bin2hex(random_bytes(6)), 'usuario_id' => $uid, 'cliente_id' => $c['id'], 'numero' => $c['tel'],
                    'situacao' => $sit, 'inicio' => $quando, 'duracao_seg' => $dur, 'gravacao' => $sit === 'atendida' ? $gravacoes[array_rand($gravacoes)] : null,
                    'origem' => 'demonstracao', 'simulado' => 1, 'criado_em' => $quando]);
                if ($sit === 'atendida') {
                    self::evento($c['id'], $uid, 'ligacao', 'Ligação pelo Ligue Talk: atendida (' . intdiv($dur, 60) . ' min)', $quando, 'Ligação');
                }
            }
            // WhatsApp (parte com mensagem pronta), visitas e mudanças de etapa
            for ($i = 0, $n = mt_rand(1, 5); $i < $n; $i++) {
                $c = $clientes[array_rand($clientes)];
                self::evento($c['id'], $uid, 'whatsapp', mt_rand(0, 2) ? 'Conversa pelo WhatsApp' : 'Mensagem pronta: ' . $prontas[array_rand($prontas)], self::hora($data), 'WhatsApp');
            }
            if (mt_rand(0, 4) === 0) {
                $c = $clientes[array_rand($clientes)];
                self::evento($c['id'], $uid, 'visita', 'Visita ao cliente (simulada)', self::hora($data), 'Visita presencial');
            }
            if (count($etapas) > 2 && mt_rand(0, 1)) {
                $k = mt_rand(0, count($etapas) - 2);
                $c = $clientes[array_rand($clientes)];
                self::evento($c['id'], $uid, 'etapa', $etapas[$k] . ' → ' . $etapas[$k + 1], self::hora($data));
            }
            // Retornos: feitos no prazo, feitos com atraso, atrasados em aberto, de hoje e futuros
            for ($i = 0, $n = mt_rand(1, 4); $i < $n; $i++) {
                $c = $clientes[array_rand($clientes)];
                $sorte = mt_rand(1, 100);
                $concluida = null;
                if ($d > 0 && $sorte <= 70) {
                    $concluida = $data . ' ' . sprintf('%02d:%02d:00', mt_rand(9, 17), mt_rand(0, 59));
                } elseif ($d > 3 && $sorte <= 88) {
                    $concluida = date('Y-m-d 10:00:00', strtotime("$data +" . mt_rand(1, 3) . ' days'));
                }
                self::guardar('tarefas', ['cliente_id' => $c['id'], 'usuario_id' => $uid, 'titulo' => 'Retorno: ' . ['ver reposição', 'mandar tabela', 'confirmar pedido', 'oferecer lançamento'][mt_rand(0, 3)],
                    'tipo' => 'retorno', 'vence_em' => $data, 'concluida_em' => $concluida, 'simulado' => 1, 'criado_em' => date('Y-m-d 08:00:00', strtotime("$data -3 days"))]);
            }
            // Pedidos
            if ($produtos && mt_rand(0, 100) < 55) {
                $c = $clientes[array_rand($clientes)];
                $faturadoMes += self::pedido($c['id'], $uid, $data, $produtos, $tabela, $forma, $d);
            }
        }
        // Retornos futuros
        for ($i = 1; $i <= 6; $i++) {
            $c = $clientes[array_rand($clientes)];
            self::guardar('tarefas', ['cliente_id' => $c['id'], 'usuario_id' => $uid, 'titulo' => 'Retorno: ligar de novo', 'tipo' => 'retorno',
                'vence_em' => date('Y-m-d', strtotime("+$i weekdays")), 'simulado' => 1]);
        }
        // Meta do mês (só se a pessoa ainda não tem)
        $mes = date('Y-m');
        if (!Banco::valor("SELECT 1 FROM metas WHERE mes = ? AND tipo = 'vendedor' AND alvo_id = ?", [$mes, $uid])) {
            $base = max(15000, (float) Banco::valor("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE vendedor_id = ? AND situacao = 'faturado' AND faturado_em >= ?", [$uid, "$mes-01"]) * 1.4);
            Banco::inserir('metas', ['mes' => $mes, 'tipo' => 'vendedor', 'alvo_id' => $uid, 'valor' => round($base / 500) * 500, 'simulado' => 1]);
        }
    }

    /** Pedido simulado (sem ERP e sem mexer em estoque). Devolve o valor faturado. */
    private static function pedido(int $clienteId, int $uid, string $data, array $produtos, int $tabela, int $forma, int $diasAtras): float
    {
        $itens = [];
        $total = 0.0;
        foreach ((array) array_rand($produtos, min(count($produtos), mt_rand(2, 6))) as $k) {
            $pr = $produtos[$k];
            $q = mt_rand(3, 24);
            $itens[] = ['produto_id' => $pr['id'], 'nome' => $pr['nome'], 'quantidade' => $q, 'preco_unitario' => $pr['preco'], 'total' => round($q * $pr['preco'], 2)];
            $total += $q * $pr['preco'];
        }
        $s = mt_rand(1, 100);
        $situacao = $diasAtras <= 1 ? 'aguardando_aprovacao' : ($s <= 82 ? 'faturado' : ($s <= 92 ? 'recusado' : 'aprovado'));
        $criado = self::hora($data);
        $id = Banco::inserir('pedidos', ['uuid' => self::uuid(), 'cliente_id' => $clienteId, 'vendedor_id' => $uid, 'origem' => ['sistema', 'catalogo', 'app'][mt_rand(0, 2)],
            'tabela_preco_id' => $tabela, 'forma_pagamento_id' => $forma, 'subtotal' => round($total, 2), 'total' => round($total, 2), 'situacao' => $situacao,
            'faturado_em' => $situacao === 'faturado' ? date('Y-m-d 15:00:00', strtotime("$data +1 day")) : null,
            'motivo_recusa' => $situacao === 'recusado' ? 'Crédito em análise (simulado)' : null, 'criado_por_usuario' => $uid, 'simulado' => 1, 'criado_em' => $criado,
            // Faturados dos últimos dias entram na fila do depósito (separar e despachar)
            'expedicao' => $situacao === 'faturado' && $diasAtras <= 4 ? 'aguardando' : null,
            'expedicao_desde' => $situacao === 'faturado' && $diasAtras <= 4 ? date('Y-m-d 15:00:00', strtotime("$data +1 day")) : null]);
        foreach ($itens as $it) {
            Banco::inserir('pedido_itens', $it + ['pedido_id' => $id]);
        }
        self::evento($clienteId, $uid, 'pedido', 'Pedido #' . $id . ' (simulado)', $criado);
        return $situacao === 'faturado' ? $total : 0.0;
    }

    // ------------------------------------------------------------------
    // Cobrança
    // ------------------------------------------------------------------

    private static function cobranca(int $uid, array $gravacoes): void
    {
        $etapas = array_column(Banco::todos('SELECT id, tipo FROM cobranca_etapas WHERE ativa = 1'), 'id', 'tipo');
        $entrada = (int) (CobrancaService::etapaEntrada()['id'] ?? 0);
        $clientes = self::comTelefone(Banco::todos("SELECT c.id, COALESCE(NULLIF(c.telefone, ''), c.whatsapp, '') AS tel FROM clientes c
            WHERE c.excluido_em IS NULL AND c.tipo = 'cliente' AND NOT EXISTS (SELECT 1 FROM cobrancas cb WHERE cb.cliente_id = c.id AND cb.situacao = 'aberta')
            ORDER BY RAND() LIMIT 18"));
        foreach ($clientes as $i => $c) {
            $venc = date('Y-m-d', strtotime('-' . mt_rand(5, 75) . ' days'));
            $titulos = [];
            for ($t = 0, $n = mt_rand(1, 3); $t < $n; $t++) {
                $titulos[] = ['valor' => mt_rand(300, 4800) + mt_rand(0, 99) / 100, 'vencimento' => date('Y-m-d', strtotime("$venc +" . ($t * 30) . ' days'))];
            }
            $total = array_sum(array_column($titulos, 'valor'));
            $tipo = ['aberta', 'aberta', 'sem_contato', 'promessa', 'aberta'][mt_rand(0, 4)];
            $etapa = (int) ($etapas[$tipo] ?? $entrada);
            $caso = Banco::inserir('cobrancas', ['cliente_id' => $c['id'], 'etapa_id' => $etapa ?: $entrada, 'responsavel_id' => $uid, 'valor_total' => round($total, 2),
                'vencimento_mais_antigo' => $venc, 'origem' => 'manual', 'situacao' => 'aberta', 'simulado' => 1, 'criado_em' => "$venc 09:00:00"]);
            $ids = [];
            foreach ($titulos as $k => $t) {
                $ids[] = Banco::inserir('cobranca_titulos', ['cobranca_id' => $caso, 'documento' => 'NF ' . mt_rand(10000, 99999), 'parcela' => ($k + 1) . '/' . count($titulos),
                    'valor' => round($t['valor'], 2), 'vencimento' => $t['vencimento']]);
            }
            // Tentativas ao longo dos dias (e a ligação de cada uma)
            for ($t = 0, $n = mt_rand(2, 7); $t < $n; $t++) {
                $quando = self::hora(date('Y-m-d', strtotime('-' . mt_rand(0, min(self::DIAS, 70)) . ' days')));
                if ($quando > date('Y-m-d H:i:s')) {
                    continue;
                }
                $resultado = ['Atendeu', 'Não atendeu', 'Caixa postal', 'Respondeu mensagem', 'Não atendeu', 'Mensagem sem resposta'][mt_rand(0, 5)];
                self::evento($c['id'], $uid, 'cobranca', "Cobrança — $resultado", $quando, $resultado === 'Respondeu mensagem' || $resultado === 'Mensagem sem resposta' ? 'WhatsApp' : 'Ligação', ['cobranca_id' => $caso]);
                if (!str_contains($resultado, 'ensagem')) {
                    $atendeu = $resultado === 'Atendeu';
                    self::guardar('ligacoes', ['id_externo' => 'sim-' . bin2hex(random_bytes(6)), 'usuario_id' => $uid, 'cliente_id' => $c['id'], 'numero' => $c['tel'],
                        'situacao' => $atendeu ? 'atendida' : 'nao_atendida', 'inicio' => $quando, 'duracao_seg' => $atendeu ? mt_rand(60, 400) : 0,
                        'gravacao' => $atendeu ? $gravacoes[array_rand($gravacoes)] : null, 'origem' => 'demonstracao', 'simulado' => 1, 'criado_em' => $quando]);
                }
            }
            // Promessas: metade cumprida (título pago até a data), parte quebrada, parte aguardando
            if ($i % 2 === 0) {
                $feita = date('Y-m-d', strtotime('-' . mt_rand(2, 25) . ' days'));
                $prometida = date('Y-m-d', strtotime("$feita +" . mt_rand(2, 10) . ' days'));
                self::evento($c['id'], $uid, 'promessa', 'Prometeu pagar ' . dinheiro($titulos[0]['valor']) . ' em ' . date('d/m/Y', strtotime($prometida)), "$feita 11:00:00", null, ['cobranca_id' => $caso]);
                if ($i % 4 === 0 && $prometida <= date('Y-m-d')) {
                    Banco::executar('UPDATE cobranca_titulos SET pago_em = ? WHERE id = ?', [$prometida, $ids[0]]);
                    self::evento($c['id'], $uid, 'cobranca', 'Título pago · ' . dinheiro($titulos[0]['valor']), "$prometida 16:00:00", null, ['cobranca_id' => $caso]);
                } elseif ($prometida > date('Y-m-d')) {
                    Banco::executar('UPDATE cobrancas SET promessa_em = ?, promessa_valor = ? WHERE id = ?', [$prometida, round($titulos[0]['valor'], 2), $caso]);
                }
            }
            // Alguns títulos pagos sem promessa (recuperado ao longo do período)
            if ($i % 3 === 1 && count($ids) > 1) {
                $pago = date('Y-m-d', strtotime('-' . mt_rand(0, 20) . ' days'));
                Banco::executar('UPDATE cobranca_titulos SET pago_em = ? WHERE id = ?', [$pago, $ids[1]]);
                self::evento($c['id'], $uid, 'cobranca', 'Título pago · ' . dinheiro($titulos[1]['valor']), "$pago 15:00:00", null, ['cobranca_id' => $caso]);
            }
            CobrancaService::recalcular($caso);
        }
    }

    // ------------------------------------------------------------------
    // Produção e Marketing
    // ------------------------------------------------------------------

    private static function producao(?int $quem): void
    {
        foreach (Banco::todos('SELECT DISTINCT produto_id FROM ficha_tecnica ORDER BY RAND() LIMIT 5') as $i => $f) {
            $quando = date('Y-m-d', strtotime('+' . ($i + 1) . ' weekdays'));
            Banco::inserir('ordens_producao', ['produto_id' => $f['produto_id'], 'quantidade' => mt_rand(4, 20) * 10, 'situacao' => 'planejada', 'origem' => 'manual',
                'previsto_para' => $quando, 'lote' => 'SIM-' . date('ymd', strtotime($quando)) . '-' . $f['produto_id'], 'observacoes' => 'Ordem simulada para teste.',
                'criado_por' => $quem, 'simulado' => 1]);
        }
    }

    private static function marketing(?int $quem): void
    {
        $etapas = array_column(Banco::todos('SELECT id FROM mkt_post_etapas ORDER BY ordem'), 'id');
        $temas = ['Lançamento da semana', 'Dica de uso', 'Depoimento de cliente', 'Bastidores da fábrica', 'Promoção para lojistas', 'Curiosidade do produto', 'Receita saudável', 'Data comemorativa'];
        foreach ($temas as $i => $t) {
            Banco::inserir('mkt_posts', ['formato' => ['feed', 'carrossel', 'reels', 'stories'][$i % 4], 'titulo' => "$t (simulado)",
                'legenda' => 'Post de teste gerado pelo simulador.', 'data_publicacao' => date('Y-m-d', strtotime(($i - 3) . ' days')),
                'etapa_id' => $etapas[$i % max(1, count($etapas))], 'responsavel_id' => $quem, 'criado_por' => $quem, 'simulado' => 1]);
        }
    }

    // ------------------------------------------------------------------

    /** Clientes da carteira da pessoa; se tiver poucos, cria 30 clientes simulados para ela */
    private static function clientesDe(int $uid): array
    {
        $sql = "SELECT id, COALESCE(NULLIF(telefone, ''), whatsapp, '') AS tel FROM clientes WHERE responsavel_id = ? AND excluido_em IS NULL LIMIT 120";
        $lista = self::comTelefone(Banco::todos($sql, [$uid]));
        if (count($lista) >= 10) {
            return $lista;
        }
        $nomes = ['Empório', 'Mercado', 'Farmácia', 'Casa', 'Armazém', 'Natural', 'Vida'];
        $sobrenomes = ['Verde', 'Saúde', 'Bem-Estar', 'da Terra', 'Integral', 'do Campo', 'Equilíbrio'];
        $cidades = [['Curitiba', 'PR'], ['Joinville', 'SC'], ['Campinas', 'SP'], ['Goiânia', 'GO'], ['Londrina', 'PR'], ['Blumenau', 'SC']];
        $funcao = CarteiraService::funcaoPrincipal($uid);
        for ($i = 0; $i < 30; $i++) {
            [$cidade, $uf] = $cidades[array_rand($cidades)];
            $id = CarteiraService::criar(['razao_social' => $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)] . ' Teste ' . mt_rand(100, 999) . ' Ltda',
                'cidade' => $cidade, 'uf' => $uf, 'telefone' => '4' . mt_rand(1, 9) . '3' . mt_rand(1000000, 9999999), 'tipo' => 'cliente',
                'responsavel_id' => $uid, 'funcao_id' => $funcao, 'segmento' => 'Produtos naturais', 'ultima_compra_em' => date('Y-m-d', strtotime('-' . mt_rand(5, 120) . ' days'))],
                null, 'Cliente simulado para teste');
            Banco::executar('UPDATE clientes SET simulado = 1 WHERE id = ?', [$id]);
        }
        return self::comTelefone(Banco::todos($sql, [$uid]));
    }

    /** Só os dígitos do telefone (feito no PHP: o MySQL da hospedagem pode não ter REGEXP_REPLACE) */
    private static function comTelefone(array $clientes): array
    {
        return array_map(fn($c) => ['id' => (int) $c['id'], 'tel' => mb_substr(preg_replace('/\D/', '', (string) $c['tel']), 0, 20)], $clientes);
    }

    // ---------- Gravação em lote (muitas linhas de uma vez: rápido em hospedagem compartilhada) ----------

    private static array $fila = [];

    private static function guardar(string $tabela, array $linha): void
    {
        self::$fila[$tabela . '|' . implode(',', array_keys($linha))][] = $linha;
    }

    private static function esvaziar(): void
    {
        foreach (self::$fila as $chave => $linhas) {
            [$tabela] = explode('|', $chave);
            $colunas = array_keys($linhas[0]);
            foreach (array_chunk($linhas, 200) as $bloco) {
                $marcas = '(' . implode(',', array_fill(0, count($colunas), '?')) . ')';
                $valores = [];
                foreach ($bloco as $l) {
                    array_push($valores, ...array_values($l));
                }
                Banco::executar("INSERT INTO $tabela (" . implode(',', $colunas) . ') VALUES ' . implode(',', array_fill(0, count($bloco), $marcas)), $valores);
            }
        }
        self::$fila = [];
    }

    private static function evento(int $clienteId, int $uid, string $tipo, string $descricao, string $quando, ?string $canal = null, ?array $dados = null): void
    {
        self::guardar('cliente_eventos', array_filter(['cliente_id' => $clienteId, 'usuario_id' => $uid, 'tipo' => $tipo, 'canal' => $canal, 'descricao' => $descricao,
            'dados_json' => $dados ? json_encode($dados) : null, 'ocorrido_em' => $quando, 'simulado' => 1], fn($v) => $v !== null));
    }

    private static function hora(string $data): string
    {
        return $data . sprintf(' %02d:%02d:%02d', mt_rand(8, 17), mt_rand(0, 59), mt_rand(0, 59));
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
