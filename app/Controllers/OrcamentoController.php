<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Services\ModeloPedidoService;
use App\Services\OrcamentoService;
use App\Services\Setores;

/**
 * Orçamentos para o cliente (equipe de vendas) e modelos de pedido.
 *  - O orçamento é montado na tela do "Novo pedido" (botão "Gerar orçamento").
 *  - Aqui: lista, página do orçamento (link, WhatsApp, imagem), cancelar.
 *  - Modelos: salvar a partir da tela do pedido, ver e tirar do ar.
 */
class OrcamentoController extends Controller
{
    public function index(): void
    {
        $aba = in_array($_GET['aba'] ?? '', ['aprovados', 'encerrados'], true) ? $_GET['aba'] : 'abertos';
        $funcao = Setores::funcaoIdAtual();
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $this->tela('orcamentos/index', [
            'titulo' => 'Orçamentos', 'aba' => $aba, 'busca' => $busca,
            'orcamentos' => OrcamentoService::listar($aba, $funcao, $busca), 'contagem' => OrcamentoService::contar($funcao),
            'estilos' => ['carteira.css', 'pedidos.css'],
        ]);
    }

    /** Vem do formulário do "Novo pedido": cria o orçamento (ou atualiza o que está sendo editado) */
    public function criar(): void
    {
        $cliente = Cliente::buscar((int) $this->campo('cliente_id'));
        if (!$cliente || !Cliente::podeVer($cliente)) {
            redirecionar('/pedidos/novo');
        }
        $orcamento = ctype_digit($this->campo('orcamento_id')) ? OrcamentoService::buscar((int) $this->campo('orcamento_id')) : null;
        if ($orcamento && (!OrcamentoService::podeVer($orcamento) || (int) $orcamento['cliente_id'] !== (int) $cliente['id'])) {
            $orcamento = null;
        }
        [$forma, $condicao] = array_map('intval', explode(':', $this->campo('pagamento') . ':0'));
        [$id, $erros] = OrcamentoService::salvar($cliente, (array) ($_POST['qtd'] ?? []),
            ['forma_pagamento_id' => $forma, 'condicao_id' => $condicao, 'observacoes' => $this->campo('observacoes'),
             'bonificados' => (array) ($_POST['bonif'] ?? []), 'bonificacao_motivo' => $this->campo('bonificacao_motivo')], (int) Auth::id(), $orcamento);
        if ($erros) {
            $this->voltarComErros('/pedidos/novo?cliente=' . (int) $cliente['id'] . ($orcamento ? '&orcamento=' . (int) $orcamento['id'] : ''), $erros);
        }
        Sessao::aviso('sucesso', $orcamento ? 'Orçamento atualizado: o link do cliente continua o mesmo.' : "Orçamento #$id pronto. Agora é só enviar o link para o cliente.");
        redirecionar("/orcamentos/$id");
    }

    private function orcamentoOuErro(string $id): array
    {
        $o = ctype_digit($id) ? OrcamentoService::buscar((int) $id) : null;
        if (!$o || !OrcamentoService::podeVer($o)) {
            http_response_code(404);
            (new \App\Core\Roteador([]))->erro(404, 'Orçamento não encontrado.');
            exit;
        }
        return $o;
    }

    public function ver(string $id): void
    {
        $o = $this->orcamentoOuErro($id);
        $itens = OrcamentoService::itens((int) $o['id']);
        $pagamento = $o['forma_pagamento_id'] ? Banco::um('SELECT f.nome AS forma, cp.nome AS condicao FROM formas_pagamento f
            LEFT JOIN condicoes_pagamento cp ON cp.id = ? WHERE f.id = ?', [$o['condicao_id'], $o['forma_pagamento_id']]) : null;
        $this->tela('orcamentos/ver', [
            'titulo' => 'Orçamento #' . $o['id'], 'o' => $o, 'itens' => $itens, 'historico' => OrcamentoService::historico((int) $o['id']),
            'situacao' => OrcamentoService::situacao($o), 'link' => OrcamentoService::link($o), 'mensagem' => OrcamentoService::mensagem($o),
            'pagamento' => $pagamento, 'pedido' => $o['pedido_id'] ? Banco::um('SELECT id, total, situacao FROM pedidos WHERE id = ?', [$o['pedido_id']]) : null,
            'estilos' => ['carteira.css', 'pedidos.css'], 'scripts' => ['orcamento.js'],
        ]);
    }

    public function cancelar(string $id): void
    {
        $o = $this->orcamentoOuErro($id);
        $erro = OrcamentoService::cancelar($o, (int) Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Orçamento cancelado: o link do cliente não abre mais.');
        redirecionar('/orcamentos/' . (int) $o['id']);
    }

    /** O vendedor enviou/copiou o link ou baixou a imagem (chamado pela tela, sem recarregar) */
    public function enviado(string $id): void
    {
        $o = $this->orcamentoOuErro($id);
        OrcamentoService::registrarEnvio($o, (int) Auth::id(), $this->campo('como'));
        $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Modelos de pedido
    // ------------------------------------------------------------------

    public function modelos(): void
    {
        $this->tela('orcamentos/modelos', [
            'titulo' => 'Modelos de pedido', 'modelos' => ModeloPedidoService::disponiveis((int) Auth::id()),
            'podeCampanha' => ModeloPedidoService::podeCampanha(), 'estilos' => ['carteira.css', 'pedidos.css'],
        ]);
    }

    /** Vem do formulário do "Novo pedido" (botão "Salvar modelo"): guarda e volta para a mesma tela, com o que estava escolhido */
    public function salvarModelo(): void
    {
        $cliente = (int) $this->campo('cliente_id');
        $voltar = '/pedidos/novo?cliente=' . $cliente . (ctype_digit($this->campo('orcamento_id')) ? '&orcamento=' . (int) $this->campo('orcamento_id') : '');
        [$id, $erros] = ModeloPedidoService::salvar([
            'nome' => $this->campo('modelo_nome'), 'tipo' => $this->campo('modelo_tipo'),
            'funcao_id' => $this->campo('modelo_funcao'), 'validade_ate' => $this->campo('modelo_validade'),
        ], (array) ($_POST['qtd'] ?? []), (int) Auth::id());
        Sessao::guardarFormulario($_POST); // a tela volta com as quantidades escolhidas
        if ($erros) {
            foreach ($erros as $e) {
                Sessao::aviso('erro', $e);
            }
        } else {
            Sessao::aviso('sucesso', 'Modelo "' . $this->campo('modelo_nome') . '" salvo. Ele aparece em "Usar um modelo".');
        }
        redirecionar($voltar);
    }

    public function removerModelo(string $id): void
    {
        $erro = ModeloPedidoService::desativar((int) $id, (int) Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Modelo retirado.');
        redirecionar('/pedidos/modelos');
    }
}
