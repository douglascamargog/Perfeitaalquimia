<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\CarteirasService as CS;
use App\Services\ImportacaoService;
use App\Services\Setores;
use RuntimeException;

/**
 * Gestão › Carteiras: ver a carteira de cada vendedor(a) (e as SEM vendedor(a)), passar a carteira inteira
 * para outra pessoa, mover clientes entre carteiras, juntar carteiras, criar carteira nova e importar
 * clientes direto para uma carteira (CSV, XLSX ou XML).
 * Quem usa: "carteiras.gerenciar" (Admin e Gestor) ou a gestora do setor (só os setores dela).
 */
class CarteirasController extends Controller
{
    private const ESTILOS = ['carteira.css', 'carteiras.css'];

    public function index(): void
    {
        $this->exigir();
        CS::encaixar(); // garante cada cliente na carteira do seu responsável (depois de transferências por outras telas)
        $setor = (int) ($_GET['setor'] ?? 0) ?: null;
        $carteiras = CS::lista($setor);
        $this->tela('carteiras/index', [
            'titulo' => 'Carteiras', 'carteiras' => $carteiras, 'setor' => $setor, 'setores' => $this->setores(),
            'vendedores' => CS::vendedores(), 'estilos' => self::ESTILOS,
        ]);
    }

    public function ver(string $id): void
    {
        $k = $this->carteira($id);
        $busca = trim((string) ($_GET['q'] ?? ''));
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $this->tela('carteiras/ver', [
            'titulo' => 'Carteira · ' . $k['nome'], 'k' => $k, 'busca' => $busca, 'pagina' => $pagina,
            'resultado' => CS::clientes((int) $k['id'], $busca, $pagina), 'outras' => array_filter(CS::lista(), fn($o) => (int) $o['id'] !== (int) $k['id']),
            'vendedores' => CS::vendedores(), 'setores' => $this->setores(), 'estilos' => self::ESTILOS, 'scripts' => ['carteiras.js'],
        ]);
    }

    public function criar(): void
    {
        $this->exigir();
        try {
            $id = CS::criar($this->campo('nome'), (int) $this->campo('funcao_id'), (int) $this->campo('vendedor_id') ?: null, Auth::id());
            Sessao::aviso('sucesso', 'Carteira criada. Agora coloque clientes nela (mover ou importar).');
            redirecionar("/carteiras/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/carteiras', [$e->getMessage()]);
        }
    }

    public function editar(string $id): void
    {
        $this->acao($id, fn($k) => CS::editar((int) $k['id'], $this->campo('nome'), (int) $this->campo('funcao_id'), Auth::id()) ?? 'Carteira atualizada.');
    }

    /** Passar a carteira inteira para outra pessoa (vendedor_id vazio = deixar sem vendedor(a)) */
    public function passar(string $id): void
    {
        $this->acao($id, function ($k) {
            $novo = (int) $this->campo('vendedor_id') ?: null;
            $n = CS::passar((int) $k['id'], $novo, Auth::id());
            return $novo ? "Carteira passada: $n cliente(s) mudaram de vendedor(a)." : "Carteira sem vendedor(a): $n cliente(s) ficam parados até alguém assumir.";
        });
    }

    public function juntar(string $id): void
    {
        $destino = (int) $this->campo('destino_id');
        $this->acao($id, fn($k) => 'Carteiras juntadas: ' . CS::juntar((int) $k['id'], $destino, Auth::id()) . ' cliente(s) mudaram.', '/carteiras/' . $destino);
    }

    /** Mover os clientes marcados (cliente[]) para outra carteira */
    public function mover(string $id): void
    {
        $destino = (int) $this->campo('destino_id');
        $this->acao($id, fn($k) => CS::mover((array) ($_POST['cliente'] ?? []), $destino, Auth::id()) . ' cliente(s) mudaram para a outra carteira.');
    }

    public function exportar(string $id): void
    {
        $k = $this->carteira($id);
        $linhas = Banco::todos("SELECT c.razao_social, c.nome_fantasia, c.cnpj, c.cidade, c.uf, c.telefone, c.whatsapp, c.email, c.ultima_compra_em
            FROM clientes c WHERE c.carteira_id = ? AND c.excluido_em IS NULL ORDER BY c.razao_social", [$k['id']]);
        Auditoria::registrar('carteira', (int) $k['id'], 'exportar', null, ['clientes' => count($linhas)]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="carteira-' . (int) $k['id'] . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, ['Razão social', 'Nome fantasia', 'CNPJ', 'Cidade', 'UF', 'Telefone', 'WhatsApp', 'E-mail', 'Última compra'], ';', '"', '');
        foreach ($linhas as $l) {
            $l['ultima_compra_em'] = $l['ultima_compra_em'] ? data_br($l['ultima_compra_em']) : '';
            fputcsv($saida, array_values($l), ';', '"', '');
        }
        fclose($saida);
    }

    // ---------- Importar direto para a carteira (prévia → confirmar) ----------

    public function importar(string $id): void
    {
        $k = $this->carteira($id);
        $arquivo = $_FILES['arquivo'] ?? null;
        $extensao = strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION));
        $volta = '/carteiras/' . (int) $k['id'];
        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            $this->voltarComErros($volta, ['Escolha um arquivo para enviar.']);
        }
        if (!in_array($extensao, ['csv', 'xlsx', 'xml'], true)) {
            $this->voltarComErros($volta, ['Formato não aceito. Use CSV, XLSX ou XML.']);
        }
        if ($arquivo['size'] > 10 * 1024 * 1024) {
            $this->voltarComErros($volta, ['Arquivo maior que 10 MB. Divida em partes.']);
        }
        try {
            $imp = ImportacaoService::prever($arquivo['tmp_name'], (string) $arquivo['name'], Auth::id(), (int) $k['id']);
        } catch (\Throwable $e) {
            $this->voltarComErros($volta, ['Não foi possível ler o arquivo: ' . $e->getMessage()]);
        } finally {
            @unlink($arquivo['tmp_name']); // o arquivo não fica guardado no servidor (LGPD)
        }
        redirecionar('/carteiras/importacao/' . $imp);
    }

