<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Core\Visao;
use App\Models\Cliente;
use App\Models\ClienteEvento;
use App\Models\Cobranca;
use App\Models\Listas;
use App\Services\CobrancaService;

/**
 * Cobrança: funil próprio, lista, caso (gaveta), tentativas, promessas, importação de títulos do ERP
 * e o "Enviar para cobrança" da ficha do cliente.
 * Ações chamadas pelo JavaScript respondem JSON; formulários comuns redirecionam.
 */
class CobrancaController extends Controller
{
    /** Dados comuns de filtro das telas */
    private function opcoes(): array
    {
        return ['etapas' => Listas::etapasCobranca(), 'responsaveis' => Cobranca::responsaveis(), 'ufs' => Listas::ufsComClientes(), 'faixas' => Cobranca::FAIXAS];
    }

    public function funil(): void
    {
        CobrancaService::verificarPromessas(); // promessa vencida volta para tentativa
        $filtros = Cobranca::filtrosDaUrl();
        $this->tela('cobranca/funil', [
            'titulo' => 'Cobrança', 'filtros' => $filtros, 'opcoes' => $this->opcoes(), 'resumo' => Cobranca::resumo(),
            'colunas' => Cobranca::funil($filtros), 'aba' => 'funil',
            'estilos' => ['carteira.css', 'cobranca.css'], 'scripts' => ['carteira.js', 'funil.js', 'busca_contatos.js'],
        ]);
    }

