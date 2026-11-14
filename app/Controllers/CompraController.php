<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\CompraService as C;
use RuntimeException;

/**
 * Orçamentos e compras da Qualidade. A Qualidade monta (cotações) e escolhe quem aprova;
 * a pessoa escolhida decide na mesma página; depois "Comprei" e o Depósito recebe.
 */
class CompraController extends Controller
{
    private const ESTILOS = ['carteira.css', 'deposito.css'];

    public function index(): void
    {
        $pode = Auth::pode('compras.solicitar');
        $padrao = $pode || Auth::pode('compras.ver') ? 'abertos' : 'minhas';
        $aba = in_array($_GET['aba'] ?? '', ['abertos', 'aprovacao', 'concluidos', 'minhas'], true) ? $_GET['aba'] : $padrao;
        if (!$pode && !Auth::pode('compras.ver')) {
            $aba = 'minhas'; // quem só aprova vê os orçamentos esperando a decisão dela
        }
        $this->tela('compras/index', ['titulo' => 'Orçamentos e compras', 'aba' => $aba, 'lista' => C::lista($aba, Auth::id()),
            'qtdMinhas' => C::qtdParaAprovar((int) Auth::id()), 'sugestoes' => $pode ? C::sugestoes() : ['materias' => [], 'projetos' => []],
            'pode' => $pode, 'estilos' => self::ESTILOS]);
    }

    public function novo(): void
    {
        $this->exigirSolicitar();
        $c = ['id' => null, 'tipo' => $_GET['tipo'] ?? 'materia_prima', 'titulo' => $_GET['titulo'] ?? '', 'materia_prima_id' => (int) ($_GET['mp'] ?? 0) ?: null,
            'dp_projeto_id' => (int) ($_GET['projeto'] ?? 0) ?: null, 'quantidade' => $_GET['qtd'] ?? '', 'unidade' => 'un', 'motivo' => $_GET['motivo'] ?? '',
            'necessario_ate' => null, 'situacao' => 'rascunho'];
        $this->tela('compras/form', ['titulo' => 'Novo orçamento', 'c' => $c] + $this->listas());
    }

    public function criar(): void
    {
        $this->exigirSolicitar();
        try {
            $id = C::salvar(null, $_POST, (int) Auth::id());
            Sessao::aviso('sucesso', 'Orçamento criado. Agora adicione as cotações.');
            redirecionar("/compras/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/compras/novo', [$e->getMessage()]);
        }
    }

    public function ver(string $id): void
    {
        $c = C::buscar((int) $id);
        if (!$c || !C::podeVer($c)) {
            Sessao::aviso('erro', 'Orçamento não encontrado ou sem acesso.');
            redirecionar('/compras');
        }
        $this->tela('compras/ver', ['titulo' => $c['titulo'], 'c' => $c, 'cotacoes' => C::cotacoes((int) $c['id']), 'historico' => C::historico((int) $c['id']),
            'anteriores' => C::precosAnteriores($c['materia_prima_id'] ? (int) $c['materia_prima_id'] : null, (int) $c['id']),
            'aprovadores' => C::aprovadores(), 'pode' => Auth::pode('compras.solicitar'),
            'podeDecidir' => $c['situacao'] === 'aprovacao' && ((int) $c['aprovador_id'] === Auth::id() || Auth::pode('*')),
            'podeReceber' => C::podeReceber()] + $this->listas());
    }

    /** Ações: salvar | cotacao | remover_cotacao | enviar | decidir | comprar | cancelar */
    public function acao(string $id, string $acao): void
    {
        $cid = (int) $id;
        $uid = (int) Auth::id();
        try {
            if ($acao !== 'decidir') {
                $this->exigirSolicitar();
            }
            $msg = match ($acao) {
                'salvar'          => (function () use ($cid, $uid) { C::salvar($cid, $_POST, $uid); return 'Orçamento salvo.'; })(),
                'cotacao'         => (function () use ($cid, $uid) { C::adicionarCotacao($cid, $_POST, $_FILES['arquivo'] ?? [], $uid); return 'Cotação adicionada.'; })(),
                'remover_cotacao' => (function () use ($cid, $uid) { C::removerCotacao($cid, (int) $this->campo('cotacao'), $uid); return 'Cotação removida.'; })(),
                'enviar'          => (function () use ($cid, $uid) { C::enviar($cid, (int) $this->campo('cotacao'), (int) $this->campo('aprovador'), $this->campo('motivo_escolha'), $uid); return 'Enviado para aprovação.'; })(),
                'decidir'         => C::decidir($cid, $this->campo('decisao'), $this->campo('comentario'), $uid),
                'comprar'         => (function () use ($cid, $uid) { C::comprar($cid, $this->campo('previsto'), $uid); return 'Compra registrada. O Depósito vai receber.'; })(),
                'cancelar'        => (function () use ($cid, $uid) { C::cancelar($cid, $this->campo('motivo'), $uid); return 'Orçamento cancelado.'; })(),
                default           => throw new RuntimeException('Ação inválida.'),
            };
            Sessao::aviso('sucesso', $msg);
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            Sessao::guardarFormulario($_POST);
        }
        redirecionar("/compras/$cid");
    }

    /** Anexo da cotação: só para quem pode ver o orçamento */
    public function anexo(string $id): void
    {
        $cot = C::anexo((int) $id);
        $c = $cot ? C::buscar((int) $cot['compra_id']) : null;
        if (!$cot || !$c || !C::podeVer($c)) {
            http_response_code(404);
            Sessao::aviso('erro', 'Anexo não encontrado ou sem acesso.');
            redirecionar('/compras');
        }
        $nome = preg_replace('/[^\w\-. ]+/u', '', (string) $cot['arquivo_nome']) ?: 'orcamento';
        header('Content-Type: ' . $cot['arquivo_mime']);
        header('Content-Length: ' . filesize($cot['caminho']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $nome) . '"');
        readfile($cot['caminho']);
    }

    // ------------------------------------------------------------------

    private function listas(): array
    {
        return [
            'materias' => Banco::todos('SELECT id, nome, unidade, tipo, rotulo FROM materias_primas WHERE ativo = 1 ORDER BY nome'),
            'projetos' => Banco::todos("SELECT id, nome FROM dp_projetos WHERE situacao IN ('andamento', 'pausado') ORDER BY nome"),
            'estilos' => self::ESTILOS,
        ];
    }

    private function exigirSolicitar(): void
    {
        if (!Auth::pode('compras.solicitar')) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                Sessao::aviso('erro', 'Os orçamentos são feitos pela Qualidade.');
                redirecionar('/compras');
            }
            throw new RuntimeException('Os orçamentos são feitos pela Qualidade.');
        }
    }
}
