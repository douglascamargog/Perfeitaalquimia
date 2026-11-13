<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Services\Versoes;

/** Novidades da versão: histórico de todas as versões e o "Entendi" da janela de novidades. */
class NovidadesController extends Controller
{
    public function index(): void
    {
        Versoes::marcarVista((int) Auth::usuario()['id']); // quem abriu o histórico já viu as novidades
        $this->tela('novidades/index', ['titulo' => 'Novidades', 'versoes' => Versoes::lista(), 'atual' => Versoes::nome()]);
    }

    /** "Entendi": não mostra mais a janela desta versão e volta para a mesma página */
    public function vista(): void
    {
        Versoes::marcarVista((int) Auth::usuario()['id']);
        $volta = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        $base = url_base();
        $caminho = ($base !== '' && str_starts_with($volta, $base)) ? substr($volta, strlen($base)) : $volta;
        $consulta = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY);
        redirecionar(preg_match('#^/(?!/)#', $caminho) ? $caminho . ($consulta !== '' ? '?' . $consulta : '') : '/');
    }
}
