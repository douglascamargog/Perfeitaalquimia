<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\MetaService;

/**
 * Gestão › Metas: meta do mês por vendedor e por função, com faturado, projeção e histórico.
 * A meta da equipe continua no Meu mês (mesma tela de sempre do gestor).
 */
class MetasController extends Controller
{
    public function index(): void
    {
        $mes = MetaService::mesValido($_GET['mes'] ?? null);
        $this->tela('metas/index', [
            'titulo' => 'Metas', 'mes' => $mes,
            'vendedores' => MetaService::grade($mes, 'vendedor'), 'funcoes' => MetaService::grade($mes, 'funcao'),
            'equipe' => MetaService::equipe($mes),
            'historico' => ['vendedor' => MetaService::historico('vendedor'), 'funcao' => MetaService::historico('funcao')],
            'estilos' => ['carteira.css', 'inicio.css', 'painel.css'],
        ]);
    }

    /** Salva a grade inteira (metas[tipo][alvo_id] = valor) */
    public function salvar(): void
    {
        $mes = MetaService::mesValido($this->campo('mes'));
        $mudou = 0;
        foreach (['vendedor', 'funcao'] as $tipo) {
            $validos = array_map(fn($a) => (int) $a['id'], MetaService::alvos($tipo));
            foreach ((array) ($_POST['metas'][$tipo] ?? []) as $alvo => $valor) {
                if (in_array((int) $alvo, $validos, true)) {
                    $mudou += MetaService::definirMeta($mes, $tipo, (int) $alvo, MeuMesController::valor((string) $valor), Auth::id()) ? 1 : 0;
                }
            }
        }
        Sessao::aviso('sucesso', $mudou ? "$mudou meta(s) salva(s)." : 'Nenhuma meta mudou.');
        redirecionar("/metas?mes=$mes");
    }

    public function copiar(): void
    {
        $mes = MetaService::mesValido($this->campo('mes'));
        $n = MetaService::copiarMesAnterior($mes, Auth::id());
        Sessao::aviso('sucesso', $n ? "$n meta(s) copiada(s) do mês anterior. Ajuste o que precisar." : 'Todas as metas deste mês já estavam preenchidas.');
        redirecionar("/metas?mes=$mes");
    }
}
