<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Services\EquipeVendasService as E;
use App\Services\MetaService;

/** Equipe de vendas: meta da equipe, as 3 frentes (Externos, Televendas, Terceirização) e o ranking do mês. */
class EquipeController extends Controller
{
    public function index(): void
    {
        $mes = MetaService::mesValido($_GET['mes'] ?? null);
        $frente = array_key_exists($_GET['frente'] ?? '', E::FRENTES) ? $_GET['frente'] : '';
        $this->tela('equipe/index', [
            'titulo' => 'Equipe de vendas', 'mes' => $mes, 'frente' => $frente,
            'equipe' => MetaService::equipe($mes), 'podeEquipe' => Auth::pode('metas.gerenciar'),
            'frentes' => E::frentes($mes), 'ranking' => E::ranking($mes, $frente), 'grafico' => E::porMes($mes),
            'estilos' => ['carteira.css', 'meu_mes.css', 'equipe.css'],
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'equipe.js'],
        ]);
    }
}
