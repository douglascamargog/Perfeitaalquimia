<?php
namespace App\Services;

use App\Controllers\CarteiraController;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Tarefa;

/**
 * One — o ajudante do sistema.
 *
 * Monta as "falas" do One com base nos dados da pessoa logada: tarefas do dia, meta do mês,
 * meta da equipe, clientes que passaram do prazo de recompra, leads para prospectar e Academia.
 * Respeita as permissões: cada pessoa só recebe dicas do que pode ver.
 *
 * Cada fala: ['humor' => ola|pensando|feliz|ops|ideia|piscando, 'texto' => '...', 'link' => '/...', 'acao' => 'Abrir']
 * Para criar uma dica nova, acrescente um método aqui e chame em falas().
 */
class OneAssistente
{
    /** Saudação conforme o horário */
    public static function saudacao(): array
    {
        $hora = (int) date('G');
        $periodo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
        $nome = EquipeService::comoChamar(Auth::usuario()); // o apelido da própria pessoa
        return ['humor' => 'ola', 'texto' => "$periodo, $nome! Eu sou o One. Separei o que merece sua atenção hoje."];
    }

    /**
     * Falas do One. $pagina = endereço atual (para dar prioridade às dicas daquela tela).
     */
    public static function falas(string $pagina = '/'): array
    {
        $falas = [];
        $id = (int) Auth::id();
        $falas[] = self::aniversario($id);
        // Cada dica aparece só para quem ACOMPANHA a área (foco: funções ou Meu perfil) E TEM ACESSO a ela
        $comercial = Foco::tem('comercial');

        if ($comercial && (Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas'))) {
            $falas[] = self::tarefas($id);
            $falas[] = self::recompra();
            $falas[] = self::prospeccao();
            $semRetorno = LeadService::semRetorno($id);
            $falas[] = $semRetorno ? ['humor' => 'ops', 'texto' => "$semRetorno lead(s) do site ou de anúncio esperando seu retorno. Quem responde no mesmo dia vende mais!", 'link' => '/agenda', 'acao' => 'Ver retornos'] : null;
        }
        if (Auth::pode('pedidos.aprovar') && Foco::tem('aprovacao', 'diretoria')) {
            $qtd = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao'");
            $falas[] = $qtd ? ['humor' => 'ideia', 'texto' => "$qtd pedido(s) aguardam a sua aprovação. O cliente está esperando!", 'link' => '/aprovacao', 'acao' => 'Aprovar pedidos'] : null;
        }
        if (Auth::pode('cobranca.ver') && Foco::tem('cobranca')) {
            $falas[] = self::promessasHoje();
            $falas[] = self::semContato();
        }
        if (Auth::pode('marketing.aprovar') && Foco::tem('marketing')) {
            $falas[] = self::postsParaAprovar();
        }
        if (Auth::pode('leads.ver') && Foco::tem('marketing')) {
            $semana = (int) Banco::valor("SELECT COUNT(*) FROM leads_recebidos WHERE resultado IN ('criado', 'existente') AND criado_em >= CURDATE() - INTERVAL 6 DAY");
            $falas[] = $semana ? ['humor' => 'feliz', 'texto' => "$semana lead(s) chegaram pelo site e pelos anúncios nos últimos 7 dias. Veja quais campanhas convertem mais.", 'link' => '/leads', 'acao' => 'Leads do site'] : null;
        }
        if (Auth::pode('marketing.editar') && Foco::tem('marketing')) {
            $falas[] = self::semanaQueVem();
        }
        if (Auth::pode('producao.gerenciar') && Foco::tem('producao')) {
            $falas = array_merge($falas, self::producao());
        }
        // Produtos: documentos vencidos ou vencendo (Responsável Técnica)
        if (Auth::pode('produtos.tecnico') && Foco::tem('producao') && ($docs = ProdutoService::documentosVencendo(99))) {
            $falas[] = ['humor' => $docs[0]['validade'] < date('Y-m-d') ? 'bravo' : 'ops', 'texto' => count($docs) . ' documento(s) de produto vencido(s) ou vencendo (o primeiro: ' . $docs[0]['titulo'] . ' — ' . $docs[0]['produto'] . ', ' . data_br($docs[0]['validade']) . ').',
                'link' => '/produtos?situacao=vencendo', 'acao' => 'Ver documentos'];
        }
        // Matérias-primas: laudos e documentos vencidos ou vencendo (Produção e Qualidade)
        if (((Auth::pode('producao.gerenciar') && Foco::tem('producao')) || \App\Models\Usuario::temFuncao((int) Auth::id(), 'qualidade'))
            && ($docsMp = ItemCompraService::documentosVencendo(99))) {
            $falas[] = ['humor' => $docsMp[0]['validade'] < date('Y-m-d') ? 'bravo' : 'ops', 'texto' => count($docsMp) . ' laudo(s) ou documento(s) de matéria-prima vencido(s) ou vencendo (o primeiro: ' . $docsMp[0]['item'] . ', ' . data_br($docsMp[0]['validade']) . ').',
                'link' => '/producao/materias/' . (int) $docsMp[0]['item_id'] . '?aba=documentos', 'acao' => 'Ver documento'];
        }
        // Desenvolvimento de Produto: projetos esperando a pessoa
        if ((Auth::pode('desenvolvimento.ver') || Auth::pode('desenvolvimento.gerenciar')) && Foco::tem('desenvolvimento')
            && ($dp = DesenvolvimentoService::minhas((int) Auth::id()))) {
            $falas[] = ['humor' => 'ideia', 'texto' => count($dp) . ' projeto(s) de produto novo esperando você (ex.: "' . $dp[0]['nome'] . '", etapa ' . $dp[0]['etapa'] . ').',
                'link' => '/desenvolvimento/minhas', 'acao' => 'Ver minhas tarefas'];
        }
        // Depósito: pedidos para separar e compras chegando
        if (Auth::pode('deposito.operar') && Foco::tem('deposito')) {
            $dep = DepositoService::resumo();
            if ($dep['a_separar'] + $dep['separando'] + $dep['separados'] > 0) {
                $falas[] = ['humor' => $dep['atrasados'] ? 'ops' : 'ideia', 'texto' => ($dep['a_separar'] + $dep['separando']) . ' pedido(s) para separar e ' . $dep['separados'] . ' para despachar'
                    . ($dep['atrasados'] ? ' · ' . $dep['atrasados'] . ' atrasado(s)' : '') . '.', 'link' => '/deposito', 'acao' => 'Abrir a separação'];
            }
            if ($dep['receber']) {
                $falas[] = ['humor' => 'ideia', 'texto' => $dep['receber'] . ' compra(s) da Qualidade para chegar: confira e dê entrada quando chegar.', 'link' => '/deposito/recebimento', 'acao' => 'Recebimento'];
            }
        }
        // Compras: orçamentos esperando a minha aprovação; para a Qualidade, o que comprar ou refazer
        if ($n = CompraService::qtdParaAprovar((int) Auth::id())) {
            $falas[] = ['humor' => 'ideia', 'texto' => "$n orçamento(s) de compra esperando a sua aprovação.", 'link' => '/compras?aba=minhas', 'acao' => 'Ver orçamentos'];
        }
        if (Auth::pode('compras.solicitar') && Foco::tem('compras') && ($n = CompraService::qtdParaAgir())) {
            $falas[] = ['humor' => 'ideia', 'texto' => "$n orçamento(s) aprovado(s) para comprar ou com pedido de outra cotação.", 'link' => '/compras', 'acao' => 'Ver orçamentos'];
        }
        // Terceirização: rótulos de clientes que não dão para produzir
        if ((Auth::pode('terceirizacao.ver') || Auth::pode('terceirizacao.editar')) && Foco::tem('terceirizacao')
            && ($rot = \App\Services\TerceirizacaoService::alertas())) {
            $falas[] = ['humor' => 'ops', 'texto' => count($rot) . ' produto(s) de clientes com rótulos abaixo do mínimo ou sem rótulo para as ordens planejadas. Avise o cliente para enviar.',
                'link' => '/terceirizacao/rotulos?situacao=alerta', 'acao' => 'Ver rótulos'];
        }
        if ($comercial && Auth::pode('meu_mes.usar')) {
            $falas[] = self::metaIndividual($id);
            $falas[] = self::metaEquipe();
        }
        if (Auth::pode('usuarios.gerenciar') && Foco::tem('diretoria')) {
            $falas[] = ['humor' => 'piscando', 'texto' => 'Gente nova na equipe? Crie o login em Usuários e marque a função da pessoa: as filas e regras seguem a função.', 'link' => '/usuarios', 'acao' => 'Usuários'];
        }

        // Dicas de todos os setores (V12.6): cada um recebe as da sua área, mais as que valem para todo mundo
        $falas = array_merge($falas, self::setores($id), self::paraTodos($id));
        $falas[] = self::academia($id); // próximo módulo do Treinamento (todos os setores)

        $falas = array_values(array_filter($falas));
        // Agenda e recompra ganham botões que já fazem a coisa (abrir o cliente, ligar, WhatsApp, deixar para amanhã)
        foreach ($falas as &$f) {
            if (empty($f['acoes']) && in_array($f['link'] ?? '', ['/agenda', '/recompra'], true) && ($b = OneTela::balao($f['link'], [], false))) {
                $f['acoes'] = $b['acoes'];
            }
        }
        unset($f);
        // Dicas da tela atual aparecem primeiro
        usort($falas, fn($a, $b) => (int) self::daPagina($b, $pagina) <=> (int) self::daPagina($a, $pagina));
        return $falas ?: [['humor' => 'feliz', 'texto' => 'Tudo em ordem por aqui. Quando houver algo importante, eu aviso!']];
    }

    /** Dicas por setor (além das que já existiam): Marketing, Produção, Técnica, Financeiro, Depósito, Diretoria, Gestão */
    private static function setores(int $id): array
    {
        $f = [];
        // Marketing: pedidos dos outros setores e tarefas atrasadas
        if (Auth::pode('marketing.ver') && Foco::tem('marketing')) {
            $r = MarketingTarefaService::resumo();
            if ($r['atrasadas']) {
                $f[] = ['humor' => 'ops', 'texto' => "{$r['atrasadas']} tarefa(s) do Marketing passaram do prazo. Que tal começar por elas?", 'link' => '/marketing/inicio', 'acao' => 'Ver tarefas'];
            }
            if ($r['pedidos_novos']) {
                $f[] = ['humor' => 'ideia', 'texto' => "{$r['pedidos_novos']} pedido(s) de outros setores esperando o Marketing começar.", 'link' => '/marketing/inicio', 'acao' => 'Ver pedidos'];
            }
            if (!$r['posts_semana'] && Auth::pode('marketing.editar')) {
                $f[] = ['humor' => 'pensando', 'texto' => 'Ainda não há posts marcados para esta semana no mural. Bora planejar?', 'link' => '/marketing', 'acao' => 'Abrir o mural'];
            }
        }
        // Produção: ordens atrasadas / de hoje
        if ((Auth::pode('producao.gerenciar') || Auth::pode('producao.ver')) && Foco::tem('producao')) {
            $op = Banco::um("SELECT SUM(previsto_para < CURDATE()) AS atrasadas, SUM(previsto_para = CURDATE()) AS hoje FROM ordens_producao WHERE situacao = 'planejada'");
            if ((int) ($op['atrasadas'] ?? 0)) {
                $f[] = ['humor' => 'ops', 'texto' => (int) $op['atrasadas'] . ' ordem(ns) de produção passaram da data prevista' . ((int) $op['hoje'] ? ' e ' . (int) $op['hoje'] . ' estão marcadas para hoje' : '') . '.', 'link' => '/producao/ordens', 'acao' => 'Ver ordens'];
            } elseif ((int) ($op['hoje'] ?? 0)) {
                $f[] = ['humor' => 'ideia', 'texto' => (int) $op['hoje'] . ' ordem(ns) de produção marcadas para hoje.', 'link' => '/producao/ordens', 'acao' => 'Ver ordens'];
            }
        }
        // Técnica: ordens concluídas esperando a aprovação dela
        if (Auth::pode('produtos.tecnico') && ($n = (int) Banco::valor("SELECT COUNT(*) FROM ordens_producao WHERE situacao = 'concluida' AND aprovada_em IS NULL AND concluida_em >= CURDATE() - INTERVAL 30 DAY"))) {
            $f[] = ['humor' => 'ideia', 'texto' => "$n ordem(ns) de produção concluída(s) esperando a aprovação da Responsável Técnica.", 'link' => '/producao/ordens', 'acao' => 'Aprovar ordens'];
        }
        // Financeiro: pedidos com desconto acima do limite e itens de notas do Omie para ligar
        if (Auth::pode('pedidos.aprovar') && Foco::tem('aprovacao', 'diretoria')
            && ($n = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao' AND desconto_acima_limite = 1"))) {
            $f[] = ['humor' => 'bravo', 'texto' => "$n pedido(s) com desconto da vendedora acima do limite: confira antes de aprovar.", 'link' => '/aprovacao', 'acao' => 'Ver na Aprovação'];
        }
        if (Auth::pode('compras.lancar') && ($n = count(CompraLancamentoService::pendentes()))) {
            $f[] = ['humor' => 'ideia', 'texto' => "$n item(ns) de notas de compra do Omie esperando você dizer a qual item do One correspondem.", 'link' => '/financeiro/compras?aba=omie', 'acao' => 'Ligar itens'];
        }
        // Depósito: e-commerce com horário hoje
        if (Auth::pode('deposito.operar') && Foco::tem('deposito') && ($b = OneTela::balao('/deposito', [], false))) {
            $f[] = $b + ['link' => '/deposito?tipo=ecommerce', 'acao' => 'Ver o e-commerce'];
        }
        // Diretoria e admin: um resumo do mês
        if (Foco::tem('diretoria') && (Auth::pode('painel.ver') || Auth::somenteAcompanha() || Auth::pode('config.gerenciar'))) {
            $m = Banco::um("SELECT COALESCE(SUM(CASE WHEN faturado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total END), 0) AS mes,
                COALESCE(SUM(CASE WHEN faturado_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND faturado_em < CURDATE() - INTERVAL 1 MONTH + INTERVAL 1 DAY THEN total END), 0) AS anterior
                FROM pedidos WHERE situacao = 'faturado' AND faturado_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')");
            $mes = (float) $m['mes'];
            $ant = (float) $m['anterior'];
            $comp = $ant > 0 ? ' (' . ($mes >= $ant ? '+' : '') . number_format(($mes / $ant - 1) * 100, 0, ',', '.') . '% em relação ao mesmo ponto do mês passado)' : '';
            $f[] = ['humor' => $mes >= $ant ? 'feliz' : 'pensando', 'texto' => 'Faturado no mês até agora: ' . dinheiro($mes) . $comp . '.', 'link' => Auth::pode('painel.ver') ? '/resultados' : '/', 'acao' => 'Ver resultados'];
            $parados = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao' AND criado_em < NOW() - INTERVAL 24 HOUR");
            if ($parados) {
                // há dias parado: o One fica vermelho de raiva; mais de 24 horas: bravo
                $diasParado = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao' AND criado_em < NOW() - INTERVAL 72 HOUR");
                $f[] = ['humor' => $diasParado ? 'furioso' : 'bravo', 'texto' => "$parados pedido(s) esperando aprovação há mais de 24 horas. Cliente esperando é venda em risco.", 'link' => '/aprovacao', 'acao' => 'Ver aprovação'];
            }
            $cob = (float) Banco::valor("SELECT COALESCE(SUM(valor_total), 0) FROM cobrancas WHERE situacao = 'aberta'");
            if ($cob > 0) {
                $f[] = ['humor' => 'pensando', 'texto' => 'Em cobrança agora: ' . dinheiro($cob) . ' em títulos atrasados.', 'link' => '/cobranca', 'acao' => 'Ver cobrança'];
            }
        }
        // Gestão comercial: vendedoras com muitos retornos atrasados
        if ((Auth::pode('carteira.ver_todas') || Setores::gerenciados()) && Foco::tem('comercial', 'diretoria')) {
            $setor = Setores::sqlGerenciados('c.funcao_id');
            $p = Banco::um("SELECT u.nome, COUNT(*) AS n FROM tarefas t JOIN usuarios u ON u.id = t.usuario_id LEFT JOIN clientes c ON c.id = t.cliente_id
                WHERE t.concluida_em IS NULL AND t.vence_em < CURDATE() - INTERVAL 2 DAY AND u.ativo = 1 AND u.id <> ?"
                . (!Auth::pode('carteira.ver_todas') && $setor ? " AND $setor" : '') . ' GROUP BY u.id, u.nome ORDER BY n DESC LIMIT 1', [$id]);
            if ($p && (int) $p['n'] >= 10) {
                $f[] = ['humor' => 'pensando', 'texto' => EquipeService::comoChamar(['nome' => $p['nome']]) . ' está com ' . (int) $p['n'] . ' retornos atrasados há mais de 2 dias. Talvez valha uma conversa ou redistribuir.', 'link' => '/acompanhamento', 'acao' => 'Acompanhamento'];
            }
        }
        return $f;
    }

    /** Dicas que valem para todo mundo: pedidos ao Marketing, recados no perfil e uma dica do sistema */
    private static function paraTodos(int $id): array
    {
        $f = [];
        $prontos = (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE solicitante_id = ? AND setor IS NOT NULL AND situacao = 'feito' AND concluido_em >= NOW() - INTERVAL 3 DAY", [$id]);
        $andando = (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE solicitante_id = ? AND setor IS NOT NULL AND situacao NOT IN ('feito', 'cancelado')", [$id]);
        if ($prontos) {
            $f[] = ['humor' => 'feliz', 'texto' => "O Marketing terminou $prontos pedido(s) seu(s) nos últimos dias. 🎉", 'link' => '/marketing/pedir', 'acao' => 'Ver meus pedidos'];
        } elseif ($andando) {
            $f[] = ['humor' => 'piscando', 'texto' => "Você tem $andando pedido(s) com o Marketing em andamento. Eu aviso quando ficar pronto.", 'link' => '/marketing/pedir', 'acao' => 'Acompanhar'];
        }
        $recados = (int) Banco::valor('SELECT COUNT(*) FROM perfil_recados WHERE perfil_id = ? AND autor_id <> ? AND oculto_em IS NULL AND criado_em >= NOW() - INTERVAL 3 DAY', [$id, $id]);
        if ($recados) {
            $f[] = ['humor' => 'feliz', 'texto' => "Você recebeu $recados recado(s) no seu perfil. 💬", 'link' => '/pessoas/' . $id, 'acao' => 'Ver recados'];
        }
        $f[] = self::dicaDoDia();
        return $f;
    }

    /** "Você sabia?": uma dica do sistema por dia, das telas que a pessoa pode abrir */
    private static function dicaDoDia(): ?array
    {
        $dicas = array_values(array_filter([
            [null, 'Você sabia? Aperte a tecla / em qualquer tela para buscar um cliente, produto ou código.', null],
            [null, 'Você sabia? Dá para me perguntar coisas, aqui em cima, em "Perguntar". Ex.: "como faço um orçamento?".', null],
            [null, 'Você sabia? Em Treinamento você tem uma trilha feita para o seu setor, com provinha no fim de cada módulo.', '/treinamento'],
            [null, 'Você sabia? Esqueceu como funciona uma tela? Me chame e clique em "O que dá pra fazer aqui?": eu mostro passo a passo.', null],
            [null, 'Você sabia? Em Meu perfil › Aparência você escolhe as cores do sistema só para você.', '/meu-perfil'],
            [null, 'Você sabia? Em Pessoas você deixa recados e elogios no perfil dos colegas.', '/pessoas'],
            ['pedidos.criar', 'Você sabia? "Gerar orçamento" manda um link pro cliente aprovar pelo WhatsApp: aprovou, vira pedido sozinho.', '/orcamentos'],
            ['producao.gerenciar', 'Você sabia? Concluir uma ordem de produção já dá baixa nas matérias-primas e soma no estoque acabado.', '/producao/ordens'],
            ['marketing.editar', 'Você sabia? Os pedidos dos outros setores chegam no Início do Marketing, com prazo e quem pediu.', '/marketing/inicio'],
            ['deposito.operar', 'Você sabia? Na separação, registrar as caixas usadas já baixa o estoque delas.', '/deposito'],
            ['pedidos.aprovar', 'Você sabia? Na Aprovação, ao lado de cada pedido aparecem crédito, títulos em aberto e compras de 12 meses.', '/aprovacao'],
        ], fn($d) => $d[0] === null || Auth::pode($d[0])));
        $d = $dicas[(int) date('z') % count($dicas)];
        return ['humor' => 'piscando', 'texto' => $d[1]] + ($d[2] ? ['link' => $d[2], 'acao' => 'Abrir'] : []);
    }

    /** Aniversário de hoje (o próprio ou de colegas) */
    private static function aniversario(int $id): ?array
    {
        $hoje = array_values(array_filter(EquipeService::aniversariantes(), fn($a) => $a['hoje']));
        if (!$hoje) {
            return null;
        }
        $eu = array_filter($hoje, fn($a) => (int) $a['id'] === $id);
        if ($eu) {
            return ['humor' => 'festa', 'texto' => 'Feliz aniversário! 🎉 A equipe toda torce por você hoje.'];
        }
        $nomes = array_map(fn($a) => $a['nome'], $hoje); // para os colegas: o nome do funcionário
        return ['humor' => 'festa', 'texto' => 'Hoje é aniversário de ' . implode(' e ', $nomes) . '! 🎉 Que tal mandar os parabéns?'];
    }

    /** Produção: matérias-primas para comprar e produtos para reabastecer */
    private static function producao(): array
    {
        $a = ProducaoService::alertas();
        $falas = [];
        if ($a['mp_abaixo']) {
            $falas[] = ['humor' => 'ops', 'texto' => "{$a['mp_abaixo']} matéria(s)-prima(s) abaixo do mínimo" . ($a['mp_acabando'] ? " e {$a['mp_acabando']} acabando" : '') . '. Vale fazer o pedido de compra hoje.', 'link' => '/producao/materias?situacao=alerta', 'acao' => 'Ver matérias-primas'];
        } elseif ($a['mp_acabando']) {
            $falas[] = ['humor' => 'pensando', 'texto' => "{$a['mp_acabando']} matéria(s)-prima(s) devem acabar nos próximos dias, pelo consumo médio.", 'link' => '/producao/materias?situacao=alerta', 'acao' => 'Ver previsão'];
        }
        if ($a['reabastecer']) {
            $falas[] = ['humor' => 'ideia', 'texto' => "{$a['reabastecer']} produto(s) para reabastecer: o estoque acabado ficou abaixo do mínimo. Eu já calculei a quantidade da ordem.", 'link' => '/producao', 'acao' => 'Gerar ordens'];
        }
        return $falas;
    }

    /** A fala é da mesma seção da tela atual? (compara o primeiro pedaço do endereço: /carteira, /meu-mes...) */
    private static function daPagina(array $fala, string $pagina): bool
    {
        $secao = fn(string $url) => explode('/', trim((string) strtok($url, '?'), '/'))[0];
        return isset($fala['link']) && $secao($fala['link']) !== '' && $secao($fala['link']) === $secao($pagina);
    }

    // ------------------------------------------------------------------

    private static function tarefas(int $id): ?array
    {
        $agenda = Tarefa::agenda($id);
        $atrasadas = count($agenda['atrasadas']);
        $hoje = count($agenda['hoje']);
        if ($atrasadas) {
            return ['humor' => 'ops', 'texto' => "Você tem $atrasadas retorno(s) atrasado(s)" . ($hoje ? " e $hoje para hoje" : '') . '. Que tal começar por eles?', 'link' => '/agenda', 'acao' => 'Abrir agenda'];
        }
        if ($hoje) {
            return ['humor' => 'piscando', 'texto' => "Hoje você tem $hoje retorno(s) combinado(s) com clientes.", 'link' => '/agenda', 'acao' => 'Ver agenda'];
        }
        return null;
    }

    /** Clientes que já passaram do intervalo médio de compra (alerta de recompra) */
    /** Marketing: posts esperando aprovação */
    private static function postsParaAprovar(): ?array
    {
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE e.tipo = 'aprovacao'");
        $etapa = \App\Services\MarketingService::etapaDoTipo('aprovacao');
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd post(s) do Instagram aguardam a sua aprovação.", 'link' => '/marketing?etapa=' . (int) ($etapa['id'] ?? 0), 'acao' => 'Revisar posts'] : null;
    }

    /** Marketing: semana que vem com poucos posts planejados */
    private static function semanaQueVem(): ?array
    {
        $meta = \App\Services\MarketingService::metaSemanal();
        $seg = date('Y-m-d', strtotime('monday next week'));
        $qtd = (int) Banco::valor('SELECT COUNT(*) FROM mkt_posts WHERE data_publicacao BETWEEN ? AND ?', [$seg, date('Y-m-d', strtotime("$seg +6 days"))]);
        return $meta && $qtd < $meta ? ['humor' => 'pensando', 'texto' => "A semana que vem tem só $qtd post(s) planejado(s) (meta: $meta). Bora preencher o mural?", 'link' => '/marketing?mes=' . substr($seg, 0, 7), 'acao' => 'Abrir mural'] : null;
    }

    /** Cobrança: promessas de pagamento que vencem hoje */
    private static function promessasHoje(): ?array
    {
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas WHERE situacao = 'aberta' AND promessa_em = CURDATE()");
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd promessa(s) de pagamento vencem hoje. Vale conferir se o Pix/boleto entrou.", 'link' => '/cobranca', 'acao' => 'Abrir Cobrança'] : null;
    }

    /** Cobrança: casos sem contato (pedem a busca com IA) */
    private static function semContato(): ?array
    {
        $qtd = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas cb JOIN cobranca_etapas e ON e.id = cb.etapa_id WHERE cb.situacao = 'aberta' AND e.tipo = 'sem_contato'");
        return $qtd ? ['humor' => 'pensando', 'texto' => "$qtd cliente(s) em cobrança estão sem contato. Que tal eu procurar telefones e WhatsApp novos na internet? É só abrir o caso e clicar em \"Buscar contatos com IA\".", 'link' => '/cobranca', 'acao' => 'Ver casos'] : null;
    }

    private static function recompra(): ?array
    {
        if (!Auth::pode('recompra.ver')) {
            return null;
        }
        $qtd = \App\Services\VendasService::qtdRecompra();
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd cliente(s) já passaram do tempo normal de recompra. Um contato agora pode virar pedido!", 'link' => '/recompra', 'acao' => 'Ver recompra'] : null;
    }


    /** Leads da carteira da pessoa que ainda aguardam o primeiro contato (Prospecção) */
    private static function prospeccao(): ?array
    {
        if (!Auth::pode('prospeccao.usar')) {
            return null;
        }
        $qtd = ProspeccaoService::qtdAguardando();
        return $qtd ? ['humor' => 'ideia', 'texto' => "$qtd lead(s) ainda aguardam o primeiro contato. Comece pelos de prioridade alta!", 'link' => '/prospeccao', 'acao' => 'Prospectar'] : null;
    }

    private static function metaIndividual(int $id): ?array
    {
        $mes = date('Y-m');
        $m = MetaService::indicadores($id, $mes);
        if ($m['config']['meta'] <= 0) {
            if (Auth::pode('metas.gerenciar')) {
                return null; // gestor acompanha a meta da equipe; meta individual é opcional para ele
            }
            return ['humor' => 'pensando', 'texto' => 'Você ainda não definiu sua meta deste mês. Com ela, eu calculo quanto falta por dia e a sua projeção.', 'link' => '/meu-mes', 'acao' => 'Definir meta'];
        }
        if ($m['atingida']) {
            return ['humor' => 'feliz', 'texto' => 'Meta do mês batida! 🎉 Você já vendeu ' . dinheiro($m['vendido']) . '. Continue registrando suas vendas.', 'link' => '/meu-mes', 'acao' => 'Meu mês'];
        }
        $texto = 'Sua meta: ' . number_format($m['percentual'], 0, ',', '.') . '% atingida. Faltam ' . dinheiro($m['falta']);
        $texto .= $m['necessario_dia'] !== null ? ' — cerca de ' . dinheiro($m['necessario_dia']) . ' por dia de trabalho.' : '.';
        if ($m['qtd_vendas']) {
            $texto .= ' Ticket médio: ' . dinheiro($m['ticket_medio']) . '.';
        }
        return ['humor' => $m['projecao_mes'] !== null && $m['projecao_mes'] >= $m['meta'] ? 'feliz' : 'ideia', 'texto' => $texto, 'link' => '/meu-mes', 'acao' => 'Meu mês'];
    }

    private static function metaEquipe(): ?array
    {
        $q = MetaService::equipe(date('Y-m'));
        if (!$q) {
            return Auth::pode('metas.gerenciar')
                ? ['humor' => 'pensando', 'texto' => 'A meta da equipe deste mês ainda não foi cadastrada. Cadastre para todos acompanharem as projeções.', 'link' => '/meu-mes', 'acao' => 'Cadastrar']
                : null;
        }
        if ($q['atingida']) {
            return ['humor' => 'festa', 'texto' => 'A equipe bateu a meta do mês! 🎉 ' . dinheiro($q['vendido']) . ' vendidos.', 'link' => '/meu-mes', 'acao' => 'Ver equipe'];
        }
        $noRitmo = $q['projecao_mes'] !== null && $q['projecao_mes'] >= $q['meta'];
        return ['humor' => $noRitmo ? 'feliz' : 'pensando',
            'texto' => 'Equipe: ' . number_format($q['percentual'], 0, ',', '.') . '% da meta. Projeção do mês: ' . ($q['projecao_mes'] !== null ? dinheiro($q['projecao_mes']) : '—')
                . ($noRitmo ? ' — no ritmo para bater!' : ($q['necessario_dia'] !== null ? '. Precisamos de ' . dinheiro($q['necessario_dia']) . ' por dia.' : '.')),
            'link' => '/meu-mes', 'acao' => 'Ver equipe'];
    }

    private static function academia(int $id): ?array
    {
        $usuario = \App\Core\Auth::usuario();
        if (!$usuario) {
            return null;
        }
        [, , $proximo] = \App\Services\TreinamentoService::resumo(\App\Services\TreinamentoService::daPessoa($usuario), \App\Services\TreinamentoService::progresso($id));
        return $proximo ? ['humor' => 'piscando', 'texto' => "Próximo passo no Treinamento: \"{$proximo['titulo']}\" ({$proximo['tempo_min']} min).", 'link' => '/treinamento/modulo/' . $proximo['id'], 'acao' => 'Estudar'] : null;
    }
}
