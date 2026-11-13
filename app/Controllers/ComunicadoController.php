<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ComunicadoService as C;
use RuntimeException;

/** Comunicados: histórico de quem recebe; emitir, acompanhar a leitura e encerrar (comunicados.emitir). */
class ComunicadoController extends Controller
{
    public function index(): void
    {
        $pode = Auth::pode('comunicados.emitir');
        $this->tela('comunicados/index', [
            'titulo' => 'Comunicados', 'pode' => $pode,
            'recebidos' => C::recebidos((int) Auth::id()),
            'emitidos' => $pode ? C::emitidos() : [],
            'funcoes' => $pode ? Banco::todos('SELECT id, nome FROM funcoes WHERE ativa = 1 ORDER BY ordem, nome') : [],
        ]);
    }

    public function emitir(): void
    {
        try {
            C::emitir($_POST, Auth::id());
            Sessao::aviso('sucesso', 'Comunicado enviado. Ele aparece em destaque no topo da tela de quem recebe.');
        } catch (RuntimeException $e) {
            $this->voltarComErros('/comunicados', [$e->getMessage()]);
        }
        redirecionar('/comunicados');
    }

    /** "Li e entendi" (JSON, sem recarregar a página) */
    public function ler(string $id): void
    {
        C::marcarLido((int) $id, (int) Auth::id());
        $restantes = C::ativosPara((int) Auth::id());
        $this->json(['ok' => true, 'restantes' => count($restantes),
            'faixa' => \App\Core\Visao::renderizar('comunicados/_faixa', ['comunicados' => $restantes])]);
    }

    public function encerrar(string $id): void
    {
        C::encerrar((int) $id, Auth::id());
        Sessao::aviso('sucesso', 'Comunicado encerrado: saiu do topo da tela de todos.');
        redirecionar('/comunicados');
    }
}
