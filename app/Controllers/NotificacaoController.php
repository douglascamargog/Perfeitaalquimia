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
        $this->json([
            'nao_lidas' => NotificacaoService::naoLidas($id),
            'pendencias' => PendenciasService::doUsuario(true), // a consulta automática (1 por minuto) sempre recalcula
            'novas' => $depois > 0 ? array_map(fn($n) => ['id' => (int) $n['id'], 'titulo' => $n['titulo'], 'texto' => $n['texto'], 'link' => url('/notificacoes/' . (int) $n['id'])],
                NotificacaoService::ultimas($id, 5, $depois)) : [],
            'ultima' => (int) (NotificacaoService::ultimas($id, 1)[0]['id'] ?? 0),
            'lista' => array_map(fn($n) => ['id' => (int) $n['id'], 'titulo' => $n['titulo'], 'texto' => $n['texto'], 'lida' => (bool) $n['lida_em'],
                'quando' => data_br($n['criado_em'], true), 'tipo' => $n['tipo'], 'link' => url('/notificacoes/' . (int) $n['id'])],
                ($_GET['lista'] ?? '') === '1' ? NotificacaoService::ultimas($id, 12) : []),
        ]);
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
