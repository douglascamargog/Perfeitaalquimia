<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Sessao;
use App\Services\CompraService;
use App\Services\DepositoService as D;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Depósito: fila de separação, separar (lote e validade), despachar, rastrear lote e receber as compras.
 * Quem só tem deposito.ver (Gestor, Financeiro) enxerga tudo sem os botões.
 */
class DepositoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'deposito.css'];

    public function index(): void
    {
        $aba = in_array($_GET['aba'] ?? '', ['separados', 'despachados'], true) ? $_GET['aba'] : 'separar';
        $this->tela('deposito/index', ['titulo' => 'Depósito', 'aba' => $aba, 'fila' => D::fila($aba), 'resumo' => D::resumo(),
            'diasAtraso' => max(1, (int) Config::get('deposito.dias_atraso', 2)), 'pode' => Auth::pode('deposito.operar'), 'estilos' => self::ESTILOS]);
    }

    public function ver(string $id): void
    {
        $p = D::pedido((int) $id);
        if (!$p || $p['expedicao'] === null) {
            Sessao::aviso('erro', 'Este pedido não está na fila do depósito.');
            redirecionar('/deposito');
        }
        $this->tela('deposito/pedido', ['titulo' => 'Separar pedido #' . (int) $p['id'], 'p' => $p, 'pode' => Auth::pode('deposito.operar'),
            'loteObrigatorio' => (bool) Config::get('deposito.lote_obrigatorio', true),
            'embalagens' => D::embalagensDisponiveis(), 'usadas' => D::embalagensDoPedido((int) $p['id']), 'caixas' => D::caixasDoPedido((int) $p['id']),
            'embalagemObrigatoria' => (bool) Config::get('deposito.embalagem_obrigatoria', true),
            'transportadoras' => array_values(array_unique(array_merge((array) Config::get('deposito.transportadoras', []),
                array_column(\App\Core\Banco::todos("SELECT DISTINCT transportadora FROM pedidos WHERE transportadora IS NOT NULL AND transportadora <> '' ORDER BY transportadora LIMIT 30"), 'transportadora')))),
            'estilos' => self::ESTILOS, 'scripts' => ['deposito.js']]);
    }

    /** Ações: comecar | separar | concluir | despachar | urgente */
    public function acao(string $id, string $acao): void
    {
        $pid = (int) $id;
        $uid = (int) Auth::id();
        $voltar = "/deposito/$pid";
        try {
            switch ($acao) {
                case 'comecar':
                    D::comecar($pid, $uid);
                    Sessao::aviso('sucesso', 'Separação começada.');
                    break;
                case 'separar':
                    $falta = D::salvarSeparacao($pid, (array) ($_POST['item'] ?? []), $uid);
                    D::salvarEmbalagem($pid, (array) ($_POST['embalagem'] ?? []), $uid); // caixas usadas: baixa no estoque
                    if (!empty($_POST['concluir'])) {
                        Sessao::aviso('sucesso', D::concluir($pid, $uid, $this->campo('decisao'), $this->campo('observacao')));
                    } else {
                        Sessao::aviso($falta ? 'atencao' : 'sucesso', $falta ? 'Separação salva. Falta: ' . implode(', ', array_map(fn($f) => $f['falta'] . ' × ' . $f['nome'], $falta)) . '.' : 'Separação salva: tudo separado.');
                    }
                    break;
                case 'despachar':
                    D::despachar($pid, $_POST, $uid);
                    Sessao::aviso('sucesso', 'Pedido despachado. O vendedor foi avisado.');
                    $voltar = '/deposito';
                    break;
                case 'urgente':
                    D::marcarUrgente($pid, $this->campo('urgente') === '1', $uid);
                    Sessao::aviso('sucesso', 'Prioridade atualizada.');
                    $voltar = $this->campo('voltar') === 'fila' ? '/deposito' : $voltar;
                    break;
                default:
                    throw new RuntimeException('Ação inválida.');
            }
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            Sessao::guardarFormulario($_POST);
        }
        redirecionar($voltar);
    }

    public function lotes(): void
    {
        $busca = trim((string) ($_GET['q'] ?? ''));
        $this->tela('deposito/lotes', ['titulo' => 'Rastrear lote', 'busca' => $busca, 'linhas' => D::rastrear($busca), 'estilos' => self::ESTILOS]);
    }

    public function recebimento(): void
    {
        $this->tela('deposito/recebimento', ['titulo' => 'Recebimento', 'itens' => \App\Core\Banco::todos("SELECT c.*, ct.fornecedor, ct.prazo_dias, s.nome AS solicitante
                FROM compras c LEFT JOIN compra_cotacoes ct ON ct.id = c.cotacao_id LEFT JOIN usuarios s ON s.id = c.solicitado_por
                WHERE c.situacao = 'comprado' ORDER BY c.previsto_em IS NULL, c.previsto_em, c.id"),
            'recebidos' => \App\Core\Banco::todos("SELECT c.*, r.nome AS recebedor FROM compras c LEFT JOIN usuarios r ON r.id = c.recebido_por
                WHERE c.situacao = 'recebido' ORDER BY c.recebido_em DESC LIMIT 15"),
            'pode' => CompraService::podeReceber(), 'estilos' => self::ESTILOS]);
    }

    public function receber(string $id): void
    {
        try {
            if (!CompraService::podeReceber()) {
                throw new RuntimeException('Só o Depósito ou a Produção registram a chegada.');
            }
            $arquivo = $_FILES['arquivo_nf'] ?? null;
            $nf = $arquivo && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? \App\Services\ItemCompraService::guardar($arquivo) : null;
            CompraService::receber((int) $id, ProducaoService::numero($this->campo('quantidade')), $this->campo('observacao'), (int) Auth::id(), $this->campo('nf'), $nf);
            Sessao::aviso('sucesso', 'Chegada registrada.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/deposito/recebimento');
    }
}
