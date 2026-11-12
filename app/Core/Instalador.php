<?php
namespace App\Core;

/**
 * Cria e atualiza o banco de dados — SEM apagar os dados existentes.
 *
 * - Migrations: arquivos .sql numerados em database/migrations (001_..., 002_...).
 *   Cada arquivo roda UMA vez; o controle fica na tabela `migracoes`.
 * - Seeds: arquivos .php em database/seeds. Também rodam uma vez.
 *     · seeds com "_demo" no nome (ex.: 007_vendas_demo.php) são DADOS DE DEMONSTRAÇÃO:
 *       só rodam na instalação nova, nunca numa atualização.
 *     · os demais são dados ESSENCIAIS (configurações novas, permissões, listas) e rodam também nas atualizações.
 *
 * Usado por: terminal (php database/migrar.php), tela /instalar (primeira instalação)
 * e tela Administração › Atualização do sistema (versões novas).
 */
class Instalador
{
    /** Mensagens do que foi feito (mostradas na tela ou no terminal) */
    public array $log = [];

    /**
     * Aplica o que falta.
     * $incluirDemo = true na instalação nova; false nas atualizações (não mistura dados fictícios com os reais).
     */
    public function executar(bool $incluirDemo = true): void
    {
        $this->criarControle();
        $this->migrar();
        $this->semear($incluirDemo);
        if (!$this->log) {
            $this->log[] = 'Nada a fazer: o banco já está atualizado.';
        }
    }

    /** O que ainda não foi aplicado: ['migrations' => [...], 'seeds' => [...]] */
    public function pendentes(bool $incluirDemo = false): array
    {
        $this->criarControle();
        $faltam = fn(array $arquivos, string $prefixo) => array_values(array_filter(
            array_map('basename', $arquivos),
            fn($nome) => !$this->jaAplicado($prefixo . $nome)
        ));
        return [
            'migrations' => $faltam($this->arquivos('migrations', 'sql'), ''),
            'seeds'      => array_values(array_filter($faltam($this->arquivos('seeds', 'php'), 'seed:'), fn($n) => $incluirDemo || !self::ehDemo($n))),
        ];
    }

    /** Lista do que já foi aplicado, do mais recente para o mais antigo */
    public function historico(): array
    {
        $this->criarControle();
        return Banco::todos('SELECT * FROM migracoes ORDER BY aplicada_em DESC, arquivo DESC');
    }

    public static function ehDemo(string $arquivo): bool
    {
        return str_contains(basename($arquivo), '_demo');
    }

    private function criarControle(): void
    {
        Banco::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migracoes (
                arquivo    VARCHAR(160) NOT NULL PRIMARY KEY,
                aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
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
            $this->log[] = "Banco atualizado: $nome";
        }
    }

    private function semear(bool $incluirDemo): void
    {
        foreach ($this->arquivos('seeds', 'php') as $arquivo) {
            $nome = 'seed:' . basename($arquivo);
            if ($this->jaAplicado($nome) || (!$incluirDemo && self::ehDemo($arquivo))) {
                continue;
            }
            // O seed pode devolver uma mensagem (ex.: aviso de arquivo de referência ausente) para o relatório
            $mensagem = Banco::transacao(function () use ($arquivo) {
                return (static function (string $arquivo) {
                    return require $arquivo; // o seed usa App\Core\Banco para inserir os dados
                })($arquivo);
            });
            Banco::inserir('migracoes', ['arquivo' => $nome]);
            $this->log[] = 'Dados carregados: ' . basename($arquivo) . (is_string($mensagem) ? " — $mensagem" : '');
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
