<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Cliente;

/**
 * Consultas que o One pode fazer para responder uma pergunta ("ferramentas" da IA).
 *
 * Todas rodam AQUI no servidor, com o login da pessoa: cada um só enxerga o que já pode ver no sistema
 * (a vendedora só a carteira dela). As de clientes só existem se o admin ligar
 * Configurações › One (IA) › "A IA pode consultar dados de clientes" (LGPD).
 * Cada ferramenta devolve um array simples (vira JSON para a IA). Nunca devolve CNPJ, telefone nem e-mail.
 */
class OneFerramentas
{
    /**
     * Definições para a IA (nome, descrição e o formato da entrada).
     * $liberadas: consultas do especialista (null = as do One geral). Cada consulta ainda confere a permissão da pessoa.
     */
    public static function definicoes(?array $liberadas = null): array
    {
        $todas = self::todas();
        $liberadas ??= ['ajuda_sistema', 'meus_numeros', 'produtos', 'campanhas_ativas', 'buscar_clientes', 'cliente_resumo'];
        $liberadas[] = 'ajuda_sistema'; // todo especialista sabe explicar o sistema
        return array_values(array_filter($todas, fn($d) => in_array($d['name'], $liberadas, true)));
    }

    /** Todas as consultas que a PESSOA pode usar (pelas permissões dela) */
    private static function todas(): array
    {
        $lista = [
            self::def('ajuda_sistema', 'Procura nas aulas "Como usar o One" e no menu como fazer algo no sistema (onde fica, passo a passo). Use para qualquer dúvida de uso do sistema.',
                ['pergunta' => ['type' => 'string', 'description' => 'A dúvida, em poucas palavras']], ['pergunta']),
            self::def('meus_numeros', 'Números da pessoa logada neste mês: meta, vendido, quanto falta, retornos atrasados e de hoje, clientes na recompra, pedidos esperando aprovação.', [], []),
            self::def('produtos', 'Procura produtos do catálogo da empresa (nome, código, embalagem, categoria e para que servem).',
                ['busca' => ['type' => 'string', 'description' => 'Nome, código ou assunto (ex.: magnésio, sono, colágeno)']], ['busca']),
            self::def('campanhas_ativas', 'Campanhas comerciais no ar hoje: faixas (desconto, brinde, frete grátis) e produtos em promoção.', [], []),
        ];
        if (self::podeClientes()) {
            $lista[] = self::def('buscar_clientes', 'Lista clientes da carteira da pessoa (só os que ela pode ver), com filtros. Devolve no máximo 15.', [
                'texto' => ['type' => 'string', 'description' => 'Parte do nome do cliente (opcional, vazio = todos)'],
                'cidade' => ['type' => 'string', 'description' => 'Cidade (opcional)'],
                'uf' => ['type' => 'string', 'description' => 'UF com 2 letras (opcional)'],
                'sem_compra_dias' => ['type' => 'integer', 'description' => 'Só clientes sem comprar há mais de X dias (0 = não filtra)'],
                'comprou_produto' => ['type' => 'string', 'description' => 'Só clientes que já compraram este produto (parte do nome; opcional)'],
            ], ['texto', 'cidade', 'uf', 'sem_compra_dias', 'comprou_produto']);
            $lista[] = self::def('cliente_resumo', 'Resumo de UM cliente da carteira: última compra, de quanto em quanto tempo compra, o que mais compra, últimos pedidos e próximo retorno.',
                ['cliente_id' => ['type' => 'integer', 'description' => 'Id do cliente (vem de buscar_clientes)']], ['cliente_id']);
            $lista[] = self::def('clientes_para_ligar_hoje', 'Para quem ligar hoje: retornos atrasados e de hoje da agenda da pessoa e clientes na recompra (passaram do intervalo de compra), com o que mais compram.', [], []);
        }
        // ----- Especialistas (V15.0) -----
        if (Auth::pode('pedidos.criar') || Auth::pode('cobranca.ver')) {
            $lista[] = self::def('condicoes_comerciais', 'Condições comerciais da empresa: formas e condições de pagamento (parcelas, prazo, desconto, valor mínimo), pedido mínimo por UF e desconto máximo da vendedora.',
                ['uf' => ['type' => 'string', 'description' => 'UF para o pedido mínimo (2 letras; vazio = todas)']], ['uf']);
        }
        if (Auth::pode('prospeccao.usar') || Auth::pode('prospeccao.ver')) {
            $lista[] = self::def('empresas_para_prospectar', 'Empresas ativas da base pública de CNPJs (amostra) numa cidade/UF, com os CNAEs dos clientes atuais. Marca as que já estão na carteira. Devolve no máximo 20, as que NÃO estão na carteira primeiro.', [
                'cidade' => ['type' => 'string', 'description' => 'Cidade (vazio = todas da UF)'],
                'uf' => ['type' => 'string', 'description' => 'UF com 2 letras'],
                'cnae' => ['type' => 'string', 'description' => 'Código ou parte da descrição do CNAE (vazio = todos os CNAEs da prospecção)'],
            ], ['cidade', 'uf', 'cnae']);
        }
        if (Auth::pode('prospeccao.usar') || Auth::pode('painel.ver') || Auth::pode('carteira.ver_todas')) {
            $lista[] = self::def('onde_vendemos', 'Onde a empresa já vende bem: clientes ativos e faturamento dos últimos 12 meses por cidade e por segmento (lojistas, sem e-commerce).',
                ['uf' => ['type' => 'string', 'description' => 'UF (vazio = Brasil)']], ['uf']);
        }
        if (Auth::pode('ecommerce.ver')) {
            $lista[] = self::def('ecommerce_resumo', 'Painel do e-commerce (loja própria + marketplaces) no período: faturamento, pedidos, ticket, canais, produtos mais vendidos, horários de compra, cupons, estados e alertas.',
                ['periodo' => ['type' => 'string', 'enum' => ['hoje', '7d', 'mes', 'mes_ant', '30d', '90d', '12m'], 'description' => 'Período']], ['periodo']);
        }
        if (Auth::pode('marketing.ver') || Auth::pode('painel.ver')) {
            $lista[] = self::def('mais_vendidos', 'Produtos mais vendidos para LOJISTAS (pedidos faturados) no período, com quantidade e valor; e os que caíram em relação ao período anterior.',
                ['periodo' => ['type' => 'string', 'enum' => ['mes', 'mes_anterior', '3m', '12m'], 'description' => 'Período']], ['periodo']);
        }
        if (Auth::pode('marketing.ver')) {
            $lista[] = self::def('datas_e_campanhas', 'Datas comemorativas dos próximos 60 dias (calendário do Marketing), campanhas de marketing planejadas e campanhas comerciais no ar.', [], []);
        }
        if (Auth::pode('producao.ver') || Auth::pode('producao.gerenciar') || Auth::pode('produtos.tecnico')) {
            $lista[] = self::def('materias_primas', 'Matérias-primas cadastradas: unidade, estoque, estoque mínimo, custo, fornecedor, fabricante, categoria, CAS/INS e a validade mais próxima dos lotes. Máximo 25.',
                ['busca' => ['type' => 'string', 'description' => 'Nome, código, categoria ou fornecedor (vazio = todas)']], ['busca']);
            $lista[] = self::def('ficha_de_produto', 'Ficha técnica (fórmula de produção) de um produto: matérias-primas por unidade, perda, custo por unidade e a composição/porção cadastrada.',
                ['produto' => ['type' => 'string', 'description' => 'Nome ou código do produto']], ['produto']);
        }
        if (Auth::pode('formulas.ver') || Auth::pode('formulas.editar')) {
            $lista[] = self::def('formula_aberta', 'Uma fórmula da área de Fórmulas com todas as contas: ingredientes (quantidade, função, % da porção, custo, estoque, fornecedor), massa da porção, ocupação da cápsula, custo por porção/embalagem/lote e faltas de estoque.',
                ['formula_id' => ['type' => 'integer', 'description' => 'Id da fórmula (vem do contexto da tela ou de buscar_formulas)']], ['formula_id']);
            $lista[] = self::def('calcular_formula', 'Recalcula uma fórmula para outro tamanho de lote: custo do lote e o que falta de estoque.',
                ['formula_id' => ['type' => 'integer', 'description' => 'Id da fórmula'], 'lote' => ['type' => 'integer', 'description' => 'Unidades (embalagens) do lote']], ['formula_id', 'lote']);
            $lista[] = self::def('buscar_formulas', 'Lista as fórmulas (última versão) por nome ou código, com situação, forma, porção e custo por embalagem.',
                ['busca' => ['type' => 'string', 'description' => 'Nome ou código F-0001 (vazio = todas)']], ['busca']);
        }
        if (Auth::pode('cobranca.ver') && (bool) Config::get('one.ia_dados_clientes', false)) {
            $lista[] = self::def('casos_de_cobranca', 'Casos de cobrança abertos (da pessoa ou de todos, conforme o acesso): cliente, valor, dias de atraso, etapa, promessa e tentativas. Máximo 20, mais urgentes primeiro.', [], []);
            $lista[] = self::def('mensagens_cobranca', 'Mensagens prontas de cobrança para UM caso (lembrete, segunda via, acordo), já com o nome, o valor e o vencimento.',
                ['caso_id' => ['type' => 'integer', 'description' => 'Id do caso (vem de casos_de_cobranca)']], ['caso_id']);
        }
        if (Auth::pode('terceirizacao.ver') || Auth::pode('terceirizacao.editar')) {
            $lista[] = self::def('clientes_terceirizacao', 'Clientes de terceirização/marca própria com os produtos de cada um (rótulo, preço, estoque de rótulos).', [], []);
            $lista[] = self::def('rotulos_em_falta', 'Produtos de marca própria com rótulos abaixo do mínimo ou que não cobrem as ordens de produção.', [], []);
            $lista[] = self::def('pedidos_terceirizacao', 'Pedidos com produtos terceirizados dos últimos 60 dias: cliente, data, situação e itens.', [], []);
        }
        return $lista;
    }

