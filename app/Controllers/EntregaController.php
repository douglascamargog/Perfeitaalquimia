<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Services\CarteiraService;
use App\Services\EntregaService;
use App\Models\Listas;
use App\Services\Normalizar;

/**
 * Carteira › Endereços de entrega a revisar (V16.3): lojas cuja observação parece trazer um endereço de entrega
 * (comum nos cadastros vindos do Nota10). O One não adivinha: a vendedora (ou a gestão) confere e confirma.
 */
class EntregaController extends Controller
{
    public function index(): void
    {
        [$w, $p] = Cliente::montarWhere([]);
        $lojas = Banco::todos('SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.observacoes, c.entrega_revisar, u.nome AS responsavel
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE ' . $w . ' AND c.entrega_revisar IS NOT NULL
            ORDER BY c.nome_fantasia, c.razao_social LIMIT 200', $p);
        $this->tela('carteira/entregas', ['titulo' => 'Endereços de entrega a revisar', 'lojas' => $lojas, 'ufs' => Listas::ufs(),
            'podeProcurar' => Auth::pode('carteira.ver_todas'), 'estilos' => ['carteira.css']]);
    }

    /** Confirma o endereço de entrega digitado, ou marca que o texto não é endereço de entrega */
    public function salvar(string $id): void
    {
        $c = Cliente::buscar((int) $id);
        if (!$c || !Cliente::podeVer($c)) {
            redirecionar('/carteira/entregas');
        }
        $dados = ['entrega_revisar' => null];
        if ($this->campo('acao') === 'confirmar') {
            foreach (['entrega_endereco' => 150, 'entrega_numero' => 20, 'entrega_complemento' => 60, 'entrega_bairro' => 60, 'entrega_cidade' => 80] as $k => $max) {
                $dados[$k] = Normalizar::texto($this->campo($k), $max);
            }
            $dados['entrega_uf'] = in_array($this->campo('entrega_uf'), Listas::ufs(), true) ? $this->campo('entrega_uf') : null;
            $dados['entrega_cep'] = preg_replace('/\D/', '', $this->campo('entrega_cep')) ?: null;
            if (!$dados['entrega_endereco'] || !$dados['entrega_cidade'] || !$dados['entrega_uf']) {
                Sessao::aviso('erro', 'Para confirmar, informe pelo menos o endereço, a cidade e a UF de entrega.');
                redirecionar('/carteira/entregas#loja-' . (int) $id);
            }
        }
        CarteiraService::salvar((int) $id, $dados, null, (int) Auth::id(), false);
        Sessao::aviso('sucesso', $this->campo('acao') === 'confirmar' ? 'Endereço de entrega confirmado: os próximos pedidos já vão para ele.' : 'Certo: a loja saiu da revisão.');
        redirecionar('/carteira/entregas');
    }

    /** Procura nas observações de todas as lojas (gestão) */
    public function procurar(): void
    {
        $n = EntregaService::marcarParaRevisar();
        Sessao::aviso('sucesso', $n ? "$n loja(s) com endereço de entrega na observação entraram na revisão." : 'Nenhuma loja nova com endereço de entrega na observação.');
        redirecionar('/carteira/entregas');
    }
}
