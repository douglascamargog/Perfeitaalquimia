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
        } elseif ($this->jaInstalado()) {
            $log[] = 'Este sistema já está instalado e em uso. Para aplicar uma versão nova, entre como admin e use Administração › Atualização (os dados não são apagados). Apague o instalar_token do config.php.';
        } else {
            try {
                $instalador = new Instalador();
                $instalador->executar(true); // primeira instalação: estrutura + dados essenciais + demonstração
                $log = $instalador->log;
            } catch (\Throwable $erro) {
                error_log((string) $erro);
                $log[] = 'Erro: ' . $erro->getMessage();
            }
        }
        $this->tela('instalar/index', ['titulo' => 'Instalação', 'ativo' => $this->ativo(), 'log' => $log], 'simples');
    }

    /** Já existe usuário cadastrado? Então é um sistema em uso: a instalação completa (com demonstração) não roda de novo. */
    private function jaInstalado(): bool
    {
        try {
            return (bool) \App\Core\Banco::valor('SELECT 1 FROM usuarios LIMIT 1');
        } catch (\PDOException) {
            return false; // tabela ainda não existe: instalação nova
        }
    }

    private function ativo(): bool
    {
        return (string) config('instalar_token', '') !== '';
    }
}
