<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Produto;
use App\Services\ErpSync;

/**
 * Conteúdo › Produtos: o MESMO cadastro da Academia e do catálogo.
 * Preços e estoque vêm do ERP (somente leitura aqui). Aqui se decide o que aparece no catálogo, a chamada e a ordem.
 */
class ProdutoController extends Controller
{
    public function index(): void
    {
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $this->tela('produtos/index', ['titulo' => 'Produtos', 'busca' => $busca] + Produto::administracao($busca) + [
            'semPreco' => (int) Banco::valor('SELECT COUNT(*) FROM produtos p WHERE p.ativo = 1 AND NOT EXISTS (SELECT 1 FROM produto_precos pp WHERE pp.produto_id = p.id)'),
            'semCodigo' => (int) Banco::valor("SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND (codigo IS NULL OR codigo = '')"),
            'previa' => Sessao::get('codigos_previa'),
            'estilos' => ['carteira.css', 'pedidos.css']]);
    }

    /** Código do produto: letras, números e . - / _ (até 40) */
    public static function codigoLimpo(string $codigo): ?string
    {
        $c = mb_strtoupper(trim($codigo));
        return preg_match('#^[A-Z0-9][A-Z0-9.\-/_ ]{0,39}$#', $c) ? $c : null;
    }

    /** Planilha com Código + Produto: monta a prévia (nada é gravado ainda) */
    public function importarCodigos(): void
    {
        $arquivo = $_FILES['planilha'] ?? null;
        $ext = strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK || !in_array($ext, ['csv', 'xlsx'], true) || $arquivo['size'] > 5 * 1024 * 1024) {
            Sessao::aviso('erro', 'Envie uma planilha CSV ou XLSX (até 5 MB) com as colunas Código e Produto.');
            redirecionar('/produtos');
        }
        $linhas = \App\Services\LeitorPlanilha::ler($arquivo['tmp_name'], $ext);
        $colCodigo = $colNome = null;
        foreach (array_keys($linhas[0] ?? []) as $cab) {
            $n = \App\Services\Normalizar::semAcento(mb_strtolower($cab));
            if ($colCodigo === null && preg_match('/cod|sku|ref/', $n)) {
                $colCodigo = $cab;
            } elseif ($colNome === null && preg_match('/produto|nome|descri/', $n)) {
                $colNome = $cab;
            }
        }
        if ($colCodigo === null || $colNome === null) {
            Sessao::aviso('erro', 'Não encontrei as colunas. A primeira linha da planilha precisa ter "Código" e "Produto".');
            redirecionar('/produtos');
        }
        // Liga cada linha a um produto pelo nome (sem acento, maiúsculas/minúsculas tanto faz)
        $porNome = [];
        foreach (Banco::todos('SELECT id, nome, codigo FROM produtos') as $p) {
            $porNome[\App\Services\Normalizar::semAcento(mb_strtolower(trim($p['nome'])))] = $p;
        }
        $previa = ['ok' => [], 'sem_produto' => [], 'invalidos' => []];
        $usados = [];
        foreach ($linhas as $l) {
            $codigo = self::codigoLimpo((string) ($l[$colCodigo] ?? ''));
            $nome = trim((string) ($l[$colNome] ?? ''));
            $produto = $porNome[\App\Services\Normalizar::semAcento(mb_strtolower($nome))] ?? null;
            if (!$codigo || isset($usados[$codigo])) {
                $previa['invalidos'][] = ['codigo' => (string) ($l[$colCodigo] ?? ''), 'nome' => $nome, 'motivo' => $codigo ? 'código repetido na planilha' : 'código vazio ou inválido'];
            } elseif (!$produto) {
                $previa['sem_produto'][] = ['codigo' => $codigo, 'nome' => $nome];
            } else {
                $usados[$codigo] = true;
                $previa['ok'][] = ['id' => (int) $produto['id'], 'nome' => $produto['nome'], 'codigo' => $codigo, 'antes' => $produto['codigo']];
            }
        }
        Sessao::set('codigos_previa', $previa);
        redirecionar('/produtos#importar');
    }

    public function confirmarCodigos(): void
    {
        $previa = Sessao::get('codigos_previa');
        Sessao::remover('codigos_previa');
        if (!$previa || ($_POST['acao'] ?? '') !== 'aplicar') {
            redirecionar('/produtos');
        }
        $n = 0;
        Banco::transacao(function () use ($previa, &$n) {
            foreach ($previa['ok'] as $l) {
                // Libera o código se outro produto estava com ele (a planilha manda)
                Banco::executar('UPDATE produtos SET codigo = NULL WHERE codigo = ? AND id <> ?', [$l['codigo'], $l['id']]);
                $n += Banco::executar('UPDATE produtos SET codigo = ? WHERE id = ?', [$l['codigo'], $l['id']]);
            }
            Auditoria::registrar('produto', null, 'importar_codigos', null, ['quantidade' => count($previa['ok'])]);
        });
        Sessao::aviso('sucesso', "Códigos importados: $n produto(s) atualizado(s).");
        redirecionar('/produtos');
    }

    public function salvar(string $id): void
    {
        $antes = Banco::um('SELECT id, codigo, chamada, ordem, visivel_catalogo FROM produtos WHERE id = ?', [(int) $id]);
        if ($antes) {
            $codigo = $this->campo('codigo') === '' ? null : self::codigoLimpo($this->campo('codigo'));
            $volta = '/produtos' . ($this->campo('q') !== '' ? '?q=' . rawurlencode($this->campo('q')) : '') . '#produto-' . (int) $id;
            if ($this->campo('codigo') !== '' && !$codigo) {
                Sessao::aviso('erro', 'Código inválido: use letras, números e . - / _ (até 40 caracteres).');
                redirecionar($volta);
            }
            if ($codigo && Banco::valor('SELECT nome FROM produtos WHERE codigo = ? AND id <> ?', [$codigo, (int) $id])) {
                Sessao::aviso('erro', "O código $codigo já é de outro produto: " . Banco::valor('SELECT nome FROM produtos WHERE codigo = ?', [$codigo]) . '.');
                redirecionar($volta);
            }
            $dados = ['codigo' => $codigo, 'visivel_catalogo' => isset($_POST['visivel_catalogo']) ? 1 : 0, 'ordem' => (int) $this->campo('ordem', (string) $antes['ordem']),
                'chamada' => mb_substr($this->campo('chamada'), 0, 255) ?: null];
            Banco::atualizar('produtos', (int) $id, $dados);
            Auditoria::registrar('produto', (int) $id, 'editar', $antes, $dados);
            Sessao::aviso('sucesso', 'Produto atualizado.');
        }
        redirecionar('/produtos' . ($this->campo('q') !== '' ? '?q=' . rawurlencode($this->campo('q')) : '') . '#produto-' . (int) $id);
    }

    public function sincronizar(): void
    {
        $precos = ErpSync::precos();
        $estoque = ErpSync::estoque();
        Sessao::aviso('sucesso', "Atualizado do ERP: $precos preço(s) e $estoque estoque(s).");
        redirecionar('/produtos');
    }
}
