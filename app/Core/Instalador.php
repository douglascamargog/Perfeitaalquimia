<?php
namespace App\Core;

/**
 * Cria e atualiza o banco de dados.
 *
 * - Migrations: arquivos .sql numerados em database/migrations (001_..., 002_...).
 *   Cada arquivo roda UMA vez; o controle fica na tabela `migracoes`.
 * - Seeds: arquivos .php em database/seeds com os dados de demonstração. Também rodam uma vez.
 *
 * Pode ser usado pelo terminal (php database/migrar.php) ou pela tela /instalar.
 */
class Instalador
{
    /** Mensagens do que foi feito (mostradas na tela ou no terminal) */
    public array $log = [];

    public function executar(bool $comSeeds = true): void
    {
        Banco::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migracoes (
                arquivo    VARCHAR(160) NOT NULL PRIMARY KEY,
                aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->migrar();
        if ($comSeeds) {
            $this->semear();
        }
        if (!$this->log) {
            $this->log[] = 'Nada a fazer: o banco já está atualizado.';
        }
    }

    private function jaAplicado(string $nome): bool
    {
        return (bool) Banco::valor('SELECT 1 FROM migracoes WHERE arquivo = ?', [$nome]);
    }

    private function migrar(): void
    {
        foreach ($this->arquivos('migrations', 'sql') as $arquivo) {
            $nome = basename($arquivo);
            if ($this->jaAplicado($nome)) {
                continue;
            }
            // Separa os comandos por ";" no fim da linha e executa um por um
            $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($arquivo));
            foreach (preg_split('/;\s*$/m', $sql) as $comando) {
                if (trim($comando) !== '') {
                    Banco::pdo()->exec($comando);
                }
            }
            Banco::inserir('migracoes', ['arquivo' => $nome]);
            $this->log[] = "Migration aplicada: $nome";
        }
    }

    private function semear(): void
    {
        foreach ($this->arquivos('seeds', 'php') as $arquivo) {
            $nome = 'seed:' . basename($arquivo);
            if ($this->jaAplicado($nome)) {
                continue;
            }
            Banco::transacao(function () use ($arquivo) {
                (static function (string $arquivo) {
                    require $arquivo; // o seed usa App\Core\Banco para inserir os dados
                })($arquivo);
            });
            Banco::inserir('migracoes', ['arquivo' => $nome]);
            $this->log[] = 'Dados de demonstração carregados: ' . basename($arquivo);
        }
    }

    /** Lista os arquivos da pasta em ordem (001, 002, ...) */
    private function arquivos(string $pasta, string $extensao): array
    {
        $lista = glob(RAIZ . "/database/$pasta/*.$extensao") ?: [];
        sort($lista);
        return $lista;
    }
}
