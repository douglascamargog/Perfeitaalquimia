<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ImportacaoService;

/**
 * Tela de importação: envio do arquivo → pré-visualização → confirmar ou cancelar.
 */
class ImportacaoController extends Controller
{
    private const TAMANHO_MAXIMO = 10 * 1024 * 1024; // 10 MB

    public function index(): void
    {
        $this->tela('importacao/index', [
            'titulo'      => 'Importar carteira',
            'importacoes' => Banco::todos('SELECT i.*, u.nome AS usuario_nome FROM importacoes i JOIN usuarios u ON u.id = i.usuario_id ORDER BY i.id DESC LIMIT 20'),
            'estilos'     => ['carteira.css'],
        ]);
    }

    public function enviar(): void
    {
        $arquivo = $_FILES['arquivo'] ?? null;
        $extensao = strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            $this->voltarComErros('/carteira/importar', ['Escolha um arquivo para enviar.']);
        }
        if (!in_array($extensao, ['csv', 'xlsx', 'json'], true)) {
            $this->voltarComErros('/carteira/importar', ['Formato não aceito. Use CSV, XLSX ou o Backup JSON da Carteira V4.']);
        }
        if ($arquivo['size'] > self::TAMANHO_MAXIMO) {
            $this->voltarComErros('/carteira/importar', ['Arquivo maior que 10 MB. Divida em partes.']);
        }
        try {
            $id = ImportacaoService::prever($arquivo['tmp_name'], (string) $arquivo['name'], Auth::id());
        } catch (\Throwable $e) {
            error_log((string) $e);
            $this->voltarComErros('/carteira/importar', ['Não foi possível ler o arquivo: ' . $e->getMessage()]);
        } finally {
            @unlink($arquivo['tmp_name']); // o arquivo não fica guardado no servidor (LGPD)
        }
        redirecionar('/carteira/importar/' . $id);
    }

    public function previa(string $id): void
    {
        $importacao = Banco::um('SELECT * FROM importacoes WHERE id = ?', [(int) $id]);
        if (!$importacao) {
            redirecionar('/carteira/importar');
        }
        $filtro = in_array($_GET['acao'] ?? '', ['novo', 'atualizar', 'duplicado', 'erro'], true) ? $_GET['acao'] : '';
        $linhas = Banco::todos(
            'SELECT * FROM importacao_linhas WHERE importacao_id = ?' . ($filtro ? ' AND acao = ?' : '') . ' ORDER BY linha LIMIT 300',
            $filtro ? [(int) $id, $filtro] : [(int) $id]
        );
        $this->tela('importacao/previa', ['titulo' => 'Pré-visualização da importação', 'importacao' => $importacao, 'linhas' => $linhas, 'filtro' => $filtro, 'estilos' => ['carteira.css']]);
    }

    public function confirmar(string $id): void
    {
        try {
            [$novos, $atualizados] = ImportacaoService::confirmar((int) $id, Auth::id());
            Sessao::aviso('sucesso', "Importação concluída: $novos estabelecimento(s) novo(s) e $atualizados cadastro(s) completado(s).");
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/carteira/importar');
    }

    public function cancelar(string $id): void
    {
        Banco::executar("UPDATE importacoes SET status = 'cancelada' WHERE id = ? AND status = 'previa'", [(int) $id]);
        Banco::executar('DELETE FROM importacao_linhas WHERE importacao_id = ?', [(int) $id]); // não guarda dados à toa (LGPD)
        Auditoria::registrar('importacao', (int) $id, 'cancelar');
        Sessao::aviso('sucesso', 'Importação cancelada. Nada foi gravado na carteira.');
        redirecionar('/carteira/importar');
    }
}
