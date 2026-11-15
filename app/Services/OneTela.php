<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Sessao;
use App\Models\Tarefa;

/**
 * One presente em cada tela (V12.3).
 *
 *  - balao($pagina): o One puxa conversa SOZINHO só quando há algo importante naquela tela
 *    (atrasados na agenda, cliente para recomprar, pedidos esperando aprovação, e-commerce com horário...).
 *    Cada balão traz botões que já fazem a coisa: abrir o cliente, ligar, WhatsApp, deixar para amanhã.
 *  - ajuda($pagina): "O que dá pra fazer aqui?" — a tela explicada em poucos passos, com a aula da Academia.
 *  - festejar(): comemorações (pedido enviado, agenda zerada, meta batida, cliente reconquistado).
 *
 * Tudo respeita as permissões: cada um só vê dicas do que pode ver.
 * Cada ação: ['rotulo', 'tipo' => link|externo|post, 'url', 'campos' => [...] (post)]
 */
class OneTela
{
    /** Balão da tela (ou null quando não há nada importante) */
    public static function balao(string $pagina, array $query = [], bool $comDicas = true): ?array
    {
        if (!Auth::logado()) {
            return null;
        }
        $id = (int) Auth::id();
        $vende = Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas');
        $b = match (true) {
            $pagina === '/agenda' && $vende => self::agenda($id),
            $pagina === '/recompra' && Auth::pode('recompra.ver') => self::recompra(true),
            in_array($pagina, ['/carteira', '/carteira/lista'], true) && Auth::pode('recompra.ver') => self::recompra(false),
            $pagina === '/reativacao' && $vende => self::reativacao(),
            $pagina === '/pedidos/novo' && !empty($query['cliente']) && empty($query['repetir']) && empty($query['orcamento']) && Auth::pode('pedidos.criar') => self::repetir((int) $query['cliente']),
            $pagina === '/aprovacao' && Auth::pode('pedidos.aprovar') => self::aprovacao(),
            $pagina === '/pedidos' && Auth::pode('pedidos.criar') => self::recusados($id),
            $pagina === '/deposito' && (Auth::pode('deposito.operar') || Auth::pode('deposito.ver')) => self::ecommerceHoje(),
            $pagina === '/meu-mes' && Auth::pode('meu_mes.usar') => self::meta($id),
            default => null,
        };
        // Nas outras telas: a dica importante do One que é desta tela (as mesmas do painel)
        if (!$b && $comDicas) { // $comDicas = false quando quem pergunta já são as dicas (evita uma pedir a outra sem fim)
            foreach (OneAssistente::falas($pagina) as $f) {
                if (in_array($f['humor'] ?? '', ['ops', 'ideia'], true) && !empty($f['link']) && strtok($f['link'], '?') === $pagina) {
                    $b = $f + ['acoes' => []];
                    break;
                }
            }
        }
        return $b;
    }

    // ---------------------------------------------------------------- balões de cada tela

    private static function agenda(int $id): ?array
    {
        $a = Tarefa::agenda($id);
        if (!$a['atrasadas']) {
            return null;
        }
        $t = $a['atrasadas'][0];
        $n = count($a['atrasadas']);
        $quem = $t['razao_social'] ? ' Começa pelo ' . self::curto($t['razao_social']) . ' (desde ' . data_br($t['vence_em']) . ')?' : '';
        return ['humor' => 'ops', 'texto' => "Você tem $n retorno(s) atrasado(s).$quem",
            'acoes' => array_values(array_filter([
                $t['cliente_id'] ? self::link('Abrir o cliente', '/carteira/clientes/' . (int) $t['cliente_id']) : null,
                self::ligar($t['telefone'] ?? null),
                self::whats($t['whatsapp'] ?: ($t['telefone'] ?? null), 'Olá! Aqui é ' . self::eu() . ' da ' . config('empresa_nome', 'Perfeita Alquimia') . '. Estou retornando o nosso contato, tudo bem?'),
                ['rotulo' => 'Deixar para amanhã', 'tipo' => 'post', 'url' => '/agenda/' . (int) $t['id'] . '/reagendar', 'campos' => ['para' => 'amanha', 'voltar' => '/agenda']],
            ]))];
    }