    public function lista(): void
    {
        $filtros = Cobranca::filtrosDaUrl();
        $this->tela('cobranca/lista', [
            'titulo' => 'Cobrança', 'filtros' => $filtros, 'opcoes' => $this->opcoes(), 'resumo' => Cobranca::resumo(),
            'resultado' => Cobranca::listar($filtros, (int) ($_GET['pagina'] ?? 1)), 'aba' => 'lista',
            'estilos' => ['carteira.css', 'cobranca.css'], 'scripts' => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    /** Relatório para imprimir (A4) */
    public function imprimir(): void
    {
        $filtros = Cobranca::filtrosDaUrl();
        $this->tela('cobranca/imprimir', ['titulo' => 'Relatório de cobrança', 'filtros' => $filtros, 'casos' => Cobranca::todos($filtros),
            'resumo' => Cobranca::resumo(), 'estilos' => ['cobranca.css']]);
    }

    /** CSV dos casos (abre no Excel). Sem dados de contato pessoais além dos da empresa */
    public function exportar(): void
    {
        $casos = Cobranca::todos(Cobranca::filtrosDaUrl());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cobranca-' . date('Y-m-d') . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, ['Cliente', 'CNPJ', 'Cidade', 'UF', 'Valor em aberto', 'Vencimento mais antigo', 'Dias de atraso', 'Etapa', 'Promessa', 'Responsável', 'Tentativas sem sucesso'], ';');
        foreach ($casos as $c) {
            fputcsv($saida, [$c['nome_fantasia'] ?: $c['razao_social'], cnpj_formatado($c['cnpj']), $c['cidade'], $c['uf'],
                number_format((float) $c['valor_total'], 2, ',', '.'), $c['vencimento_mais_antigo'] ? data_br($c['vencimento_mais_antigo']) : '',
                $c['dias_atraso'], $c['etapa'], $c['promessa_em'] ? data_br($c['promessa_em']) : '', $c['responsavel'], $c['tentativas_sem_sucesso']], ';');
        }
        fclose($saida);
    }

    // ---------------- Caso (gaveta) ----------------

    private function casoOuErro(string $id): array
    {
        $caso = Cobranca::buscar((int) $id);
        if (!$caso) {
            $this->json(['ok' => false, 'mensagem' => 'Caso de cobrança não encontrado.'], 404);
            exit;
        }
        return $caso;
    }

    /** HTML do caso para a gaveta lateral */
    public function caso(string $id): void
    {
        $caso = $this->casoOuErro($id);
        echo Visao::renderizar('cobranca/_caso', [
            'c'            => $caso,
            'titulos'      => Cobranca::titulos((int) $caso['id']),
            'eventos'      => ClienteEvento::doCliente((int) $caso['cliente_id'], 40),
            'etapas'       => Listas::etapasCobranca(),
            'responsaveis' => Cobranca::responsaveis(),
            'mensagens'    => CobrancaService::mensagens($caso),
            'resultados'   => array_keys(CobrancaService::RESULTADOS),
            'podeGerenciar' => Auth::pode('cobranca.gerenciar'),
        ]);
    }

    public function tentativa(string $id): void
    {
        $caso = $this->casoOuErro($id);
        $resultado = array_key_exists($this->campo('resultado'), CobrancaService::RESULTADOS) ? $this->campo('resultado') : 'Não atendeu';
        $canal = in_array($this->campo('canal'), ['Ligação', 'WhatsApp', 'E-mail', 'Visita presencial'], true) ? $this->campo('canal') : 'Ligação';
        $mensagem = CobrancaService::registrarTentativa((int) $caso['id'], $resultado, $canal, mb_substr($this->campo('nota'), 0, 500), Auth::id());
        $this->json(['ok' => true, 'mensagem' => $mensagem]);
    }

    public function promessa(string $id): void
    {
        $caso = $this->casoOuErro($id);
        $data = $this->campo('data');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d')) {
            $this->json(['ok' => false, 'mensagem' => 'Informe a data prometida (hoje ou depois).'], 422);
            return;
        }
        CobrancaService::registrarPromessa((int) $caso['id'], $data, MeuMesController::valor($this->campo('valor')) ?? 0.0, Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Promessa registrada. Uma tarefa foi criada para conferir o pagamento em ' . data_br($data) . '.']);
    }

    /** Muda a etapa (arrastar no funil ou select da gaveta) */
    public function etapa(string $id): void
    {
        $caso = $this->casoOuErro($id);
        $etapaId = (int) $this->campo('etapa_id');
        if (!Banco::valor('SELECT 1 FROM cobranca_etapas WHERE id = ? AND ativa = 1', [$etapaId])) {
            $this->json(['ok' => false, 'mensagem' => 'Etapa inválida.'], 422);
            return;
        }
        CobrancaService::mudarEtapa((int) $caso['id'], $etapaId, Auth::id(), mb_substr($this->campo('motivo'), 0, 80));
        $this->json(['ok' => true, 'mensagem' => 'Etapa atualizada.']);
    }

    public function responsavel(string $id): void
    {
        $caso = $this->casoOuErro($id);
        $novo = (int) $this->campo('responsavel_id');
        if (!in_array($novo, array_map('intval', array_column(Cobranca::responsaveis(), 'id')), true)) {
            $this->json(['ok' => false, 'mensagem' => 'Escolha uma pessoa da cobrança.'], 422);
            return;
        }
        CobrancaService::transferir((int) $caso['id'], $novo, Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Responsável atualizado.']);
    }

    public function observacoes(string $id): void
    {
        $caso = $this->casoOuErro($id);
        $texto = mb_substr($this->campo('observacoes'), 0, 2000);
        Banco::atualizar('cobrancas', (int) $caso['id'], ['observacoes' => $texto !== '' ? $texto : null]);
        \App\Core\Auditoria::registrar('cobranca', (int) $caso['id'], 'editar', ['observacoes' => $caso['observacoes']], ['observacoes' => $texto]);
        $this->json(['ok' => true, 'mensagem' => 'Observações salvas.']);
    }

    public function tituloPago(string $id): void
    {
        $titulo = Banco::um('SELECT * FROM cobranca_titulos WHERE id = ?', [(int) $id]);
        if (!$titulo) {
            $this->json(['ok' => false, 'mensagem' => 'Título não encontrado.'], 404);
            return;
        }
        CobrancaService::baixarTitulo((int) $titulo['id'], Auth::id());
        $this->json(['ok' => true, 'mensagem' => 'Título marcado como pago.']);
    }

    /** Lê os títulos vencidos do ERP (simulado no protótipo) */
    public function importar(): void
    {
        $r = CobrancaService::importarDoErp(Auth::id());
        Sessao::aviso('sucesso', sprintf('Títulos do ERP lidos: %d caso(s) novo(s) ou atualizado(s), %d título(s) pago(s) baixado(s)%s.',
            $r['casos'], $r['pagos'], $r['sem_cadastro'] ? ", {$r['sem_cadastro']} CNPJ(s) sem cadastro na carteira" : ''));
        redirecionar('/cobranca');
    }

    // ---------------- Enviar da ficha do cliente ----------------

    /** "Enviar para cobrança": cria o caso com um título informado à mão */
    public function enviar(string $clienteId): void
    {
        $cliente = Cliente::buscar((int) $clienteId);
        if (!$cliente || (!Cliente::podeVer($cliente) && !Auth::pode('cobranca.ver'))) {
            $this->json(['ok' => false, 'mensagem' => 'Cliente não encontrado.'], 404);
            return;
        }
        $valor = MeuMesController::valor($this->campo('valor')) ?? 0.0;
        $vencimento = $this->campo('vencimento');
        if ($valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimento) || $vencimento > date('Y-m-d')) {
            $this->json(['ok' => false, 'mensagem' => 'Informe o valor em aberto e a data de vencimento (já vencida).'], 422);
            return;
        }
        $jaTinha = CobrancaService::casoAberto((int) $cliente['id']);
        CobrancaService::abrir((int) $cliente['id'], [['documento' => $this->campo('documento'), 'valor' => $valor, 'vencimento' => $vencimento]],
            'manual', Auth::id(), mb_substr($this->campo('observacao'), 0, 300));
        $this->json(['ok' => true, 'mensagem' => $jaTinha ? 'Título acrescentado ao caso de cobrança que já estava aberto.' : 'Cliente enviado para a Cobrança.']);
    }
}
