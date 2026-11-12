<?php
namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Conexão com o banco de dados (MySQL/MariaDB) usando PDO.
 *
 * REGRA: sempre passar os valores separados do SQL (prepared statements),
 * nunca colar valores dentro do texto do SQL. Exemplo:
 *     Banco::um('SELECT * FROM usuarios WHERE email = ?', [$email]);
 */
class Banco
{
    private static ?PDO $pdo = null;

    /** Abre a conexão (uma só por requisição) */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('banco');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['porta'] ?? 3306, $c['nome']);
            self::$pdo = new PDO($dsn, $c['usuario'], $c['senha'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erro de SQL vira exceção
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // resultados como array ['coluna' => valor]
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    /** Executa um SQL com parâmetros e devolve o "statement" */
    public static function consulta(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Todas as linhas do resultado */
    public static function todos(string $sql, array $params = []): array
    {
        return self::consulta($sql, $params)->fetchAll();
    }

    /** Só a primeira linha (ou null) */
    public static function um(string $sql, array $params = []): ?array
    {
        $linha = self::consulta($sql, $params)->fetch();
        return $linha === false ? null : $linha;
    }

    /** Só o primeiro valor da primeira linha. Ex.: SELECT COUNT(*) ... */
    public static function valor(string $sql, array $params = []): mixed
    {
        $v = self::consulta($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** INSERT, UPDATE ou DELETE. Devolve quantas linhas foram afetadas */
    public static function executar(string $sql, array $params = []): int
    {
        return self::consulta($sql, $params)->rowCount();
    }

    /**
     * Insere uma linha a partir de um array ['coluna' => valor] e devolve o id criado.
     * Os nomes das colunas vêm sempre do nosso código, nunca do usuário.
     */
    public static function inserir(string $tabela, array $dados): int
    {
        $colunas = array_keys($dados);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $tabela,
            implode('`, `', $colunas),
            implode(', ', array_fill(0, count($colunas), '?'))
        );
        self::consulta($sql, array_values($dados));
        return (int) self::pdo()->lastInsertId();
    }

    /** Atualiza a linha de id informado com os dados ['coluna' => valor] */
    public static function atualizar(string $tabela, int $id, array $dados): int
    {
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($dados)));
        return self::executar("UPDATE `$tabela` SET $sets WHERE id = ?", [...array_values($dados), $id]);
    }

    /** Executa várias operações juntas: ou tudo dá certo, ou nada é gravado */
    public static function transacao(callable $funcao): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultado = $funcao();
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $erro) {
            $pdo->rollBack();
            throw $erro;
        }
    }
}
