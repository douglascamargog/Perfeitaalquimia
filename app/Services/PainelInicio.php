<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Listas;
use App\Models\Tarefa;

/**
 * Monta os blocos da tela Início (painel em "bento grid").
 * Cada bloco só aparece para quem tem permissão; null = bloco escondido.
 */
class PainelInicio
{
    private const DIAS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    private const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    public static function montar(): array
    {
        $id = (int) Auth::id();
        $mes = date('Y-m');
        // Cada cartão aparece só se a pessoa ACOMPANHA a área (foco: funções ou Meu perfil) E TEM ACESSO a ela.
        // Ex.: admin que é do Marketing vê os cartões de Marketing, mesmo podendo abrir tudo.
        $ve = fn(string $permissao, string ...$areas) => Auth::pode($permissao) && Foco::tem(...$areas);
        $temCarteira = $ve('carteira.ver_propria', 'comercial') || $ve('carteira.ver_todas', 'comercial');
        $soDiretoria = !Foco::tem('comercial'); // resumo da empresa, sem a meta da equipe

        return [
            'focos'        => Foco::areasDe($id),
            'aniversariantes' => EquipeService::aniversariantes(),
            'aprovacao'    => $ve('pedidos.aprovar', 'aprovacao', 'diretoria') ? Banco::um("SELECT COUNT(*) AS qtd, COALESCE(SUM(total), 0) AS valor FROM pedidos WHERE situacao = 'aguardando_aprovacao'") : null,
            'data_extenso' => self::DIAS[(int) date('w')] . ', ' . (int) date('d') . ' de ' . self::MESES[(int) date('n') - 1],
            'clima'        => self::clima(),
            'agenda'       => $temCarteira ? Tarefa::agenda($id) : null,
            'meta'         => $ve('meu_mes.usar', 'comercial') ? self::meta($id, $mes) : null,
            'equipe'       => $ve('meu_mes.usar', 'comercial') || $ve('metas.gerenciar', 'comercial') ? MetaService::equipe($mes) : null,
            'carteira'     => $temCarteira ? self::carteira() : null,
            'funil'        => $temCarteira ? self::funil() : null,
            'prospeccao'   => $ve('prospeccao.usar', 'comercial') ? self::prospeccao() : null,
            'cobranca'     => $ve('cobranca.ver', 'cobranca', 'diretoria') ? Cobranca::resumo() : null,
            'marketing'    => $ve('marketing.ver', 'marketing') ? self::marketing() : null,
            'vendas'       => $ve('painel.ver', 'comercial', 'diretoria') ? self::vendas($mes, $soDiretoria) : null,
            'recompra'     => $ve('recompra.ver', 'comercial') ? VendasService::qtdRecompra() : null,
            'producao'     => ($ve('producao.gerenciar', 'producao', 'diretoria') || $ve('producao.ver', 'producao', 'diretoria')) ? ProducaoService::alertas() : null,
            'ecommerce'    => $ve('ecommerce.ver', 'marketing', 'diretoria') && Banco::valor('SELECT 1 FROM ecommerce_resumo_diario LIMIT 1') ? EcommerceService::painel(date('Y-m')) : null,
        ];
    }

