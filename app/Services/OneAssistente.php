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
        $nome = explode(' ', (string) (Auth::usuario()['nome'] ?? ''))[0];
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
        if ($comercial && Auth::pode('academia.ver')) {
            $falas[] = self::academia($id);
        }
        if (Auth::pode('usuarios.gerenciar') && Foco::tem('diretoria')) {
            $falas[] = ['humor' => 'piscando', 'texto' => 'Gente nova na equipe? Crie o login em Usuários e marque a função da pessoa: as filas e regras seguem a função.', 'link' => '/usuarios', 'acao' => 'Usuários'];
        }

        $falas = array_values(array_filter($falas));
        // Dicas da tela atual aparecem primeiro
        usort($falas, fn($a, $b) => (int) self::daPagina($b, $pagina) <=> (int) self::daPagina($a, $pagina));
        return $falas ?: [['humor' => 'feliz', 'texto' => 'Tudo em ordem por aqui. Quando houver algo importante, eu aviso!']];
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
            return ['humor' => 'feliz', 'texto' => 'Feliz aniversário! 🎉 A equipe toda torce por você hoje.'];
        }
        $nomes = array_map(fn($a) => $a['apelido'] ?: explode(' ', $a['nome'])[0], $hoje);
        return ['humor' => 'feliz', 'texto' => 'Hoje é aniversário de ' . implode(' e ', $nomes) . '! 🎉 Que tal mandar os parabéns?'];
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
            return ['humor' => 'feliz', 'texto' => 'A equipe bateu a meta do mês! 🎉 ' . dinheiro($q['vendido']) . ' vendidos.', 'link' => '/meu-mes', 'acao' => 'Ver equipe'];
        }
        $noRitmo = $q['projecao_mes'] !== null && $q['projecao_mes'] >= $q['meta'];
        return ['humor' => $noRitmo ? 'feliz' : 'pensando',
            'texto' => 'Equipe: ' . number_format($q['percentual'], 0, ',', '.') . '% da meta. Projeção do mês: ' . ($q['projecao_mes'] !== null ? dinheiro($q['projecao_mes']) : '—')
                . ($noRitmo ? ' — no ritmo para bater!' : ($q['necessario_dia'] !== null ? '. Precisamos de ' . dinheiro($q['necessario_dia']) . ' por dia.' : '.')),
            'link' => '/meu-mes', 'acao' => 'Ver equipe'];
    }

    private static function academia(int $id): ?array
    {
        $feitos = AcademiaService::progresso($id);
        foreach (AcademiaService::modulos() as $m) {
            if (!isset($feitos['modulo:' . $m['id']])) {
                return ['humor' => 'piscando', 'texto' => "Próximo passo na Academia: \"{$m['title']}\" ({$m['time']}).", 'link' => '/academia/trilha/' . $m['id'], 'acao' => 'Estudar'];
            }
        }
        return null;
    }
}