    public function previa(string $id): void
    {
        $imp = $this->importacao($id);
        $filtro = in_array($_GET['acao'] ?? '', ['novo', 'atualizar', 'duplicado', 'erro'], true) ? $_GET['acao'] : '';
        $linhas = Banco::todos('SELECT * FROM importacao_linhas WHERE importacao_id = ?' . ($filtro ? ' AND acao = ?' : '') . ' ORDER BY linha LIMIT 300',
            $filtro ? [(int) $id, $filtro] : [(int) $id]);
        $this->tela('importacao/previa', ['titulo' => 'Importar para a carteira', 'importacao' => $imp, 'linhas' => $linhas, 'filtro' => $filtro,
            'base' => '/carteiras/importacao', 'estilos' => ['carteira.css']]);
    }

    public function confirmar(string $id): void
    {
        $imp = $this->importacao($id);
        try {
            [$novos, $movidos] = ImportacaoService::confirmar((int) $imp['id'], Auth::id());
            Sessao::aviso('sucesso', "Importação concluída: $novos cliente(s) novo(s) e $movidos já cadastrado(s) colocados na carteira.");
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/carteiras/' . (int) $imp['carteira_id']);
    }

    public function cancelar(string $id): void
    {
        $imp = $this->importacao($id);
        Banco::executar("UPDATE importacoes SET status = 'cancelada' WHERE id = ? AND status = 'previa'", [(int) $imp['id']]);
        Banco::executar('DELETE FROM importacao_linhas WHERE importacao_id = ?', [(int) $imp['id']]);
        Sessao::aviso('sucesso', 'Importação cancelada. Nada foi gravado.');
        redirecionar('/carteiras/' . (int) $imp['carteira_id']);
    }

    // ---------- Apoio ----------

    private function exigir(): void
    {
        if (!CS::podeUsar()) {
            http_response_code(403);
            $this->tela('erros/sem_acesso', ['titulo' => 'Sem acesso', 'aba' => 'Carteiras']);
            exit;
        }
    }

    private function carteira(string $id): array
    {
        $this->exigir();
        try {
            return CS::daPessoa((int) $id);
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/carteiras');
        }
    }

    private function importacao(string $id): array
    {
        $imp = Banco::um('SELECT * FROM importacoes WHERE id = ? AND carteira_id IS NOT NULL', [(int) $id]);
        if (!$imp) {
            redirecionar('/carteiras');
        }
        $this->carteira((string) $imp['carteira_id']); // confere que a pessoa pode mexer nessa carteira
        return $imp;
    }

    /** Executa uma ação na carteira e volta com a mensagem (ou o erro) */
    private function acao(string $id, callable $fazer, ?string $volta = null): void
    {
        $k = $this->carteira($id);
        try {
            Sessao::aviso('sucesso', (string) $fazer($k));
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            $volta = null;
        }
        redirecionar($volta ?? '/carteiras/' . (int) $k['id']);
    }

    /** Setores que a pessoa pode usar (todos para Admin/Gestor; os gerenciados para a gestora) */
    private function setores(): array
    {
        $permitidos = CS::setoresPermitidos();
        return array_values(array_filter(Setores::todos(), fn($s) => $permitidos === null || in_array((int) $s['id'], $permitidos, true)));
    }
}
