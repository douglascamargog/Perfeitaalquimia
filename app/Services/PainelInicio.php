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
        $temCarteira = Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas');
        $mes = date('Y-m');

        return [
            'data_extenso' => self::DIAS[(int) date('w')] . ', ' . (int) date('d') . ' de ' . self::MESES[(int) date('n') - 1],
            'clima'        => self::clima(),
            'agenda'       => $temCarteira ? Tarefa::agenda($id) : null,
            'meta'         => Auth::pode('meu_mes.usar') ? self::meta($id, $mes) : null,
            'equipe'       => Auth::pode('meu_mes.usar') || Auth::pode('metas.gerenciar') ? MetaService::equipe($mes) : null,
            'carteira'     => $temCarteira ? self::carteira() : null,
            'funil'        => $temCarteira ? self::funil() : null,
            'campanha'     => $temCarteira ? self::campanha() : null,
            'cobranca'     => Auth::pode('cobranca.ver') ? Cobranca::resumo() : null,
            'marketing'    => Auth::pode('marketing.ver') ? self::marketing() : null,
            'vendas'       => Auth::pode('painel.ver') ? self::vendas($mes) : null,
            'recompra'     => Auth::pode('recompra.ver') ? VendasService::qtdRecompra() : null,
            'ecommerce'    => Auth::pode('ecommerce.ver') && Banco::valor('SELECT 1 FROM ecommerce_resumo_diario LIMIT 1') ? EcommerceService::painel(date('Y-m')) : null,
        ];
    }

    /** Faturado no mês (pedidos faturados) e % da meta da equipe */
    private static function vendas(string $mes): array
    {
        $r = VendasService::resumo(['de' => "$mes-01", 'ate' => date('Y-m-d')]);
        $meta = (float) Banco::valor('SELECT meta FROM metas_equipe WHERE mes = ?', [$mes]);
        return ['faturado' => $r['faturamento'], 'pedidos' => $r['pedidos'], 'meta' => $meta,
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

    /** Campanha em destaque (ex.: Black Friday): lojas que ainda aguardam o primeiro contato */
    private static function campanha(): ?array
    {
        $origem = trim((string) Config::get('carteira.campanha_origem', ''));
        if ($origem === '') {
            return null;
        }
        $f = Cliente::filtrosDe(['origem' => (string) (Listas::idPorNome(Listas::origens(), $origem) ?? '')]);
        if ($f['origem'] === '') {
            return null;
        }
        $r = Cliente::resumo($f);
        if (!$r['total']) {
            return null; // ninguém da campanha nesta carteira
        }
        return ['titulo' => (string) Config::get('carteira.campanha_titulo', $origem), 'total' => $r['total'],
            'aguardando' => $r['nao_abordados'], 'contatados' => $r['contatados']];
    }
}
