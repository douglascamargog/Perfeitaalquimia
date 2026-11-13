<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Funcao;

/**
 * Funções comerciais: criar, renomear, ativar/desativar.
 * O slug (identificador usado pelas regras) não muda depois de criado.
 */
class FuncaoController extends Controller
{
    public function index(): void
    {
        $this->tela('funcoes/index', ['titulo' => 'Funções', 'funcoes' => Funcao::listar()]);
    }

    public function criar(): void
    {
        $nome = $this->campo('nome');
        if ($nome === '') {
            $this->voltarComErros('/funcoes', ['Informe o nome da função.']);
        }
        $dados = [
            'nome'      => $nome,
            'slug'      => Funcao::gerarSlug($nome),
            'descricao' => $this->campo('descricao') ?: null,
            'ordem'     => (int) Banco::valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM funcoes'),
            'areas_foco' => 'comercial',
        ];
        $id = Banco::inserir('funcoes', $dados);
        Auditoria::registrar('funcao', $id, 'criar', null, $dados);
        Sessao::aviso('sucesso', 'Função criada.');
        redirecionar('/funcoes');
    }

    public function atualizar(string $id): void
    {
        $antes = Funcao::buscar((int) $id);
        if (!$antes) {
            redirecionar('/funcoes');
        }
        $dados = [
            'nome'      => $this->campo('nome') ?: $antes['nome'],
            'descricao' => $this->campo('descricao') ?: null,
            'ativa'     => isset($_POST['ativa']) ? 1 : 0,
            // Áreas que a função acompanha (Início, One, sino e números do menu)
            'areas_foco' => implode(',', \App\Services\Foco::lista(implode(',', (array) ($_POST['areas'] ?? [])))),
        ];
        Banco::atualizar('funcoes', (int) $id, $dados);
        Auditoria::registrar('funcao', (int) $id, 'editar', $antes, $dados);
        Sessao::aviso('sucesso', 'Função atualizada.');
        redirecionar('/funcoes');
    }
}
