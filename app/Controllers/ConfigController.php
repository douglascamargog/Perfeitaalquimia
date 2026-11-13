<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Sessao;

/**
 * Tela de Configurações gerais (tabela `configuracoes`).
 * As listas próprias (funil, segmentos, regiões, pagamento) ganham telas junto com o módulo que as usa.
 */
class ConfigController extends Controller
{
    /** Configurações que não são mais usadas (ficam no banco, mas não aparecem na tela) */
    public const OBSOLETAS = ['carteira.campanha_origem', 'carteira.campanha_titulo', 'carteira.campanha_chamada'];

    public function index(): void
    {
        $grupos = [];
        foreach (Config::todas() as $item) {
            if (in_array($item['chave'], self::OBSOLETAS, true)) {
                continue;
            }
            $grupos[$item['grupo']][] = $item;
        }
        $this->tela('config/index', ['titulo' => 'Configurações', 'grupos' => $grupos]);
    }

    public function salvar(): void
    {
        $erros = [];
        $valores = (array) ($_POST['config'] ?? []);

        foreach (Config::todas() as $item) {
            // Caixa de seleção desmarcada não é enviada pelo navegador: vira "0"
            $valor = $item['tipo'] === 'booleano'
                ? (isset($valores[$item['chave']]) ? '1' : '0')
                : trim((string) ($valores[$item['chave']] ?? $item['valor']));

            if ($item['tipo'] === 'numero' && !is_numeric($valor)) {
                $erros[] = "\"{$item['rotulo']}\" precisa ser um número.";
                continue;
            }
            Config::definir($item['chave'], $valor);
        }

        if ($erros) {
            $this->voltarComErros('/configuracoes', $erros);
        }
        Sessao::aviso('sucesso', 'Configurações salvas.');
        redirecionar('/configuracoes');
    }
}
