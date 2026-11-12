<?php
namespace App\Controllers;

use App\Core\Sessao;
use App\Core\Visao;

/**
 * Base de todos os controllers: atalhos para mostrar telas, responder JSON e voltar com erro.
 */
abstract class Controller
{
    protected function tela(string $pagina, array $dados = [], string $layout = 'base'): void
    {
        Visao::mostrar($pagina, $dados, $layout);
    }

    protected function json(mixed $dados, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    }

    /** Volta para o formulário mostrando os erros e mantendo o que foi digitado */
    protected function voltarComErros(string $caminho, array $erros): never
    {
        foreach ($erros as $erro) {
            Sessao::aviso('erro', $erro);
        }
        Sessao::guardarFormulario($_POST);
        redirecionar($caminho);
    }

    /** Lê um campo do formulário já sem espaços nas pontas */
    protected function campo(string $nome, string $padrao = ''): string
    {
        $valor = $_POST[$nome] ?? $padrao;
        return is_string($valor) ? trim($valor) : $padrao;
    }
}
