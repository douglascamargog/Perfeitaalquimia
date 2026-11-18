<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Models\ClienteEvento;
use App\Models\Listas;
use App\Services\CarteiraService;
use App\Services\CarteirasService;

/**
 * Carteira › Lista: ações com as lojas marcadas (V15.9) — mudar etapa, mudar prioridade ou mover para outra carteira.
 * Cada loja passa pela mesma regra de quando é feito uma a uma (só lojas que a pessoa vê; linha do tempo e auditoria).
 */
class CarteiraLoteController extends Controller
{
    public function aplicar(): void
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))))), 0, 500);
        $acao = (string) ($_POST['acao'] ?? '');
        $voltar = '/carteira/lista';
        if (!$ids) {
            Sessao::aviso('erro', 'Marque as lojas na lista (a caixinha no começo de cada linha).');
            redirecionar($voltar);
        }
        // Só as lojas que a pessoa pode ver
        $lojas = array_filter(array_map(fn($id) => Cliente::buscar($id), $ids), fn($c) => $c && Cliente::podeVer($c));
        $n = 0;
        try {
            switch ($acao) {
                case 'etapa':
                    $etapa = (int) ($_POST['etapa_id'] ?? 0);
                    if (!in_array($etapa, array_map('intval', array_column(Listas::etapas(), 'id')), true)) {
                        throw new \RuntimeException('Escolha a etapa.');
                    }
                    foreach ($lojas as $c) {
                        CarteiraService::mudarEtapa((int) $c['id'], $etapa, (int) Auth::id());
                        $n++;
                    }
                    $texto = 'etapa mudada';
                    break;
                case 'prioridade':
                    $prioridade = (string) ($_POST['prioridade'] ?? '');
                    if (!in_array($prioridade, Listas::prioridades(), true)) {
                        throw new \RuntimeException('Escolha a prioridade.');
                    }
                    foreach ($lojas as $c) {
                        if ($c['prioridade'] !== $prioridade) {
                            Banco::atualizar('clientes', (int) $c['id'], ['prioridade' => $prioridade]);
                            ClienteEvento::registrar((int) $c['id'], 'etapa', "Prioridade: {$c['prioridade']} → $prioridade", (int) Auth::id());
                            Auditoria::registrar('cliente', (int) $c['id'], 'prioridade', ['prioridade' => $c['prioridade']], ['prioridade' => $prioridade]);
                        }
                        $n++;
                    }
                    $texto = 'prioridade mudada';
                    break;
                case 'carteira':
                    if (!CarteirasService::podeUsar()) {
                        throw new \RuntimeException('Mover de carteira é com a gestão (Gestão › Carteiras).');
                    }
                    $n = CarteirasService::mover(array_column($lojas, 'id'), (int) ($_POST['carteira_id'] ?? 0), (int) Auth::id());
                    $texto = 'movida(s) de carteira';
                    break;
                default:
                    throw new \RuntimeException('Escolha o que fazer com as lojas marcadas.');
            }
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar($voltar);
        }
        Sessao::aviso('sucesso', "$n loja(s): $texto.");
        redirecionar($voltar);
    }
}
