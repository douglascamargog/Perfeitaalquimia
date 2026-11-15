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
        echo Visao::renderizar('one/_falas', ['saudacao' => OneAssistente::saudacao(), 'falas' => OneAssistente::falas($pagina),
            'ajuda' => \App\Services\OneTela::ajuda($pagina), 'tutorial' => \App\Services\TutorialService::daRota($pagina)]);
    }

    /** O One na tela atual (JSON): balão importante (se a pessoa deixa o One puxar conversa) e a ajuda da tela */
    public function naTela(): void
    {
        $pagina = '/' . trim((string) parse_url((string) ($_GET['pagina'] ?? '/'), PHP_URL_PATH), '/');
        parse_str((string) parse_url((string) ($_GET['pagina'] ?? ''), PHP_URL_QUERY), $query);
        $usuario = \App\Core\Auth::usuario();
        $balao = (int) ($usuario['one_balao'] ?? 1) ? \App\Services\OneTela::balao($pagina, $query) : null;
        $tutorial = \App\Services\TutorialService::daTela($pagina);
        $this->json(['balao' => $tutorial['automatico'] ?? false ? null : $balao, 'ajuda' => \App\Services\OneTela::ajuda($pagina), 'tutorial' => $tutorial]);
    }

    /** Pergunte ao One (JSON). A resposta vem em markdown simples; a tela converte só negrito, links internos e listas. */
    public function perguntar(): void
    {
        if (!empty($_POST['esquecer'])) {
            \App\Services\OnePergunta::esquecer();
            $this->json(['ok' => true]);
            return;
        }
        $this->json(['ok' => true] + \App\Services\OnePergunta::perguntar((string) ($_POST['pergunta'] ?? '')));
    }
}
