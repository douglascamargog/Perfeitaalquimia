<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Services\MensagemService;
use RuntimeException;

/**
 * Conteúdo › Mensagens prontas (cadastro) e o uso na ficha do cliente (abre o WhatsApp com o texto preenchido).
 */
class MensagemController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'prospeccao.css'];

    public function index(): void
    {
        $this->tela('mensagens/index', ['titulo' => 'Mensagens prontas', 'modelos' => MensagemService::modelos(false),
            'categorias' => MensagemService::categorias(), 'estilos' => self::ESTILOS]);
    }

    public function nova(): void
    {
        $this->formulario(null);
    }

    public function editar(string $id): void
    {
        $m = Banco::um('SELECT * FROM mensagens_prontas WHERE id = ?', [(int) $id]);
        if (!$m) {
            Sessao::aviso('erro', 'Mensagem não encontrada.');
            redirecionar('/mensagens');
        }
        $this->formulario($m);
    }

    private function formulario(?array $m): void
    {
        // Prévia com um cliente de exemplo (o primeiro da carteira de quem edita) e a pessoa logada
        [$where, $par] = Cliente::montarWhere(Cliente::filtrosDe([]));
        $exemploId = Banco::valor("SELECT c.id FROM clientes c WHERE $where AND c.ultima_compra_em IS NOT NULL ORDER BY c.id LIMIT 1", $par);
        $exemplo = $exemploId ? Cliente::buscar((int) $exemploId) : ['razao_social' => 'Loja Exemplo Ltda', 'nome_fantasia' => 'Loja Exemplo', 'cidade' => 'Porto Alegre', 'ultima_compra_em' => date('Y-m-d', strtotime('-45 days'))];
        $this->tela('mensagens/form', [
            'titulo' => $m ? 'Editar mensagem' : 'Nova mensagem', 'm' => $m, 'categorias' => MensagemService::categorias(),
            'variaveis' => MensagemService::VARIAVEIS, 'valores' => MensagemService::variaveis($exemplo, Auth::usuario()),
            'estilos' => self::ESTILOS, 'scripts' => ['mensagens.js'],
        ]);
    }

    public function criar(): void
    {
        try {
            MensagemService::salvar(null, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Mensagem criada.');
            redirecionar('/mensagens');
        } catch (RuntimeException $e) {
            $this->voltarComErros('/mensagens/nova', [$e->getMessage()]);
        }
    }

    public function atualizar(string $id): void
    {
        try {
            MensagemService::salvar((int) $id, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Mensagem salva.');
            redirecionar('/mensagens');
        } catch (RuntimeException $e) {
            $this->voltarComErros("/mensagens/$id", [$e->getMessage()]);
        }
    }

    /** POST da ficha: registra o uso (linha do tempo + contador) antes de abrir o WhatsApp */
    public function usar(string $id): void
    {
        $cliente = Cliente::buscar((int) $this->campo('cliente_id'));
        if (!$cliente || !Cliente::podeVer($cliente)) {
            $this->json(['ok' => false, 'mensagem' => 'Este cliente não está na sua carteira.'], 403);
            return;
        }
        try {
            MensagemService::registrarUso((int) $id, (int) $cliente['id'], (int) Auth::id());
            $this->json(['ok' => true]);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 422);
        }
    }
}
