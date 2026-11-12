<?php
namespace App\Controllers;

use App\Core\Instalador;

/**
 * Instalação pelo navegador (para hospedagem sem terminal).
 * Só funciona se 'instalar_token' estiver preenchido no config.php e a pessoa digitar esse token.
 * Depois de instalar, apague o token do config.php para desligar esta tela.
 */
class InstalarController extends Controller
{
    public function index(): void
    {
        $this->tela('instalar/index', ['titulo' => 'Instalação', 'ativo' => $this->ativo(), 'log' => []], 'simples');
    }

    public function executar(): void
    {
        $log = [];
        if (!$this->ativo() || !hash_equals((string) config('instalar_token'), (string) ($_POST['token'] ?? ''))) {
            $log[] = 'Token inválido ou instalação desligada.';
        } else {
            try {
                $instalador = new Instalador();
                $instalador->executar(isset($_POST['seeds']));
                $log = $instalador->log;
            } catch (\Throwable $erro) {
                error_log((string) $erro);
                $log[] = 'Erro: ' . $erro->getMessage();
            }
        }
        $this->tela('instalar/index', ['titulo' => 'Instalação', 'ativo' => $this->ativo(), 'log' => $log], 'simples');
    }

    private function ativo(): bool
    {
        return (string) config('instalar_token', '') !== '';
    }
}
