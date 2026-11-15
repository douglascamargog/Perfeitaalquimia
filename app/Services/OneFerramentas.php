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
    /** Definições para a IA (nome, descrição e o formato da entrada) */
    public static function definicoes(): array
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
    public static function executar(string $nome, array $e): array
    {
        $permitidas = array_column(self::definicoes(), 'name');
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
