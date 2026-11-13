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
    private const ESTILOS = ['carteira.css', 'painel.css', 'producao.css'];

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
        $this->tela('producao/materia', [
            'titulo' => $m['nome'], 'm' => $m, 'movimentos' => ProducaoService::movimentosMp((int) $id), 'unidades' => ProducaoService::unidades(),
            'produtos' => \App\Core\Banco::todos('SELECT p.id, p.nome, ft.quantidade FROM ficha_tecnica ft JOIN produtos p ON p.id = ft.produto_id WHERE ft.materia_prima_id = ? ORDER BY p.nome', [(int) $id]),
            'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS,
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
            ProducaoService::entrada((int) $id, $qtd, ProducaoService::numero($this->campo('custo_unitario')), $this->campo('nota'), $this->campo('observacao'), Auth::id());
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
