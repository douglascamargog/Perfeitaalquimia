<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Models\Listas;
use App\Services\CarteiraService;

/**
 * Fila de reativação: clientes que já compraram, mas estão sem comprar há mais de X dias.
 * Eles pertencem à função configurada (padrão: Prospecção e Reativação). Reativado → volta para um vendedor.
 */
class ReativacaoController extends Controller
{
    public function index(): void
    {
        $funcaoId = CarteiraService::funcaoId((string) Config::get('carteira.funcao_reativacao', 'prospeccao_reativacao'));
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $filtros = Cliente::filtrosDe(['funcao' => (string) $funcaoId, 'tipo' => 'cliente']);
        $dias = (int) Config::get('carteira.dias_inatividade', 90);

        // Quantos clientes já passaram do prazo e ainda não estão na fila (aparecem no botão "processar agora")
        $aguardando = (int) Banco::valor(
            'SELECT COUNT(*) FROM clientes WHERE excluido_em IS NULL AND ultima_compra_em < CURDATE() - INTERVAL ? DAY AND (funcao_id IS NULL OR funcao_id <> ?)',
            [$dias, $funcaoId ?? 0]
        );
        $this->tela('reativacao/index', [
            'titulo'     => 'Reativação',
            'resultado'  => Cliente::listar($filtros, $pagina, 50, 'compra'),
            'dias'       => $dias,
            'aguardando' => Auth::pode('carteira.ver_todas') ? $aguardando : 0,
            'vendedores' => Auth::pode('carteira.transferir') || Auth::temFuncao('prospeccao_reativacao') ? Listas::vendedores() : [],
            'estilos'    => ['carteira.css'],
            'scripts'    => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    /** Roda a regra agora (o cron faz isso automaticamente todo dia) */
    public function processar(): void
    {
        $movidos = CarteiraService::processarInatividade(Auth::id());
        Sessao::aviso('sucesso', $movidos ? "$movidos cliente(s) sem compra foram para a fila de reativação." : 'Nenhum cliente novo para reativar.');
        redirecionar('/reativacao');
    }

    public function devolver(string $id): void
    {
        $cliente = Cliente::buscar((int) $id);
        $vendedor = (int) $this->campo('vendedor_id');
        if (!$cliente || !Cliente::podeVer($cliente) || !in_array($vendedor, array_map('intval', array_column(Listas::vendedores(), 'id')), true)) {
            Sessao::aviso('erro', 'Escolha o vendedor que vai receber o cliente.');
            redirecionar('/reativacao');
        }
        CarteiraService::devolverParaVendedor((int) $id, $vendedor, Auth::id());
        Sessao::aviso('sucesso', 'Cliente devolvido para o vendedor.');
        redirecionar('/reativacao');
    }
}
