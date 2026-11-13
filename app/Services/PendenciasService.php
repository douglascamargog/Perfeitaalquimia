<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;

/**
 * Contador vermelho do menu: quanto está PENDENTE agora em cada área (some quando o assunto é resolvido).
 * Calculado pela situação real (não por notificação) e guardado 60 s na sessão para não pesar.
 * Devolve [url do menu => quantidade], só com o que a pessoa pode ver e só quando é maior que zero.
 */
class PendenciasService
{
    private const VALIDADE = 60; // segundos

    public static function doUsuario(bool $forcar = false): array
    {
        $cache = Sessao::get('pendencias');
        if (!$forcar && $cache && time() - $cache['em'] < self::VALIDADE) {
            return $cache['dados'];
        }
        $dados = array_filter(self::calcular(), fn($n) => $n > 0);
        Sessao::set('pendencias', ['em' => time(), 'dados' => $dados]);
        return $dados;
    }

    /** Depois de uma ação que muda as contagens (aprovar, concluir...), recalcula na próxima página */
    public static function limpar(): void
    {
        Sessao::remover('pendencias');
    }

    private static function calcular(): array
    {
        $id = (int) Auth::id();
        // Número no menu só da área que a pessoa ACOMPANHA (foco) e à qual TEM ACESSO
        $pode = fn(string $p) => Auth::pode($p);
        $comercial = Foco::tem('comercial');
        $carteira = $comercial && ($pode('carteira.ver_propria') || $pode('carteira.ver_todas'));
        $r = [];

        if ($pode('pedidos.aprovar') && Foco::tem('aprovacao', 'diretoria')) {
            $r['/aprovacao'] = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE situacao = 'aguardando_aprovacao'");
        }
        if ($carteira) {
            $r['/agenda'] = (int) Banco::valor('SELECT COUNT(*) FROM tarefas t LEFT JOIN clientes c ON c.id = t.cliente_id
                WHERE t.concluida_em IS NULL AND t.usuario_id = ? AND t.vence_em <= CURDATE() AND (t.cliente_id IS NULL OR c.excluido_em IS NULL)', [$id]);
        }
        if ($comercial && $pode('pedidos.criar')) {
            // Meus pedidos recusados com aviso ainda não lido
            $r['/pedidos'] = (int) Banco::valor("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND tipo = 'pedido_recusado' AND lida_em IS NULL", [$id]);
        }
        if ($pode('cobranca.ver') && Foco::tem('cobranca')) {
            $r['/cobranca'] = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas cb LEFT JOIN cobranca_etapas e ON e.id = cb.etapa_id
                WHERE cb.situacao = 'aberta' AND (cb.promessa_em = CURDATE() OR e.tipo = 'sem_contato')");
        }
        if ($comercial && $pode('recompra.ver')) {
            $r['/recompra'] = VendasService::qtdRecompra();
        }
        if ($comercial && $pode('prospeccao.usar')) {
            [$where, $par] = Cliente::montarWhere(Cliente::filtrosDe(['tipo' => 'lead']));
            $r['/prospeccao'] = (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE $where AND c.contatado = 0 AND c.criado_em >= NOW() - INTERVAL 48 HOUR", $par);
        }
        if ($pode('leads.ver') && Foco::tem('marketing')) {
            $r['/leads'] = (int) Banco::valor("SELECT COUNT(*) FROM leads_recebidos WHERE resultado = 'criado' AND criado_em >= CURDATE()");
        }
        if (($pode('marketing.aprovar') || $pode('marketing.editar')) && Foco::tem('marketing')) {
            $tipo = $pode('marketing.aprovar') ? 'aprovacao' : 'ajuste';
            $r['/marketing'] = (int) Banco::valor('SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE e.tipo = ?', [$tipo]);
        }
        if ($pode('producao.gerenciar') && Foco::tem('producao')) {
            $a = ProducaoService::alertas();
            $r['/producao'] = $a['mp_abaixo'] + $a['reabastecer'];
        }
        // Produtos: documentos (laudos, registros...) vencidos ou vencendo — para quem cuida da parte técnica
        if ($pode('produtos.tecnico') && Foco::tem('producao')) {
            $r['/produtos'] = count(ProdutoService::documentosVencendo(99));
        }
        // Terceirização: rótulos de clientes abaixo do mínimo ou que não dão para as ordens planejadas
        if (($pode('terceirizacao.ver') || $pode('terceirizacao.editar')) && Foco::tem('terceirizacao')) {
            $r['/terceirizacao'] = count(\App\Services\TerceirizacaoService::alertas());
        }
        return $r;
    }
}