    private static function recompra(bool $naTela): ?array
    {
        $lista = VendasService::recompra(null, Setores::funcaoIdAtual());
        if (!$lista) {
            return null;
        }
        $c = $lista[0];
        $nome = self::curto($c['nome_fantasia'] ?: $c['razao_social']);
        $leva = $c['produtos'] ? ' Costuma levar ' . implode(', ', array_slice($c['produtos'], 0, 2)) . '.' : '';
        $texto = $naTela
            ? "Comece pelo $nome: compra a cada " . (int) $c['intervalo_medio_dias'] . ' dias e já está ' . (int) $c['dias_atraso'] . " dias atrasado.$leva"
            : count($lista) . " cliente(s) seus passaram do tempo de recompra. O mais valioso agora: $nome.";
        return ['humor' => 'ideia', 'texto' => $texto, 'acoes' => array_values(array_filter([
            self::link('Abrir o cliente', '/carteira/clientes/' . (int) $c['id']),
            self::whats($c['whatsapp'] ?: $c['telefone'], 'Olá! Aqui é ' . self::eu() . ' da ' . config('empresa_nome', 'Perfeita Alquimia') . '. Já faz um tempinho do seu último pedido: quer que eu separe a reposição?'),
            Auth::pode('pedidos.criar') ? self::link('Repetir o último pedido', '/pedidos/novo?cliente=' . (int) $c['id'] . '&repetir=1') : null,
            $naTela ? null : self::link('Ver a recompra', '/recompra'),
        ]))];
    }

    private static function reativacao(): ?array
    {
        if (!CarteiraService::reativacaoComVendedora()) {
            return null;
        }
        $dias = (int) Config::get('carteira.dias_inatividade', 90);
        $f = \App\Models\Cliente::filtrosDe(['tipo' => 'cliente', 'compra' => 'sem' . $dias] + (Setores::funcaoIdAtual() ? ['funcao' => (string) Setores::funcaoIdAtual()] : []));
        $r = \App\Models\Cliente::listar($f, 1, 1, 'compra');
        if (!$r['total']) {
            return null;
        }
        $c = $r['itens'][0];
        return ['humor' => 'ideia', 'texto' => $r['total'] . ' cliente(s) seus estão sem comprar há mais de ' . $dias . ' dias. Que tal oferecer a campanha do mês para o ' . self::curto($c['nome_fantasia'] ?: $c['razao_social']) . '?',
            'acoes' => array_values(array_filter([
                self::link('Abrir o cliente', '/carteira/clientes/' . (int) $c['id']),
                self::whats($c['whatsapp'] ?: $c['telefone'], 'Olá! Aqui é ' . self::eu() . ' da ' . config('empresa_nome', 'Perfeita Alquimia') . '. Sentimos sua falta! Este mês temos condições especiais: posso te mandar as ofertas?'),
            ]))];
    }

    private static function repetir(int $clienteId): ?array
    {
        $p = Banco::um("SELECT id, total, criado_em FROM pedidos WHERE cliente_id = ? AND situacao IN ('faturado', 'aprovado') ORDER BY criado_em DESC LIMIT 1", [$clienteId]);
        if (!$p || !\App\Models\Cliente::podeVer(\App\Models\Cliente::buscar($clienteId) ?? [])) {
            return null;
        }
        return ['humor' => 'ideia', 'texto' => 'O último pedido deste cliente foi em ' . data_br($p['criado_em']) . ' (' . dinheiro((float) $p['total']) . '). Quer começar repetindo ele?',
            'acoes' => [self::link('Repetir o último pedido', '/pedidos/novo?cliente=' . $clienteId . '&repetir=1'), self::link('Ver o pedido', '/pedidos/' . (int) $p['id'])]];
    }

