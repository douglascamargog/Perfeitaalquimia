<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\SimuladorService as S;

/**
 * Administração › Dados de teste: gerar movimento simulado para as pessoas cadastradas e apagar tudo depois.
 */
class SimuladorController extends Controller
{
    public function index(): void
    {
        $this->tela('simulador/index', ['titulo' => 'Dados de teste', 'contagem' => S::contagem(), 'pessoas' => S::pessoas(), 'estilos' => ['carteira.css']]);
    }

    public function gerar(): void
    {
        try {
            $c = S::gerar(Auth::id());
        } catch (\Throwable $e) {
            error_log((string) $e);
            Sessao::aviso('erro', 'Não foi possível gerar os dados de teste: ' . mb_substr($e->getMessage(), 0, 300) . ' (nada foi gravado).');
            redirecionar('/sistema/dados-de-teste');
        }
        Sessao::aviso('sucesso', "Movimento simulado gerado: {$c['ligacoes']} ligações, {$c['contatos']} contatos, {$c['tarefas']} retornos, {$c['pedidos']} pedidos, "
            . "{$c['cobrancas']} casos de cobrança, {$c['ordens']} ordens e {$c['posts']} posts" . ($c['clientes'] ? " (e {$c['clientes']} clientes de teste)." : '.'));
        redirecionar('/sistema/dados-de-teste');
    }

    public function apagar(): void
    {
        if (trim((string) ($_POST['confirmar'] ?? '')) !== 'APAGAR') {
            Sessao::aviso('erro', 'Para apagar, digite APAGAR no campo de confirmação.');
            redirecionar('/sistema/dados-de-teste');
        }
        try {
            $c = S::apagar(Auth::id());
        } catch (\Throwable $e) {
            error_log((string) $e);
            Sessao::aviso('erro', 'Não foi possível apagar: ' . mb_substr($e->getMessage(), 0, 300));
            redirecionar('/sistema/dados-de-teste');
        }
        Sessao::aviso('sucesso', 'Dados simulados apagados (' . array_sum($c) . ' registros). Um backup do banco foi feito antes. O que a equipe fez de verdade continua.');
        redirecionar('/sistema/dados-de-teste');
    }
}
