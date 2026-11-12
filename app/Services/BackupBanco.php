<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Cópia de segurança do banco de dados em um arquivo .sql.gz (compactado).
 *
 * Para restaurar: cPanel › phpMyAdmin › escolha o banco › Importar › envie o arquivo.
 * O arquivo contém TODOS os dados (clientes, usuários...) — guarde com cuidado (LGPD).
 */
class BackupBanco
{
    private const LOTE = 500; // linhas por comando INSERT

    /** Gera o backup em um arquivo temporário e devolve o caminho */
    public static function gerar(): string
    {
        $arquivo = RAIZ . '/storage/cache/backup-' . bin2hex(random_bytes(6)) . '.sql.gz';
        $gz = gzopen($arquivo, 'wb6');
        $pdo = Banco::pdo();

        gzwrite($gz, "-- Backup do One (" . config('empresa_nome', '') . ") gerado em " . date('d/m/Y H:i') . "\n");
        gzwrite($gz, "-- Restaurar: phpMyAdmin › Importar este arquivo no banco do sistema.\n\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM) as [$tabela]) {
            $criar = $pdo->query("SHOW CREATE TABLE `$tabela`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "-- Tabela $tabela\nDROP TABLE IF EXISTS `$tabela`;\n$criar;\n");

            $consulta = $pdo->query("SELECT * FROM `$tabela`", \PDO::FETCH_NUM);
            $linhas = [];
            while ($linha = $consulta->fetch()) {
                $linhas[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $linha)) . ')';
                if (count($linhas) >= self::LOTE) {
                    gzwrite($gz, "INSERT INTO `$tabela` VALUES\n" . implode(",\n", $linhas) . ";\n");
                    $linhas = [];
                }
            }
            if ($linhas) {
                gzwrite($gz, "INSERT INTO `$tabela` VALUES\n" . implode(",\n", $linhas) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);
        return $arquivo;
    }
}