    private static function aprovacao(): ?array
    {
        $p = Banco::um("SELECT id, criado_em, TIMESTAMPDIFF(HOUR, criado_em, NOW()) AS horas, (SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao') AS total
            FROM pedidos WHERE situacao = 'aguardando_aprovacao' ORDER BY criado_em, id LIMIT 1");
        if (!$p) {
            return null;
        }
        $h = (int) $p['horas'];
        $espera = $h >= 48 ? intdiv($h, 24) . ' dias' : ($h >= 1 ? "$h hora(s)" : 'menos de uma hora');
        return ['humor' => $h >= 24 ? 'ops' : 'ideia', 'texto' => (int) $p['total'] . ' pedido(s) esperando aprovação. O mais antigo está esperando há ' . $espera . ': o cliente está aguardando!',
            'acoes' => [self::link('Abrir o mais antigo', '/pedidos/' . (int) $p['id'])]];
    }

    private static function recusados(int $id): ?array
    {
        $p = Banco::um("SELECT p.id, p.motivo_recusa, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
            WHERE p.situacao = 'recusado' AND (p.vendedor_id = ? OR p.criado_por_usuario = ?) AND p.atualizado_em >= NOW() - INTERVAL 3 DAY ORDER BY p.atualizado_em DESC LIMIT 1", [$id, $id]);
        if (!$p) {
            return null;
        }
        return ['humor' => 'ops', 'texto' => 'O pedido #' . (int) $p['id'] . ' (' . self::curto($p['cliente']) . ') foi recusado' . ($p['motivo_recusa'] ? ': "' . mb_strimwidth($p['motivo_recusa'], 0, 90, '…') . '"' : '.') . ' Dá para ajustar e enviar de novo.',
            'acoes' => [self::link('Abrir o pedido', '/pedidos/' . (int) $p['id'])]];
    }

    private static function ecommerceHoje(): ?array
    {
        $r = Banco::um("SELECT COUNT(*) AS n, MIN(expedicao_limite) AS limite FROM pedidos WHERE tipo_venda = 'ecommerce' AND expedicao IN ('aguardando', 'separando')
            AND expedicao_limite IS NOT NULL AND DATE(expedicao_limite) = CURDATE()");
        if (!$r || !(int) $r['n']) {
            return null;
        }
        $hora = date('H:i', strtotime($r['limite']));
        $passou = strtotime($r['limite']) < time();
        return ['humor' => 'ops', 'texto' => (int) $r['n'] . ' pedido(s) do e-commerce precisam sair hoje' . ($passou ? ' e o primeiro horário (' . $hora . ') já passou!' : '. O primeiro vence às ' . $hora . '.'),
            'acoes' => [self::link('Ver só o e-commerce', '/deposito?tipo=ecommerce')]];
    }

    private static function meta(int $id): ?array
    {
        $m = MetaService::indicadores($id, date('Y-m'));
        if ($m['config']['meta'] <= 0 || $m['atingida'] || $m['necessario_dia'] === null || ($m['projecao_mes'] !== null && $m['projecao_mes'] >= $m['meta'])) {
            return null; // sem meta, já batida ou no ritmo certo: não precisa puxar conversa
        }
        return ['humor' => 'ideia', 'texto' => 'No ritmo de agora você chega a ' . dinheiro((float) $m['projecao_mes']) . '. Para bater a meta, são cerca de ' . dinheiro((float) $m['necessario_dia']) . ' por dia de trabalho. Bora pela recompra?',
            'acoes' => array_values(array_filter([Auth::pode('recompra.ver') ? self::link('Ver a recompra', '/recompra') : null, self::link('Minha agenda', '/agenda')]))];
    }

    // ---------------------------------------------------------------- ajuda de cada tela

    /** [prefixo => [título, passos, aula da Academia]] — o mais específico vale */
    private const AJUDAS = [
        '/' => ['Início', ['Aqui fica o resumo do seu dia: tarefas, metas e avisos.', 'Os cartões levam direto para o que precisa de atenção.', 'Eu (o One) aviso quando algo importante aparece.'], 'primeiro-acesso'],
        '/carteira' => ['Carteira', ['Visão geral, Lista, Funil e Mapa ficam nas abas.', 'Clique no nome do cliente para abrir a ficha com histórico e contatos.', 'Use os filtros (cidade, etapa, situação de compra) para montar sua lista do dia.'], 'carteira'],
        '/carteira/clientes' => ['Ficha do cliente', ['Registre cada contato: ligação, WhatsApp ou visita. Fica na linha do tempo.', 'Agende o próximo contato: ele aparece na sua Agenda no dia.', '"O que mais compra" ajuda a montar a oferta. Daqui dá para fazer pedido ou orçamento.'], 'contato-agenda'],
        '/agenda' => ['Agenda', ['Atrasados primeiro, depois os de hoje.', 'Conclua quando fizer o contato, ou deixe para amanhã com um clique.', 'Semana mostra os dias; Lista mostra tudo em ordem.'], 'contato-agenda'],
        '/recompra' => ['Recompra', ['Clientes que passaram do tempo normal entre uma compra e outra.', 'Os primeiros da lista valem mais (ticket × atraso).', 'Ligue ou mande WhatsApp e, se der, repita o último pedido.'], 'recompra-cobranca'],
        '/reativacao' => ['Reativação', ['Seus clientes que estão sem comprar há muito tempo.', 'Eles continuam com você: ofereça a campanha do mês.', 'Registre o contato na ficha para não perder o histórico.'], 'recompra-cobranca'],
        '/pedidos' => ['Pedidos', ['Todos os seus pedidos e a situação de cada um.', '"Novo pedido" monta o pedido com a tabela do cliente, as campanhas e os descontos.', 'Recusado? Abra, veja o motivo e envie de novo.'], 'pedido'],
        '/pedidos/novo' => ['Novo pedido', ['Digite as quantidades: o total, o pedido mínimo e a campanha se atualizam na hora.', 'Desconto por produto ou no pedido inteiro (acima do limite vai para aprovação).', '"Gerar orçamento" manda um link para o cliente aprovar pelo WhatsApp.'], 'pedido'],
        '/orcamentos' => ['Orçamentos', ['Orçamentos enviados por link ao cliente.', 'O cliente abre no celular, muda o que quiser e aprova: vira pedido sozinho.', 'Dá para reenviar o link ou transformar em pedido você mesma.'], 'pedido'],
        '/meu-mes' => ['Meu mês', ['Sua meta, o que já vendeu e quanto falta por dia.', 'As vendas chegam do faturamento (Omie).', 'Acompanhe também a meta da equipe.'], 'meu-mes'],
        '/prospeccao' => ['Prospecção', ['Empresas novas para abordar, da base de CNPJs e dos anúncios.', 'Envie para o funil as que valem a pena.', 'Use as mensagens prontas para o primeiro contato.'], 'prospeccao'],
        '/campanhas' => ['Campanhas', ['Campanhas do mês ou da semana: faixas de desconto, brindes, frete grátis e promoções.', 'Valem no catálogo do cliente e no pedido da equipe.', 'Duplique para criar a do mês seguinte em segundos.'], 'pedido'],
        '/aprovacao' => ['Aprovação', ['Pedidos esperando o Financeiro, os mais antigos primeiro.', 'Ao lado de cada um: crédito, títulos em aberto e compras dos últimos 12 meses.', 'Recusar pede o motivo: a vendedora e o cliente veem.'], null],
        '/cobranca' => ['Cobrança', ['Clientes com títulos em atraso, num funil próprio.', 'Registre tentativas e promessas de pagamento.', 'Promessa vencendo hoje aparece em destaque.'], 'recompra-cobranca'],
        '/deposito' => ['Separação', ['Pedidos com nota para separar e despachar.', 'Lojistas, e-commerce e terceirização separados por cor; e-commerce tem horário limite.', 'Registre as caixas usadas: o estoque delas baixa sozinho.'], null],
        '/producao' => ['Produção', ['Matérias-primas abaixo do mínimo e produtos para reabastecer.', 'Ordens de produção planejadas e o que foi produzido no mês.', 'Concluir uma ordem baixa as matérias-primas automaticamente.'], null],
        '/producao/fichas' => ['Fichas técnicas', ['O que vai em cada unidade fabricada e o custo.', 'A tabela nutricional vai sendo montada enquanto você preenche.', 'Imprima a ficha técnica ou baixe a tabela em PDF/imagem.'], null],
        '/marketing/pedir' => ['Pedir ao Marketing', ['Escolha o assunto (arte, material, cadastro, pergunta de cliente).', 'Descreva o que precisa e a data.', 'Você acompanha o andamento e recebe aviso quando ficar pronto.'], null],
        '/academia' => ['Academia', ['Trilha de treinamento, fichas dos produtos, falas e objeções.', 'Comece pela trilha se você é nova na equipe.', '"Como usar o One" explica cada tela do sistema.'], 'primeiro-acesso'],
        '/pessoas' => ['Pessoas', ['Conheça a equipe: perfis, gostos e aniversários.', 'Deixe recados e elogios no perfil dos colegas.', 'Personalize o seu em Meu perfil.'], null],
    ];

    public static function ajuda(string $pagina): ?array
    {
        $melhor = null;
        foreach (self::AJUDAS as $prefixo => $a) {
            $ok = $prefixo === '/' ? $pagina === '/' : str_starts_with($pagina . '/', $prefixo . '/');
            if ($ok && strlen($prefixo) > strlen((string) ($melhor[3] ?? ''))) {
                $melhor = [...$a, $prefixo];
            }
        }
        if (!$melhor) {
            $item = \App\Core\Menu::itemDe($pagina);
            return $item ? ['titulo' => $item['titulo'], 'passos' => [$item['descricao'] ?? ''], 'aula' => null] : null;
        }
        [$titulo, $passos, $aula] = $melhor;
        return ['titulo' => $titulo, 'passos' => $passos, 'aula' => $aula && Auth::pode('academia.ver') ? '/academia/sistema/' . $aula : null];
    }

    // ---------------------------------------------------------------- comemorações

    /** Guarda uma comemoração para a próxima tela (o layout mostra uma vez, com confete) */
    public static function festejar(string $titulo, string $texto): void
    {
        if (Auth::logado()) {
            Sessao::set('one_festa', ['titulo' => $titulo, 'texto' => $texto]);
        }
    }

    /** A comemoração pendente (e esquece). Também festeja a meta batida (aviso no sino ainda não lido). */
    public static function festaPendente(): ?array
    {
        $f = Sessao::get('one_festa');
        Sessao::remover('one_festa');
        if (!$f && Auth::logado()) {
            $n = Banco::um("SELECT id, titulo FROM notificacoes WHERE usuario_id = ? AND tipo = 'meta_batida' AND lida_em IS NULL ORDER BY id DESC LIMIT 1", [(int) Auth::id()]);
            if ($n) {
                Banco::executar('UPDATE notificacoes SET lida_em = NOW() WHERE id = ?', [$n['id']]);
                $f = ['titulo' => 'Meta batida! 🎯', 'texto' => 'Você bateu a meta do mês. Que orgulho! Tudo o que vier agora é recorde.'];
            }
        }
        return $f ?: null;
    }

    // ---------------------------------------------------------------- ajudantes

    private static function link(string $rotulo, string $url): array
    {
        return ['rotulo' => $rotulo, 'tipo' => 'link', 'url' => $url];
    }

    private static function ligar(?string $tel): ?array
    {
        $n = preg_replace('/\D/', '', (string) $tel);
        return strlen($n) >= 10 ? ['rotulo' => 'Ligar', 'tipo' => 'externo', 'url' => 'tel:+55' . $n] : null;
    }

    private static function whats(?string $tel, string $mensagem): ?array
    {
        $n = preg_replace('/\D/', '', (string) $tel);
        if (strlen($n) < 10) {
            return null;
        }
        if (!str_starts_with($n, '55') || strlen($n) <= 11) {
            $n = '55' . $n;
        }
        return ['rotulo' => 'WhatsApp', 'tipo' => 'externo', 'url' => 'https://wa.me/' . $n . '?text=' . rawurlencode($mensagem)];
    }

    private static function eu(): string
    {
        return EquipeService::comoChamar(Auth::usuario());
    }

    /** Nome de cliente curto para caber no balão */
    private static function curto(?string $nome): string
    {
        return mb_strimwidth(trim((string) $nome), 0, 40, '…');
    }
}
