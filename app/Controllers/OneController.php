<?php
namespace App\Controllers;

use App\Core\Visao;
use App\Services\OneAssistente;

/**
 * One, o ajudante: devolve as falas para o painel flutuante (carregado só quando a pessoa abre).
 */
class OneController extends Controller
{
    public function falas(): void
    {
        $pagina = '/' . trim((string) ($_GET['pagina'] ?? '/'), '/');
        echo Visao::renderizar('one/_falas', ['saudacao' => OneAssistente::saudacao(), 'falas' => OneAssistente::falas($pagina)]);
    }
}