    /** Faturado no mês (pedidos faturados) e % da meta da equipe */
    private static function vendas(string $mes, bool $admin = false): array
    {
        $r = VendasService::resumo(['de' => "$mes-01", 'ate' => date('Y-m-d')]);
        $meta = $admin ? 0.0 : (float) Banco::valor('SELECT meta FROM metas_equipe WHERE mes = ?', [$mes]);
        // Para a diretoria: comparação com o mesmo período do mês anterior
        $anterior = $admin ? VendasService::resumo(['de' => date('Y-m-01', strtotime("$mes-01 -1 month")), 'ate' => date('Y-m-d', strtotime('-1 month'))])['faturamento'] : null;
        return ['faturado' => $r['faturamento'], 'pedidos' => $r['pedidos'], 'meta' => $meta, 'anterior' => $anterior,
            'a_aprovar' => (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao'")];
    }

    /** Marketing: posts desta semana (seg → dom) e os que aguardam aprovação */
    private static function marketing(): array
    {
        $seg = date('Y-m-d', strtotime('monday this week'));
        $dom = date('Y-m-d', strtotime('sunday this week'));
        return [
            'semana'    => (int) Banco::valor('SELECT COUNT(*) FROM mkt_posts WHERE data_publicacao BETWEEN ? AND ?', [$seg, $dom]),
            'publicados_semana' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE p.data_publicacao BETWEEN ? AND ? AND e.tipo = 'publicado'", [$seg, $dom]),
            'aprovacao' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE e.tipo = 'aprovacao'"),
            'ajuste'    => (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE e.tipo = 'ajuste'"),
            'meta'      => MarketingService::metaSemanal(),
            'proximos'  => Banco::todos("SELECT p.id, p.titulo, p.formato, p.data_publicacao, p.hora, e.nome AS etapa, e.cor FROM mkt_posts p
                JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE p.data_publicacao >= CURDATE() AND e.tipo <> 'publicado'
                ORDER BY p.data_publicacao, p.hora LIMIT 3"),
        ];
    }

    /** Cidade da previsão do tempo (Configurações › Início). A previsão é buscada pelo navegador (Open-Meteo, grátis). */
    private static function clima(): ?array
    {
        $lat = (string) Config::get('inicio.clima_latitude', '');
        $lng = (string) Config::get('inicio.clima_longitude', '');
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        return ['cidade' => (string) Config::get('inicio.clima_cidade', ''), 'lat' => (float) $lat, 'lng' => (float) $lng];
    }

    private static function meta(int $id, string $mes): ?array
    {
        $m = MetaService::indicadores($id, $mes);
        return $m['config']['meta'] > 0 ? $m : null;
    }

    /** Números da carteira (já respeitam o escopo: vendedor vê só os dele) */
    private static function carteira(): array
    {
        $f = Cliente::filtrosDe([]);
        $r = Cliente::resumo($f);
        $escopo = Auth::pode('carteira.ver_todas') ? '' : ' AND responsavel_id = ' . (int) Auth::id();
        $r['recompra'] = (int) Banco::valor(
            "SELECT COUNT(*) FROM clientes WHERE excluido_em IS NULL AND tipo = 'cliente' AND intervalo_medio_dias > 0
                AND ultima_compra_em + INTERVAL intervalo_medio_dias DAY < CURDATE()
                AND ultima_compra_em >= CURDATE() - INTERVAL ? DAY$escopo",
            [(int) Config::get('carteira.dias_inatividade', 90)]
        );
        // Contatos registrados nos últimos 7 dias (mini gráfico)
        $r['semana'] = Cliente::atividade($f, 7);
        return $r;
    }

    /** Etapas do funil de vendas com a quantidade em cada uma (para a "trilha" de etapas) */
    private static function funil(): array
    {
        $contagem = array_column(Cliente::contarPor(Cliente::filtrosDe([]), 'etapa'), 'total', 'chave');
        $etapas = [];
        foreach (Listas::etapas() as $e) {
            $etapas[] = ['id' => (int) $e['id'], 'nome' => $e['nome'], 'cor' => $e['cor'], 'tipo' => $e['tipo'], 'total' => (int) ($contagem[$e['id']] ?? 0)];
        }
        return $etapas;
    }

    /** Prospecção: leads que aguardam o primeiro contato (na carteira de cada um) e abordados na semana */
    private static function prospeccao(): ?array
    {
        [$where, $par] = Cliente::montarWhere(Cliente::filtrosDe(['tipo' => 'lead']));
        $r = Banco::um("SELECT COUNT(*) AS leads, SUM(c.contatado = 0) AS aguardando FROM clientes c WHERE $where", $par);
        return (int) $r['leads'] ? ['leads' => (int) $r['leads'], 'aguardando' => (int) $r['aguardando']] : null;
    }
}