    private static function def(string $nome, string $descricao, array $props, array $obrigatorios): array
    {
        // Sem parâmetros: o esquema vai sem "properties" (uma lista vazia viraria [] no JSON, e a API espera um objeto)
        $esquema = $props ? ['type' => 'object', 'properties' => $props, 'required' => $obrigatorios, 'additionalProperties' => false]
            : ['type' => 'object', 'additionalProperties' => false];
        return ['name' => $nome, 'description' => $descricao, 'strict' => true, 'inputSchema' => $esquema];
    }

    /** O admin liberou consultar clientes (LGPD) e a pessoa trabalha com carteira? */
    public static function podeClientes(): bool
    {
        return (bool) Config::get('one.ia_dados_clientes', false) && (Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas'));
    }

    /** Executa uma ferramenta (nome vindo da IA). Ferramenta desconhecida ou não liberada → erro, nunca dados. */
    public static function executar(string $nome, array $e, ?array $liberadas = null): array
    {
        $permitidas = array_column(self::definicoes($liberadas), 'name');
        if (!in_array($nome, $permitidas, true)) {
            return ['erro' => 'Consulta não disponível.'];
        }
        return match ($nome) {
            'ajuda_sistema' => self::ajudaSistema((string) ($e['pergunta'] ?? '')),
            'meus_numeros' => self::meusNumeros(),
            'produtos' => self::produtos((string) ($e['busca'] ?? '')),
            'campanhas_ativas' => self::campanhas(),
            'buscar_clientes' => self::buscarClientes($e),
            'cliente_resumo' => self::clienteResumo((int) ($e['cliente_id'] ?? 0)),
            'clientes_para_ligar_hoje' => self::paraLigarHoje(),
            'condicoes_comerciais' => self::condicoes((string) ($e['uf'] ?? '')),
            'empresas_para_prospectar' => self::empresasProspectar($e),
            'onde_vendemos' => self::ondeVendemos((string) ($e['uf'] ?? '')),
            'ecommerce_resumo' => self::ecommerce((string) ($e['periodo'] ?? 'mes')),
            'mais_vendidos' => self::maisVendidos((string) ($e['periodo'] ?? 'mes')),
            'datas_e_campanhas' => self::datasCampanhas(),
            'materias_primas' => self::materiasPrimas((string) ($e['busca'] ?? '')),
            'ficha_de_produto' => self::fichaProduto((string) ($e['produto'] ?? '')),
            'formula_aberta' => FormulaService::paraEspecialista((int) ($e['formula_id'] ?? 0)),
            'calcular_formula' => FormulaService::paraEspecialista((int) ($e['formula_id'] ?? 0), max(1, (int) ($e['lote'] ?? 100))),
            'buscar_formulas' => ['formulas' => array_map(fn($f) => ['id' => (int) $f['id'], 'codigo' => $f['codigo'] . ' v' . $f['versao'], 'nome' => $f['nome'],
                'situacao' => FormulaService::SITUACOES[$f['situacao']][0] ?? $f['situacao'], 'forma' => FormulaService::FORMAS[$f['forma']] ?? $f['forma'],
                'custo_por_embalagem' => dinheiro($f['calculo']['custo_embalagem']), 'link' => '/formulas/' . $f['id']], array_slice(FormulaService::lista(['busca' => mb_substr(trim((string) ($e['busca'] ?? '')), 0, 60)]), 0, 20))],
            'casos_de_cobranca' => self::casosCobranca(),
            'mensagens_cobranca' => self::mensagensCobranca((int) ($e['caso_id'] ?? 0)),
            'clientes_terceirizacao' => self::clientesTerceirizacao(),
            'rotulos_em_falta' => ['produtos' => array_map(fn($p) => ['cliente' => $p['cliente_nome'] ?? null, 'rotulo' => $p['nome_rotulo'] ?? $p['nome'] ?? null,
                'rotulos_em_estoque' => (int) $p['rotulos_estoque'], 'minimo' => (int) $p['rotulos_minimo'], 'em_ordens' => (int) ($p['em_ordens'] ?? 0)], TerceirizacaoService::alertas()), 'link' => '/terceirizacao'],
            'pedidos_terceirizacao' => self::pedidosTerceirizacao(),
            default => ['erro' => 'Consulta não disponível.'],
        };
    }

    // ---------------------------------------------------------------- consultas

    /** Aulas "Como usar o One" + itens do menu que mais combinam com a pergunta */
    public static function ajudaSistema(string $pergunta): array
    {
        $palavras = self::palavras($pergunta);
        $achados = [];
        // Módulos do Treinamento (V13.0) e os tutoriais das telas
        $modulos = Banco::todos('SELECT id, titulo, resumo, conteudo FROM trein_modulos WHERE ativo = 1');
        foreach ($modulos as $m) {
            $texto = trim(preg_replace('/\s+/u', ' ', strip_tags(\App\Services\TreinamentoService::html($m['conteudo']))));
            $pontos = self::pontos($palavras, $m['titulo'] . ' ' . $m['resumo'] . ' ' . $texto, $m['titulo']);
            if ($pontos > 0) {
                $achados[] = ['pontos' => $pontos, 'titulo' => 'Treinamento: ' . $m['titulo'], 'resumo' => mb_strimwidth($texto, 0, 900, '…'), 'link' => '/treinamento/modulo/' . $m['id']];
            }
        }
        foreach (\App\Services\TutorialService::telas() as $rota => $t) {
            if (!\App\Services\TutorialService::podeVer($rota)) {
                continue;
            }
            $texto = implode(' ', array_column($t['passos'], 'texto'));
            $pontos = self::pontos($palavras, $t['titulo'] . ' ' . $texto, $t['titulo']);
            if ($pontos > 0) {
                $achados[] = ['pontos' => $pontos, 'titulo' => 'Tela: ' . $t['titulo'], 'resumo' => mb_strimwidth($texto, 0, 900, '…'), 'link' => $rota];
            }
        }
        foreach (self::PERGUNTAS_COMUNS as [$chaves, $titulo, $resposta, $link]) {
            $pontos = self::pontos($palavras, $chaves . ' ' . $titulo, $chaves);
            if ($pontos > 0) {
                $achados[] = ['pontos' => $pontos + 2, 'titulo' => $titulo, 'resumo' => $resposta, 'link' => $link];
            }
        }
        foreach (require RAIZ . '/app/menu.php' as $item) {
            if (!\App\Controllers\InicioController::visivel($item)) {
                continue; // só telas que a pessoa pode abrir
            }
            $pontos = self::pontos($palavras, $item['titulo'] . ' ' . ($item['descricao'] ?? '') . ' ' . ($item['grupo'] ?? ''), $item['titulo']);
            if ($pontos > 0) {
                $achados[] = ['pontos' => $pontos + 1, 'titulo' => 'Tela: ' . $item['titulo'] . ($item['grupo'] ? ' (menu ' . ($item['grupo'] === 'Vendas' ? 'Comercial' : $item['grupo']) . ')' : ''), 'resumo' => (string) ($item['descricao'] ?? ''), 'link' => $item['url']];
            }
        }
        usort($achados, fn($a, $b) => $b['pontos'] <=> $a['pontos']);
        $achados = array_map(fn($a) => array_diff_key($a, ['pontos' => 1]), array_slice($achados, 0, 4));
        return ['resultados' => $achados, 'dica' => $achados ? null : 'Nada encontrado nas aulas. Sugira abrir Treinamento › Como usar o One.'];
    }

    public static function meusNumeros(): array
    {
        $id = (int) Auth::id();
        $r = ['pessoa' => EquipeService::comoChamar(Auth::usuario())];
        if (Auth::pode('meu_mes.usar')) {
            $m = MetaService::indicadores($id, date('Y-m'));
            $r['meta_do_mes'] = $m['config']['meta'] > 0 ? dinheiro((float) $m['meta']) : 'sem meta definida';
            $r['vendido_no_mes'] = dinheiro((float) $m['vendido']);
            $r['falta'] = $m['config']['meta'] > 0 ? dinheiro((float) $m['falta']) : null;
            $r['precisa_por_dia_util'] = $m['necessario_dia'] !== null ? dinheiro((float) $m['necessario_dia']) : null;
            $r['meta_batida'] = (bool) $m['atingida'];
        }
        if (Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas')) {
            $a = \App\Models\Tarefa::agenda($id);
            $r['retornos_atrasados'] = count($a['atrasadas']);
            $r['retornos_hoje'] = count($a['hoje']);
        }
        if (Auth::pode('recompra.ver')) {
            $r['clientes_na_recompra'] = VendasService::qtdRecompra();
        }
        if (Auth::pode('pedidos.criar')) {
            $r['meus_pedidos_esperando_aprovacao'] = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao' AND (vendedor_id = ? OR criado_por_usuario = ?)", [$id, $id]);
        }
        if (Auth::pode('pedidos.aprovar')) {
            $r['pedidos_para_aprovar'] = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao'");
        }
        return $r;
    }

    public static function produtos(string $busca): array
    {
        $t = '%' . mb_substr(trim($busca), 0, 60) . '%';
        $lista = Banco::todos("SELECT p.codigo, p.nome, p.embalagem, p.chamada, c.nome AS categoria, p.slug FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.ativo = 1 AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.chamada LIKE ? OR c.nome LIKE ?) ORDER BY p.nome LIMIT 10", [$t, $t, $t, $t]);
        return ['produtos' => array_map(fn($p) => ['codigo' => $p['codigo'], 'nome' => $p['nome'], 'embalagem' => $p['embalagem'], 'categoria' => $p['categoria'],
            'para_que_serve' => $p['chamada'], 'link' => '/produtos/' . $p['slug']], $lista)];
    }

    public static function campanhas(): array
    {
        return ['campanhas' => array_map(fn($c) => [
            'nome' => $c['titulo'] ?: $c['nome'], 'periodo' => CampanhaService::periodo($c), 'para_quem' => CampanhaService::publicoTexto($c),
            'faixas' => array_map(fn($f) => 'a partir de ' . dinheiro((float) $f['a_partir_de']) . ': ' . CampanhaService::textoFaixa($f), $c['faixas']),
            'produtos_em_promocao' => array_map(fn($p) => $p['nome'] . ' -' . CampanhaService::pct((float) $p['desconto_pct']), $c['produtos']),
        ], CampanhaService::ativasHoje())];
    }

    private static function buscarClientes(array $e): array
    {
        $dias = max(0, (int) ($e['sem_compra_dias'] ?? 0));
        $f = Cliente::filtrosDe(['q' => (string) ($e['texto'] ?? ''), 'cidade' => (string) ($e['cidade'] ?? ''), 'uf' => strtoupper(mb_substr((string) ($e['uf'] ?? ''), 0, 2)),
            'tipo' => 'cliente', 'compra' => $dias ? 'sem' . $dias : '']);
        [$where, $par] = Cliente::montarWhere($f); // já limita à carteira que a pessoa pode ver
        $produto = trim((string) ($e['comprou_produto'] ?? ''));
        if ($produto !== '') {
            $where .= " AND EXISTS (SELECT 1 FROM pedidos p JOIN pedido_itens i ON i.pedido_id = p.id WHERE p.cliente_id = c.id AND p.situacao = 'faturado' AND i.nome LIKE ?)";
            $par[] = '%' . mb_substr($produto, 0, 60) . '%';
        }
        $total = (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE $where", $par);
        $lista = Banco::todos("SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.cidade, c.uf, c.ultima_compra_em, u.nome AS responsavel
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE $where ORDER BY c.ultima_compra_em DESC, c.id LIMIT 15", $par);
        return ['total_encontrado' => $total, 'mostrando' => count($lista), 'clientes' => array_map(fn($c) => [
            'id' => (int) $c['id'], 'nome' => $c['nome'], 'cidade' => trim($c['cidade'] . '/' . $c['uf'], '/'),
            'ultima_compra' => $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : 'nunca comprou', 'responsavel' => $c['responsavel'],
            'link' => '/carteira/clientes/' . (int) $c['id']], $lista)];
    }

    private static function clienteResumo(int $id): array
    {
        $c = Cliente::buscar($id);
        if (!$c || !Cliente::podeVer($c)) {
            return ['erro' => 'Cliente não encontrado na sua carteira.'];
        }
        $produtos = array_column(Banco::todos("SELECT i.nome, SUM(i.quantidade) AS q FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id
            WHERE p.cliente_id = ? AND p.situacao = 'faturado' GROUP BY i.nome ORDER BY q DESC LIMIT 5", [$id]), 'nome');
        $pedidos = Banco::todos('SELECT id, criado_em, total, situacao FROM pedidos WHERE cliente_id = ? ORDER BY criado_em DESC LIMIT 3', [$id]);
        $retorno = \App\Models\Tarefa::proximoRetorno($id);
        return ['nome' => $c['nome_fantasia'] ?: $c['razao_social'], 'cidade' => trim($c['cidade'] . '/' . $c['uf'], '/'),
            'ultima_compra' => $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : 'nunca comprou',
            'compra_a_cada_dias' => (int) $c['intervalo_medio_dias'] ?: null, 'o_que_mais_compra' => $produtos,
            'ultimos_pedidos' => array_map(fn($p) => '#' . $p['id'] . ' em ' . data_br($p['criado_em']) . ': ' . dinheiro((float) $p['total']) . ' (' . (PedidoService::SITUACOES[$p['situacao']][0] ?? $p['situacao']) . ')', $pedidos),
            'proximo_retorno' => $retorno ? data_br($retorno['vence_em']) . ' — ' . $retorno['titulo'] : null,
            'link' => '/carteira/clientes/' . $id];
    }

    // ---------------------------------------------------------------- consultas dos especialistas (V15.0)

    private static function paraLigarHoje(): array
    {
        $a = \App\Models\Tarefa::agenda((int) Auth::id());
        $tarefa = fn($t) => ['cliente' => $t['cliente'] ?? ($t['razao_social'] ?? null), 'assunto' => $t['titulo'] ?? null,
            'vence' => !empty($t['vence_em']) ? data_br($t['vence_em']) : null, 'link' => !empty($t['cliente_id']) ? '/carteira/clientes/' . (int) $t['cliente_id'] : '/agenda'];
        $recompra = Auth::pode('recompra.ver') ? array_slice(VendasService::recompra(), 0, 12) : [];
        return [
            'retornos_atrasados' => array_map($tarefa, array_slice($a['atrasadas'], 0, 10)),
            'retornos_de_hoje' => array_map($tarefa, array_slice($a['hoje'], 0, 10)),
            'recompra' => array_map(fn($c) => ['cliente' => $c['nome_fantasia'] ?: $c['razao_social'], 'cidade' => trim($c['cidade'] . '/' . $c['uf'], '/'),
                'ultima_compra' => $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : null, 'compra_a_cada_dias' => (int) $c['intervalo_medio_dias'],
                'dias_de_atraso' => (int) $c['dias_atraso'], 'ticket_medio' => $c['ticket'] ? dinheiro((float) $c['ticket']) : null,
                'mais_compra' => $c['produtos'] ?? null, 'link' => '/carteira/clientes/' . (int) $c['id']], $recompra),
            'links' => ['agenda' => '/agenda', 'recompra' => '/recompra'],
        ];
    }

    private static function condicoes(string $uf): array
    {
        $uf = strtoupper(mb_substr(trim($uf), 0, 2));
        $condicoes = Banco::todos('SELECT f.nome AS forma, c.nome, c.parcelas, c.prazo_dias, c.desconto_pct, c.valor_minimo, f.bloqueia_com_atraso
            FROM condicoes_pagamento c JOIN formas_pagamento f ON f.id = c.forma_pagamento_id WHERE c.ativa = 1 AND f.ativa = 1 ORDER BY f.ordem, c.valor_minimo');
        $minimos = Banco::todos('SELECT ru.uf, COALESCE(ru.pedido_minimo, r.pedido_minimo) AS minimo, r.nome AS regiao FROM regiao_ufs ru JOIN regioes r ON r.id = ru.regiao_id'
            . ($uf !== '' ? ' WHERE ru.uf = ?' : '') . ' ORDER BY ru.uf', $uf !== '' ? [$uf] : []);
        return [
            'condicoes_de_pagamento' => array_map(fn($c) => ['forma' => $c['forma'], 'condicao' => $c['nome'], 'parcelas' => (int) $c['parcelas'], 'prazo_dias' => (int) $c['prazo_dias'],
                'desconto' => (float) $c['desconto_pct'] ? number_format((float) $c['desconto_pct'], 1, ',', '.') . '%' : null,
                'a_partir_de' => (float) $c['valor_minimo'] ? dinheiro((float) $c['valor_minimo']) : null,
                'bloqueada_para_quem_tem_atraso' => (bool) $c['bloqueia_com_atraso']], $condicoes),
            'pedido_minimo_por_uf' => array_map(fn($m) => ['uf' => $m['uf'], 'regiao' => $m['regiao'], 'minimo' => dinheiro((float) $m['minimo'])], $minimos),
            'desconto_maximo_da_vendedora' => number_format((float) Config::get('pedidos.desconto_max_pct', 0), 1, ',', '.') . '%',
            'regra' => 'Cliente com título em atraso: boleto bloqueado, só Pix ou cartão.',
        ];
    }

    private static function empresasProspectar(array $e): array
    {
        $uf = strtoupper(mb_substr(trim((string) ($e['uf'] ?? '')), 0, 2));
        $cidade = trim((string) ($e['cidade'] ?? ''));
        $cnae = trim((string) ($e['cnae'] ?? ''));
        $w = ["p.situacao IN ('ATIVA', 'Ativa', 'ativa', '02')"];
        $par = [];
        if ($uf !== '') { $w[] = 'p.uf = ?'; $par[] = $uf; }
        if ($cidade !== '') { $w[] = 'p.cidade LIKE ?'; $par[] = '%' . mb_substr($cidade, 0, 60) . '%'; }
        if ($cnae !== '') { $w[] = '(p.cnae LIKE ? OR p.cnae_descricao LIKE ?)'; array_push($par, preg_replace('/\D/', '', $cnae) . '%', '%' . mb_substr($cnae, 0, 60) . '%'); }
        $onde = implode(' AND ', $w);
        $base = "FROM prospeccao_empresas p LEFT JOIN clientes c ON c.cnpj = p.cnpj AND c.excluido_em IS NULL WHERE $onde";
        $total = (int) Banco::valor("SELECT COUNT(*) $base", $par);
        $lista = Banco::todos("SELECT COALESCE(NULLIF(p.nome_fantasia, ''), p.razao_social) AS nome, p.cidade, p.uf, p.bairro, p.cnae, p.cnae_descricao, p.porte, p.abertura,
                c.id AS cliente_id FROM prospeccao_empresas p LEFT JOIN clientes c ON c.cnpj = p.cnpj AND c.excluido_em IS NULL
            WHERE $onde ORDER BY c.id IS NOT NULL, p.porte DESC, p.abertura LIMIT 20", $par);
        $cidades = Banco::todos("SELECT p.cidade, p.uf, COUNT(*) AS n FROM prospeccao_empresas p WHERE p.uf = ? GROUP BY p.cidade, p.uf ORDER BY n DESC LIMIT 10", [$uf ?: 'RS']);
        return ['total_encontrado' => $total, 'empresas' => array_map(fn($p) => ['nome' => $p['nome'], 'cidade' => $p['cidade'] . '/' . $p['uf'], 'bairro' => $p['bairro'],
            'atividade' => $p['cnae_descricao'] ?: $p['cnae'], 'porte' => $p['porte'], 'aberta_em' => $p['abertura'] ? data_br($p['abertura']) : null,
            'ja_na_carteira' => (bool) $p['cliente_id']], $lista),
            'cidades_com_mais_empresas_na_base' => array_map(fn($c) => $c['cidade'] . '/' . $c['uf'] . ' (' . $c['n'] . ')', $cidades),
            'observacao' => 'Base de demonstração: amostra do RS. Para enviar ao funil, use a tela de Prospecção.', 'link' => '/prospeccao'];
    }

    private static function ondeVendemos(string $uf): array
    {
        $uf = strtoupper(mb_substr(trim($uf), 0, 2));
        $f = ['de' => date('Y-m-d', strtotime('-12 months')), 'ate' => date('Y-m-d'), 'uf' => $uf];
        $cidades = Banco::todos("SELECT c.cidade, c.uf, COUNT(DISTINCT c.id) AS clientes, SUM(p.total) AS faturamento FROM pedidos p JOIN clientes c ON c.id = p.cliente_id AND c.tipo <> 'consumidor'
            WHERE p.situacao = 'faturado' AND p.criado_em >= ?" . ($uf ? ' AND c.uf = ?' : '') . " GROUP BY c.cidade, c.uf ORDER BY faturamento DESC LIMIT 15",
            array_filter([$f['de'], $uf]));
        $segmentos = Banco::todos("SELECT COALESCE(s.nome, 'Sem segmento') AS segmento, COUNT(DISTINCT c.id) AS clientes, SUM(p.total) AS faturamento FROM pedidos p
            JOIN clientes c ON c.id = p.cliente_id AND c.tipo <> 'consumidor' LEFT JOIN segmentos s ON s.id = c.segmento_id
            WHERE p.situacao = 'faturado' AND p.criado_em >= ?" . ($uf ? ' AND c.uf = ?' : '') . " GROUP BY segmento ORDER BY faturamento DESC", array_filter([$f['de'], $uf]));
        return ['ultimos_12_meses' => true,
            'cidades' => array_map(fn($c) => ['cidade' => $c['cidade'] . '/' . $c['uf'], 'clientes_que_compraram' => (int) $c['clientes'], 'faturamento' => dinheiro((float) $c['faturamento'])], $cidades),
            'segmentos' => array_map(fn($s) => ['segmento' => $s['segmento'], 'clientes' => (int) $s['clientes'], 'faturamento' => dinheiro((float) $s['faturamento'])], $segmentos)];
    }

    private static function ecommerce(string $periodo): array
    {
        $p = EcommerceService::painel(EcommerceService::filtros(['periodo' => $periodo]));
        $a = $p['atual'];
        return ['periodo' => data_br($p['filtros']['de']) . ' a ' . data_br($p['filtros']['ate']), 'dados_simulados' => $p['simulado'],
            'faturamento' => dinheiro((float) $a['faturamento']), 'variacao_faturamento_pct' => $p['variacao']['faturamento'] !== null ? round($p['variacao']['faturamento'], 1) : null,
            'pedidos' => (int) $a['pedidos'], 'ticket_medio' => dinheiro((float) $a['ticket']), 'cancelados_pct' => round((float) $a['cancelados_pct'], 1),
            'clientes_novos' => (int) $a['novos'], 'clientes_que_voltaram' => (int) $a['recompra'],
            'canais' => array_map(fn($c) => ['canal' => $c['nome'], 'faturamento' => dinheiro((float) $c['faturamento']), 'pedidos' => (int) $c['pedidos'], 'participacao_pct' => round($c['participacao'], 1)], $p['canais']),
            'mais_vendidos' => array_map(fn($x) => ['produto' => $x['nome'], 'faturamento' => dinheiro((float) $x['faturamento']), 'unidades' => (int) $x['quantidade']], array_slice($p['produtos'], 0, 10)),
            'pararam_de_vender' => array_column($p['parados'], 'nome'),
            'horarios_com_mais_pedidos' => array_map(fn($c) => ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'][$c['dia_semana']] . ' ' . $c['hora'] . 'h (' . $c['pedidos'] . ')',
                array_slice(self::ordenar($p['calor'], 'pedidos'), 0, 6)),
            'cupons' => array_map(fn($c) => $c['cupom'] . ': ' . $c['pedidos'] . ' pedido(s)', $p['cupons']),
            'estados' => array_map(fn($u) => $u['uf'] . ': ' . dinheiro((float) $u['faturamento']), array_slice($p['ufs'], 0, 8)),
            'alertas' => array_map(fn($x) => $x[1], $p['alertas']), 'link' => '/marketing/ecommerce?periodo=' . $periodo];
    }

    /** 1234.5 → "1.234,5" (sem zeros sobrando) */
    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }

    private static function ordenar(array $l, string $campo): array
    {
        usort($l, fn($a, $b) => $b[$campo] <=> $a[$campo]);
        return $l;
    }

    private static function maisVendidos(string $periodo): array
    {
        [$de, $ate] = VendasService::periodo($periodo);
        [$deA, $ateA] = VendasService::anterior($de, $ate);
        $agora = VendasService::ranking(['de' => $de, 'ate' => $ate], 'produto', 15);
        $antes = array_column(VendasService::ranking(['de' => $deA, 'ate' => $ateA], 'produto', 200), 'valor', 'nome');
        return ['periodo' => data_br($de) . ' a ' . data_br($ate), 'produtos' => array_map(fn($p) => ['produto' => $p['nome'], 'valor' => dinheiro($p['valor']),
            'unidades' => (int) $p['quantidade'], 'variacao_pct' => !empty($antes[$p['nome']]) ? round(($p['valor'] - $antes[$p['nome']]) / $antes[$p['nome']] * 100, 1) : null], $agora)];
    }

    private static function datasCampanhas(): array
    {
        $datas = [];
        foreach ((array) Config::get('marketing.datas_comemorativas', []) as $d) {
            if (preg_match('/^(\d{2})\/(\d{2})\s+(.+)$/u', trim($d), $m)) {
                $quando = strtotime(date('Y') . "-{$m[2]}-{$m[1]}");
                if ($quando < strtotime('today')) {
                    $quando = strtotime('+1 year', $quando);
                }
                if ($quando <= strtotime('+60 days')) {
                    $datas[$quando] = date('d/m', $quando) . ' ' . $m[3];
                }
            }
        }
        ksort($datas);
        $mkt = Banco::todos("SELECT nome, objetivo, inicio, fim, situacao FROM mkt_campanhas WHERE fim >= CURDATE() ORDER BY inicio LIMIT 10");
        return ['datas_proximos_60_dias' => array_values($datas),
            'campanhas_de_marketing' => array_map(fn($c) => ['nome' => $c['nome'], 'objetivo' => $c['objetivo'], 'periodo' => data_br($c['inicio']) . ' a ' . data_br($c['fim']), 'situacao' => $c['situacao']], $mkt),
            'campanhas_comerciais_no_ar' => self::campanhas()['campanhas']];
    }

    private static function materiasPrimas(string $busca): array
    {
        $t = '%' . mb_substr(trim($busca), 0, 60) . '%';
        $lista = Banco::todos("SELECT m.id, m.codigo, m.nome, m.unidade, m.tipo, m.estoque, m.estoque_minimo, m.custo_unitario, m.fornecedor, m.fabricante, m.categoria, m.cas_ins,
                (SELECT MIN(l.validade) FROM mp_lotes l WHERE l.materia_prima_id = m.id AND l.saldo > 0) AS validade_mais_proxima
            FROM materias_primas m WHERE m.ativo = 1 AND (m.nome LIKE ? OR m.codigo LIKE ? OR m.categoria LIKE ? OR m.fornecedor LIKE ? OR m.fabricante LIKE ?)
            ORDER BY m.nome LIMIT 25", [$t, $t, $t, $t, $t]);
        return ['total' => count($lista), 'materias_primas' => array_map(fn($m) => ['id' => (int) $m['id'], 'codigo' => $m['codigo'], 'nome' => $m['nome'], 'unidade' => $m['unidade'],
            'tipo' => $m['tipo'], 'estoque' => self::num((float) $m['estoque']) . ' ' . $m['unidade'], 'estoque_minimo' => self::num((float) $m['estoque_minimo']) . ' ' . $m['unidade'],
            'custo_por_unidade' => (float) $m['custo_unitario'] ? dinheiro((float) $m['custo_unitario']) . '/' . $m['unidade'] : 'sem custo cadastrado',
            'fornecedor' => $m['fornecedor'], 'fabricante' => $m['fabricante'], 'categoria' => $m['categoria'], 'cas_ins' => $m['cas_ins'],
            'validade_mais_proxima' => $m['validade_mais_proxima'] ? data_br($m['validade_mais_proxima']) : null], $lista), 'link' => '/producao/materias'];
    }

    private static function fichaProduto(string $produto): array
    {
        $t = '%' . mb_substr(trim($produto), 0, 60) . '%';
        $p = Banco::um('SELECT p.id, p.nome, p.codigo, p.embalagem, p.slug, n.porcao, n.porcoes_embalagem FROM produtos p LEFT JOIN produto_nutricional n ON n.produto_id = p.id
            WHERE p.nome LIKE ? OR p.codigo LIKE ? ORDER BY EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id) DESC, p.ativo DESC, p.nome LIMIT 1', [$t, $t]);
        if (!$p) {
            return ['erro' => 'Produto não encontrado.'];
        }
        $itens = Banco::todos('SELECT m.nome, m.unidade, f.quantidade, f.perda_pct, m.custo_unitario FROM ficha_tecnica f JOIN materias_primas m ON m.id = f.materia_prima_id WHERE f.produto_id = ? ORDER BY f.quantidade DESC', [$p['id']]);
        $custo = 0.0;
        $linhas = [];
        foreach ($itens as $i) {
            $qtd = (float) $i['quantidade'] * (1 + (float) $i['perda_pct'] / 100);
            $custo += $qtd * (float) $i['custo_unitario'];
            $linhas[] = ['materia_prima' => $i['nome'], 'por_unidade' => rtrim(rtrim(number_format((float) $i['quantidade'], 6, ',', ''), '0'), ',') . ' ' . $i['unidade'],
                'perda_pct' => (float) $i['perda_pct'], 'custo' => dinheiro($qtd * (float) $i['custo_unitario'])];
        }
        return ['produto' => $p['nome'], 'codigo' => $p['codigo'], 'embalagem' => $p['embalagem'], 'porcao' => $p['porcao'], 'porcoes_por_embalagem' => $p['porcoes_embalagem'],
            'ficha_por_unidade_fabricada' => $linhas, 'custo_materia_prima_por_unidade' => dinheiro($custo), 'link' => '/producao/fichas'];
    }

    private static function casosCobranca(): array
    {
        $w = "co.situacao = 'aberta'";
        $par = [];
        if (!Auth::pode('cobranca.gerenciar')) {
            $w .= ' AND co.responsavel_id = ?';
            $par[] = Auth::id();
        }
        $casos = Banco::todos("SELECT co.id, co.cliente_id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.cidade, c.uf, co.valor_total, co.vencimento_mais_antigo,
                co.promessa_em, co.promessa_valor, co.tentativas_sem_sucesso, co.ultimo_contato_em, e.nome AS etapa, c.ultima_compra_em
            FROM cobrancas co JOIN clientes c ON c.id = co.cliente_id LEFT JOIN cobranca_etapas e ON e.id = co.etapa_id
            WHERE $w ORDER BY (co.promessa_em IS NOT NULL AND co.promessa_em <= CURDATE()) DESC, co.valor_total DESC LIMIT 20", $par);
        return ['casos' => array_map(fn($c) => ['caso_id' => (int) $c['id'], 'cliente' => $c['cliente'], 'cidade' => trim($c['cidade'] . '/' . $c['uf'], '/'),
            'valor' => dinheiro((float) $c['valor_total']), 'dias_de_atraso' => $c['vencimento_mais_antigo'] ? max(0, (int) ((strtotime('today') - strtotime($c['vencimento_mais_antigo'])) / 86400)) : null,
            'etapa' => $c['etapa'], 'promessa' => $c['promessa_em'] ? data_br($c['promessa_em']) . ($c['promessa_valor'] ? ' de ' . dinheiro((float) $c['promessa_valor']) : '') : null,
            'tentativas_sem_sucesso' => (int) $c['tentativas_sem_sucesso'], 'ultimo_contato' => $c['ultimo_contato_em'] ? data_br($c['ultimo_contato_em']) : null,
            'ainda_compra' => $c['ultima_compra_em'] && strtotime($c['ultima_compra_em']) > strtotime('-90 days'),
            'link' => '/carteira/clientes/' . (int) $c['cliente_id']], $casos), 'tela_da_cobranca' => '/cobranca'];
    }

    private static function mensagensCobranca(int $id): array
    {
        $caso = Banco::um('SELECT co.*, c.razao_social, c.nome_fantasia FROM cobrancas co JOIN clientes c ON c.id = co.cliente_id WHERE co.id = ?', [$id]);
        if (!$caso || (!Auth::pode('cobranca.gerenciar') && (int) $caso['responsavel_id'] !== (int) Auth::id())) {
            return ['erro' => 'Caso não encontrado.'];
        }
        return ['mensagens' => CobrancaService::mensagens($caso)];
    }

    private static function clientesTerceirizacao(): array
    {
        $porCliente = [];
        foreach (TerceirizacaoService::produtos() as $p) {
            $porCliente[$p['cliente_nome'] ?? '—'][] = ['rotulo' => $p['nome_rotulo'], 'produto_base' => $p['produto_nome'] ?? null, 'preco' => dinheiro((float) $p['preco_final']),
                'rotulos_em_estoque' => (int) $p['rotulos_estoque'], 'minimo' => (int) $p['rotulos_minimo']];
        }
        return ['clientes' => array_map(fn($c, $l) => ['cliente' => $c, 'produtos' => $l], array_keys($porCliente), $porCliente), 'link' => '/terceirizacao'];
    }

    private static function pedidosTerceirizacao(): array
    {
        $pedidos = Banco::todos("SELECT p.id, p.criado_em, p.situacao, p.total, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente,
                GROUP_CONCAT(CONCAT(i.quantidade, '× ', i.nome) SEPARATOR '; ') AS itens
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id JOIN pedido_itens i ON i.pedido_id = p.id
            WHERE i.terceirizacao_produto_id IS NOT NULL AND p.criado_em >= CURDATE() - INTERVAL 60 DAY GROUP BY p.id ORDER BY p.criado_em DESC LIMIT 20");
        return ['pedidos' => array_map(fn($p) => ['pedido' => '#' . $p['id'], 'cliente' => $p['cliente'], 'data' => data_br($p['criado_em']),
            'situacao' => PedidoService::SITUACOES[$p['situacao']][0] ?? $p['situacao'], 'total' => dinheiro((float) $p['total']), 'itens' => $p['itens'], 'link' => '/pedidos/' . $p['id']], $pedidos)];
    }

    /** Dúvidas rápidas que não têm aula própria: [palavras-chave, título, resposta, link] */
    private const PERGUNTAS_COMUNS = [
        ['senha trocar mudar esqueci', 'Trocar a senha', 'Clique no seu nome, no alto da tela, para abrir o Meu perfil, e use "Trocar minha senha". Esqueceu a senha? Peça para quem administra o sistema gerar uma provisória.', '/minha-senha'],
        ['foto perfil apelido aniversario', 'Foto e dados do seu perfil', 'Clique no seu nome, no alto da tela (Meu perfil): dá para trocar a foto, o apelido, o aniversário e personalizar o seu perfil para a equipe.', '/meu-perfil'],
        ['one silenciar quieto balao parar falar', 'Deixar o One mais quieto', 'Em Meu perfil › O One, desmarque "o One pode puxar conversa sozinho". Ele continua ajudando quando você clica nele. No balão, "Hoje não" cala só aquela tela por hoje.', '/meu-perfil#one'],
        ['cores tema aparencia escuro', 'Cores do sistema', 'Em Meu perfil › Aparência você escolhe as cores do sistema só para você.', '/meu-perfil'],
        ['setor trocar televendas externo', 'Trocar de setor', 'No menu, em Comercial, use o campo "Setor" no alto do grupo (aparece para quem trabalha em mais de um setor). As abas mostram os clientes do setor escolhido.', '/carteira'],
        ['sair logout deslogar', 'Sair do sistema', 'O botão de sair fica no canto de cima, à direita (a seta saindo da porta).', '/'],
    ];

    // ---------------------------------------------------------------- ajudantes da busca

    private static function palavras(string $texto): array
    {
        $t = self::semAcento($texto);
        $fora = ['como', 'onde', 'para', 'que', 'uma', 'um', 'fazer', 'faco', 'eu', 'meu', 'minha', 'meus', 'minhas', 'qual', 'quais', 'tem', 'com', 'dos', 'das', 'nos', 'nas', 'pelo', 'pela', 'sobre', 'esta', 'isso', 'posso', 'consigo', 'fica'];
        return array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', $t), fn($p) => strlen($p) >= 3 && !in_array($p, $fora, true))));
    }

    private static function pontos(array $palavras, string $texto, string $titulo): int
    {
        $t = self::semAcento($texto);
        $tt = self::semAcento($titulo);
        $n = 0;
        foreach ($palavras as $p) {
            $raiz = strlen($p) > 5 ? substr($p, 0, -2) : $p; // pedido/pedidos, senha/senhas
            if (str_contains($t, $raiz)) {
                $n += str_contains($tt, $raiz) ? 3 : 1;
            }
        }
        return $n;
    }

    private static function semAcento(string $s): string
    {
        $s = mb_strtolower($s);
        return strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    }
}
