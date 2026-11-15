<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Produção › Matérias-primas: lista com previsão, cadastro, entrada de compra, ajuste de inventário e histórico.
 * O estoque só muda por entrada ou ajuste (e pelo consumo das ordens de produção) — tudo no ProducaoService.
 */
class MateriaPrimaController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'inicio.css', 'producao.css', 'graficos.css', 'compras-itens.css'];

    public function index(): void
    {
        $f = ['q' => trim((string) ($_GET['q'] ?? '')), 'situacao' => in_array($_GET['situacao'] ?? '', ['abaixo', 'alerta'], true) ? $_GET['situacao'] : '',
            'inativas' => ($_GET['inativas'] ?? '') === '1'];
        $this->tela('producao/materias', [
            'titulo' => 'Matérias-primas', 'f' => $f, 'itens' => ProducaoService::materias($f), 'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function nova(): void
    {
        $this->tela('producao/materia_form', ['titulo' => 'Nova matéria-prima', 'm' => null, 'unidades' => ProducaoService::unidades(), 'estilos' => self::ESTILOS]);
    }

    public function criar(): void
    {
        try {
            $id = ProducaoService::salvarMateria(null, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Matéria-prima cadastrada.');
            redirecionar("/producao/materias/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/producao/materias/nova', [$e->getMessage()]);
        }
    }

    public function ver(string $id): void
    {
        $m = ProducaoService::materia((int) $id);
        if (!$m) {
            Sessao::aviso('erro', 'Matéria-prima não encontrada.');
            redirecionar('/producao/materias');
        }
        $aba = in_array($_GET['aba'] ?? '', ['fornecedores', 'compras', 'documentos', 'estoque'], true) ? $_GET['aba'] : 'resumo';
        $leitura = Auth::somenteAcompanha();
        $this->tela('producao/materia', [
            'titulo' => $m['nome'], 'm' => $m, 'aba' => $aba, 'movimentos' => ProducaoService::movimentosMp((int) $id), 'unidades' => ProducaoService::unidades(),
            'produtos' => \App\Core\Banco::todos('SELECT p.id, p.nome, ft.quantidade FROM ficha_tecnica ft JOIN produtos p ON p.id = ft.produto_id WHERE ft.materia_prima_id = ? ORDER BY p.nome', [(int) $id]),
            'fornecedores' => \App\Services\ItemCompraService::fornecedores((int) $id), 'documentos' => \App\Services\ItemCompraService::documentos((int) $id),
            'compras' => \App\Services\ItemCompraService::compras((int) $id), 'numeros' => \App\Services\ItemCompraService::numeros($m),
            'categorias' => \App\Services\ItemCompraService::categorias(),
            // Estoque (entrada/ajuste): Produção · Cadastro, fornecedores e documentos: Financeiro, Qualidade e Produção
            'pode' => Auth::pode('producao.gerenciar') && !$leitura, 'podeCadastro' => (Auth::pode('compras.itens') || Auth::pode('producao.gerenciar')) && !$leitura,
            'estilos' => self::ESTILOS,
        ]);
    }

    public function atualizar(string $id): void
    {
        try {
            ProducaoService::salvarMateria((int) $id, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Cadastro salvo.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/materias/$id");
    }

    public function entrada(string $id): void
    {
        try {
            $qtd = ProducaoService::numero($this->campo('quantidade'));
            $arquivo = $_FILES['arquivo_nf'] ?? null;
            $temArquivo = $arquivo && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $lote = ['lote' => $this->campo('lote'), 'fabricante' => $this->campo('fabricante'), 'fabricacao' => $this->campo('fabricacao'), 'validade' => $this->campo('validade')];
            if (ProducaoService::exigeLote((int) $id) && ($lote['lote'] === '' || $lote['validade'] === '')) {
                throw new RuntimeException('Informe o lote do fabricante e a validade da matéria-prima.');
            }
            if ($this->campo('fornecedor') !== '' || $temArquivo) {
                // Com fornecedor/NF: vira um lançamento de compra (histórico de compras, fornecedor do item e NF nos documentos)
                if ($qtd <= 0) {
                    throw new RuntimeException('A quantidade da compra precisa ser maior que zero.');
                }
                \App\Services\CompraLancamentoService::lancar(['fornecedor' => $this->campo('fornecedor') ?: 'Fornecedor não informado', 'numero_nf' => $this->campo('nota'),
                    'emissao' => date('Y-m-d'), 'observacoes' => $this->campo('observacao'), 'origem' => 'manual'],
                    [['materia_prima_id' => (int) $id, 'quantidade' => $qtd, 'valor_unitario' => ProducaoService::numero($this->campo('custo_unitario'))] + $lote],
                    $temArquivo ? \App\Services\ItemCompraService::guardar($arquivo) : null, Auth::id());
            } else {
                ProducaoService::entrada((int) $id, $qtd, ProducaoService::numero($this->campo('custo_unitario')), $this->campo('nota'), $this->campo('observacao'), Auth::id(), null, $lote);
            }
            Sessao::aviso('sucesso', 'Entrada de compra registrada: +' . ProducaoService::qtd($qtd) . '.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar($this->campo('voltar') === 'lista' ? '/producao/materias' : "/producao/materias/$id");
    }

    public function ajuste(string $id): void
    {
        try {
            $contagem = $this->campo('contagem');
            if ($contagem === '') {
                throw new RuntimeException('Informe a quantidade contada.');
            }
            $dif = ProducaoService::ajustar((int) $id, ProducaoService::numero($contagem), $this->campo('motivo'), Auth::id());
            Sessao::aviso('sucesso', $dif ? 'Inventário ajustado (' . ($dif > 0 ? '+' : '') . ProducaoService::qtd($dif) . ').' : 'A contagem bate com o estoque. Nada mudou.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/materias/$id");
    }
}
