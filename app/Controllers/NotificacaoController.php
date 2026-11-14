<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\NotificacaoService;
use App\Services\PendenciasService;

/**
 * Sino de notificações: lista, resumo para a atualização automática (a cada 60 s), abrir e marcar como lidas.
 * Cada pessoa só enxerga e só abre as próprias notificações.
 */
class NotificacaoController extends Controller
{
    public function index(): void
    {
        $this->tela('notificacoes/index', ['titulo' => 'Notificações', 'itens' => NotificacaoService::ultimas((int) Auth::id(), 200),
            'estilos' => ['carteira.css', 'painel.css']]);
    }

    /** JSON leve: não lidas, contadores do menu e as notificações novas desde o último id visto */
    public function resumo(): void
    {
        $id = (int) Auth::id();
        $depois = max(0, (int) ($_GET['depois'] ?? 0));
        // A pessoa continua "online" mesmo parada numa tela; guarda só o nome da aba
        \App\Services\Presenca::registrar($id, (string) ($_GET['onde'] ?? '/'));
        $online = \App\Services\Presenca::online($id);
        $this->json([
            'comunicados' => \App\Core\Visao::renderizar('comunicados/_faixa', ['comunicados' => \App\Services\ComunicadoService::ativosPara($id)]),
            'online_qtd' => count($online),
            'online_html' => \App\Core\Visao::renderizar('layout/_online', ['online' => $online]),
            'nao_lidas' => NotificacaoService::naoLidas($id),
            'pendencias' => PendenciasService::doUsuario(true), // a consulta automática (1 por minuto) sempre recalcula
            'novas' => $depois > 0 ? array_map(fn($n) => ['id' => (int) $n['id'], 'titulo' => $n['titulo'], 'texto' => $n['texto'], 'link' => url('/notificacoes/' . (int) $n['id'])],
                NotificacaoService::ultimas($id, 5, $depois)) : [],
            'ultima' => (int) (NotificacaoService::ultimas($id, 1)[0]['id'] ?? 0),
            // Letreiro do topo: pedidos novos desde o último que esta pessoa viu passar (só os dos últimos 10 minutos)
            'pedido_ultimo' => (int) \App\Core\Banco::valor('SELECT COALESCE(MAX(id), 0) FROM pedidos'),
            'letreiro' => self::letreiro((int) ($_GET['pedido'] ?? 0)),
            'lista' => array_map(fn($n) => ['id' => (int) $n['id'], 'titulo' => $n['titulo'], 'texto' => $n['texto'], 'lida' => (bool) $n['lida_em'],
                'quando' => data_br($n['criado_em'], true), 'tipo' => $n['tipo'], 'link' => url('/notificacoes/' . (int) $n['id'])],
                ($_GET['lista'] ?? '') === '1' ? NotificacaoService::ultimas($id, 12) : []),
        ]);
    }

    /** Frases do letreiro: "Novo pedido de Fulana · Cidade/UF" (liga/desliga em Configurações › Início) */
    private static function letreiro(int $depoisDe): array
    {
        if ($depoisDe <= 0 || !\App\Core\Config::get('inicio.letreiro_pedidos', true)) {
            return [];
        }
        $pedidos = \App\Core\Banco::todos("SELECT p.id, p.origem, u.nome AS vendedor, u.apelido, c.cidade, c.uf FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN usuarios u ON u.id = p.vendedor_id WHERE p.id > ? AND p.criado_em >= NOW() - INTERVAL 10 MINUTE AND p.situacao <> 'cancelado'
            ORDER BY p.id LIMIT 5", [$depoisDe]);
        return array_map(fn($p) => ['id' => (int) $p['id'], 'texto' => '🎉 Novo pedido feito por ' . ($p['vendedor'] ? \App\Services\EquipeService::comoChamar(['nome' => $p['vendedor'], 'apelido' => $p['apelido']])
            : 'um cliente pelo catálogo') . ' · ' . $p['cidade'] . '/' . $p['uf']], $pedidos);
    }

    /** Abre: marca como lida e leva para o link */
    public function abrir(string $id): void
    {
        $link = NotificacaoService::abrir((int) $id, (int) Auth::id());
        if ($link === null) {
            http_response_code(403);
            Sessao::aviso('erro', 'Esta notificação não é sua.');
            redirecionar('/notificacoes');
        }
        PendenciasService::limpar();
        redirecionar($link);
    }

    public function marcarTodas(): void
    {
        $n = NotificacaoService::marcarTodas((int) Auth::id());
        PendenciasService::limpar();
        if (\App\Core\Roteador::querJson()) {
            $this->json(['ok' => true, 'marcadas' => $n]);
            return;
        }
        Sessao::aviso('sucesso', $n ? "$n notificação(ões) marcada(s) como lida(s)." : 'Nenhuma notificação nova.');
        redirecionar('/notificacoes');
    }
}
