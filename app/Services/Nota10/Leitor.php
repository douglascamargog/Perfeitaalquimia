<?php
namespace App\Services\Nota10;

use App\Core\Banco;

/**
 * Lê o backup do Nota10 (.sql do MySQL, gerado pelo mysqldump ou pelo HeidiSQL) para a ÁREA SEPARADA:
 * tabelas com o prefixo n10_ (ex.: clientes → n10_clientes). Os dados do One não são tocados.
 *
 * A leitura é feita em partes (cada chamada trabalha alguns segundos e guarda até onde chegou),
 * para caber no limite de tempo da hospedagem compartilhada. A tela chama de novo até terminar.
 *
 * TODAS as tabelas do backup são guardadas (decisão: nada do Nota10 fica de fora). A lista TABELAS são as que a conferência e a
 * importação usam; as outras (logs, configuração, contas bancárias, fiscais...) ficam só na área n10_ para consulta.
 */
class Leitor
{
    /** Pasta dos backups (fora da web e fora do que o "Zerar dados" apaga) */
    public const PASTA = RAIZ . '/storage/nota10';

    /** Tabelas do Nota10 que o One aproveita */
    public const TABELAS = ['clientes', 'contatos', 'cidades', 'usuarios', 'grupos', 'apoio', 'notas', 'itensnota', 'nfref', 'eventosnfe',
        'produtos', 'tabelaprecos', 'precos', 'formapgto', 'contaspagrec', 'followup', 'estoques', 'logestoque', 'producao', 'composicao',
        'compras', 'itenscompra', 'itemfornecedor', 'equivalencia', 'romaneios', 'mensalidades', 'emails', 'listas', 'inventario'];

    public const PREFIXO = 'n10_';

    /** Segundos de trabalho por chamada */
    private const TEMPO = 8.0;

    /** Prepara a pasta (protegida contra acesso pela web) */
    public static function pasta(): string
    {
        if (!is_dir(self::PASTA)) {
            mkdir(self::PASTA, 0750, true);
        }
        if (!is_file(self::PASTA . '/.htaccess')) {
            file_put_contents(self::PASTA . '/.htaccess', "Require all denied\n");
        }
        return self::PASTA;
    }

    /**
     * Recebe o arquivo (.sql, .zip com um .sql dentro, ou .sql.gz) e deixa um .sql pronto na pasta.
     * Devolve o id da importação registrada.
     */
    public static function registrar(string $caminho, string $nomeOriginal, int $usuarioId): int
    {
        $destino = self::pasta() . '/nota10-' . date('Y-m-d-His') . '.sql';
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        @set_time_limit(600);
        if ($ext === 'zip') {
            self::extrairZip($caminho, $destino);
        } elseif ($ext === 'gz') {
            self::extrairGz($caminho, $destino);
        } elseif (in_array($ext, ['sql', 'txt'], true)) { // o mysqldump às vezes sai como .txt
            if (realpath($caminho) !== realpath($destino) && !@rename($caminho, $destino) && !copy($caminho, $destino)) {
                throw new \RuntimeException('Não foi possível guardar o arquivo no servidor.');
            }
        } else {
            throw new \RuntimeException('Envie o backup em .sql, .txt, .zip ou .sql.gz.');
        }
        self::converterUtf16($destino); // PowerShell do Windows grava "mysqldump > arquivo" em UTF-16
        self::consertarAcentos($destino); // ...e embaralha os acentos pela página de código do console
        if (!self::pareceBackup($destino)) {
            throw new \RuntimeException('Este arquivo não parece um backup do banco: não tem "CREATE TABLE" nem "INSERT INTO" no começo. '
                . 'Gere de novo pelo HeidiSQL (Exportar banco de dados como SQL) ou pelo mysqldump.');
        }
        return Banco::inserir('nota10_importacoes', ['arquivo' => basename($destino), 'nome_original' => mb_substr($nomeOriginal, 0, 255),
            'tamanho' => filesize($destino), 'criado_por' => $usuarioId]);
    }

    /** O começo do arquivo tem cara de backup SQL (CREATE TABLE / INSERT INTO)? */
    public static function pareceBackup(string $arquivo): bool
    {
        $fh = @fopen($arquivo, 'rb');
        if (!$fh) {
            return false;
        }
        $inicio = (string) fread($fh, 4 << 20); // primeiros 4 MB
        fclose($fh);
        return (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO)\b/i', $inicio);
    }

    /**
     * Se o arquivo está em UTF-16 (PowerShell do Windows), converte para UTF-8 no mesmo lugar, em partes (arquivo grande).
     * Devolve true se converteu.
     */
    public static function converterUtf16(string $arquivo): bool
    {
        $fh = fopen($arquivo, 'rb');
        $amostra = (string) fread($fh, 4096);
        fclose($fh);
        $bom = substr($amostra, 0, 2);
        $le = $bom === "\xFF\xFE" || (strlen($amostra) > 100 && substr_count($amostra, "\0") > strlen($amostra) / 3 && $amostra[1] === "\0");
        $be = $bom === "\xFE\xFF";
        if (!$le && !$be) {
            return false;
        }
        @set_time_limit(900);
        $origem = $be ? 'UTF-16BE' : 'UTF-16LE';
        $saida = $arquivo . '.utf8';
        $in = fopen($arquivo, 'rb');
        $out = fopen($saida, 'wb');
        if (in_array($bom, ["\xFF\xFE", "\xFE\xFF"], true)) {
            fread($in, 2); // pula o marcador
        }
        $resto = '';
        while (!feof($in)) {
            $bloco = $resto . fread($in, 8 << 20);
            $resto = '';
            if (strlen($bloco) % 2) { // não corta um caractere no meio
                $resto = substr($bloco, -1);
                $bloco = substr($bloco, 0, -1);
            }
            $ultimo = $be ? ord($bloco[strlen($bloco) - 2] ?? "\0") : ord($bloco[strlen($bloco) - 1] ?? "\0");
            if (strlen($bloco) >= 2 && $ultimo >= 0xD8 && $ultimo <= 0xDB) { // metade de um emoji: espera a outra metade
                $resto = substr($bloco, -2) . $resto;
                $bloco = substr($bloco, 0, -2);
            }
            fwrite($out, (string) mb_convert_encoding($bloco, 'UTF-8', $origem));
        }
        fclose($in);
        fclose($out);
        rename($saida, $arquivo);
        return true;
    }

    /**
     * O PowerShell lê a saída do mysqldump com a página de código do console e embaralha os acentos
     * ("Farmácia" vira "Farm├ícia" ou "FarmÃ¡cia"). Se o arquivo está assim, desfaz a troca e volta aos bytes originais.
     */
    public static function consertarAcentos(string $arquivo): bool
    {
        $fh = fopen($arquivo, 'rb');
        $amostra = (string) fread($fh, 8 << 20);
        fclose($fh);
        $cp850 = preg_match_all('/├[\x{00A0}-\x{00FF}\x{2500}-\x{25FF}]|┬[\x{00A0}-\x{00FF}\x{2500}-\x{25FF}]/u', $amostra);
        $cp1252 = preg_match_all('/Ã[\x{00A0}-\x{00BF}\x{0080}-\x{009F}\x{2013}-\x{203A}]|Â[\x{00A0}-\x{00BF}]/u', $amostra);
        $de = $cp850 >= 3 && $cp850 >= $cp1252 ? 'CP850' : ($cp1252 >= 3 ? 'Windows-1252' : null);
        if (!$de) {
            return false;
        }
        $saida = $arquivo . '.acentos';
        $in = fopen($arquivo, 'rb');
        $out = fopen($saida, 'wb');
        while (($linha = fgets($in)) !== false) {
            fwrite($out, (string) mb_convert_encoding($linha, $de, 'UTF-8')); // volta para os bytes que o mysqldump escreveu
        }
        fclose($in);
        fclose($out);
        rename($saida, $arquivo);
        return true;
    }

    /** Arquivos colocados direto na pasta (FTP / Gerenciador de arquivos do cPanel) que ainda não foram registrados */
    public static function arquivosSoltos(): array
    {
        $registrados = array_column(Banco::todos('SELECT arquivo, nome_original FROM nota10_importacoes'), 'nome_original', 'arquivo');
        $soltos = [];
        foreach (glob(self::pasta() . '/*.{sql,txt,zip,gz}', GLOB_BRACE) ?: [] as $f) {
            $nome = basename($f);
            if (!isset($registrados[$nome]) && !in_array($nome, $registrados, true)) {
                $soltos[] = ['nome' => $nome, 'tamanho' => filesize($f), 'data' => filemtime($f)];
            }
        }
        return $soltos;
    }

    private static function extrairZip(string $zip, string $destino): void
    {
        $z = new \ZipArchive();
        if ($z->open($zip) !== true) {
            throw new \RuntimeException('O arquivo .zip não abriu. Confira se ele não está corrompido.');
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            $nome = $z->getNameIndex($i);
            if (preg_match('/\.(sql|txt)$/i', $nome) && !str_contains($nome, '__MACOSX')) {
                $in = $z->getStream($nome);
                $out = fopen($destino, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $z->close();
                return;
            }
        }
        $z->close();
        throw new \RuntimeException('Não há arquivo .sql (ou .txt) dentro do .zip.');
    }

    private static function extrairGz(string $gz, string $destino): void
    {
        $in = gzopen($gz, 'rb');
        $out = fopen($destino, 'wb');
        if (!$in || !$out) {
            throw new \RuntimeException('Não foi possível abrir o arquivo .gz.');
        }
        while (!gzeof($in)) {
            fwrite($out, gzread($in, 1 << 20));
        }
        gzclose($in);
        fclose($out);
    }

    /** Apaga a área separada (todas as tabelas n10_) */
    public static function limparArea(): void
    {
        foreach (self::tabelasDaArea() as $t) {
            Banco::pdo()->exec("DROP TABLE IF EXISTS `$t`");
        }
    }

    /** Tabelas n10_ que existem hoje */
    public static function tabelasDaArea(): array
    {
        return array_map(fn($l) => $l[0], Banco::pdo()->query("SHOW TABLES LIKE 'n10\\_%'")->fetchAll(\PDO::FETCH_NUM));
    }

    /**
     * Lê mais uma parte do backup. Devolve a importação atualizada (posicao, situacao, comandos...).
     * Na primeira parte, apaga a área separada anterior.
     */
    public static function lerParte(int $id, float $tempo = self::TEMPO): array
    {
        $imp = Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [$id]);
        if (!$imp || $imp['situacao'] === 'lido') {
            return $imp ?: [];
        }
        $arquivo = self::PASTA . '/' . basename($imp['arquivo']);
        $fh = @fopen($arquivo, 'rb');
        if (!$fh) {
            Banco::atualizar('nota10_importacoes', $id, ['situacao' => 'erro', 'erro' => 'O arquivo do backup não está mais no servidor.']);
            return Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [$id]);
        }
        @set_time_limit(120);
        $pdo = Banco::pdo();
        if ((int) $imp['posicao'] === 0) {
            self::limparArea();
            $imp['comandos'] = $imp['ignorados'] = 0;
            // o backup pode ter um "marcador de UTF-8" no começo
            if (fread($fh, 3) !== "\xEF\xBB\xBF") {
                rewind($fh);
            }
        } else {
            fseek($fh, (int) $imp['posicao']);
        }
        $pdo->exec("SET SESSION sql_mode = '', FOREIGN_KEY_CHECKS = 0, UNIQUE_CHECKS = 0");
        $charset = $imp['charset'] ?: 'utf8mb4';
        $pdo->exec('SET NAMES ' . self::charsetSeguro($charset));

        $pdo->exec('START TRANSACTION'); // grava a parte inteira de uma vez (bem mais rápido com milhões de linhas)
        $inicio = microtime(true);
        $comando = '';
        $aspas = false;          // dentro de um texto '...' que continua na próxima linha?
        $delimitador = ';';
        $inicioComando = ftell($fh);
        $feitos = (int) $imp['comandos'];
        $pulados = (int) $imp['ignorados'];
        $erro = null;

        while (($linha = fgets($fh)) !== false) {
            if ($comando === '' && !$aspas) {
                $t = ltrim($linha);
                if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) {
                    $inicioComando = ftell($fh);
                    continue;
                }
                if (preg_match('/^DELIMITER\s+(\S+)/i', $t, $m)) {
                    $delimitador = $m[1];
                    $inicioComando = ftell($fh);
                    continue;
                }
            }
            $comando .= $linha;
            // conta as aspas simples, sem as escapadas com barra (\' e \\)
            if (substr_count(preg_replace('/\\\\./s', '', $linha), "'") % 2 === 1) {
                $aspas = !$aspas;
            }
            if ($aspas || !str_ends_with(rtrim($linha), $delimitador)) {
                continue;
            }
            // comando completo
            $sql = substr(rtrim($comando), 0, -strlen($delimitador));
            $comando = '';
            if ($delimitador === ';') {
                try {
                    $r = self::executar($sql, $charset);
                    $r ? $feitos++ : $pulados++;
                } catch (\PDOException $e) {
                    $erro = mb_substr($e->getMessage(), 0, 300) . ' — no comando: ' . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 160);
                    break;
                }
            } else {
                $pulados++; // gatilhos e rotinas (DELIMITER) não são usados
            }
            $inicioComando = ftell($fh);
            if (microtime(true) - $inicio > $tempo) {
                break;
            }
        }
        if ($linha === false && !$erro && trim($comando) !== '') {
            // último comando sem quebra de linha no fim do arquivo
            try {
                $sql = rtrim(trim($comando), $delimitador);
                self::executar($sql, $charset) ? $feitos++ : $pulados++;
                $comando = '';
            } catch (\PDOException $e) {
                $erro = mb_substr($e->getMessage(), 0, 300);
            }
        }
        $fim = $linha === false && !$erro;
        fclose($fh);
        $pdo->exec('COMMIT'); // guarda o que foi lido até aqui (com erro, a próxima tentativa recomeça no comando que falhou)
        $pdo->exec('SET NAMES utf8mb4');
        $dados = ['posicao' => $erro ? $inicioComando : ($fim ? (int) $imp['tamanho'] : $inicioComando), 'comandos' => $feitos, 'ignorados' => $pulados,
            'charset' => $charset, 'situacao' => $erro ? 'erro' : ($fim ? 'lido' : 'lendo'), 'erro' => $erro];
        if ($fim) {
            $dados['lido_em'] = date('Y-m-d H:i:s');
            self::indexar();
        }
        Banco::atualizar('nota10_importacoes', $id, $dados);
        return Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [$id]);
    }

    /** Executa um comando do backup se for de uma tabela útil (devolve false quando é pulado) */
    private static function executar(string $sql, string &$charset): bool
    {
        $t = ltrim($sql);
        // /*!40101 SET NAMES utf8mb4 */ : guarda a codificação do arquivo
        if (preg_match('/^(?:\/\*!\d+\s+)?SET\s+NAMES\s+\'?(\w+)/i', $t, $m)) {
            $charset = strtolower($m[1]);
            Banco::pdo()->exec('SET NAMES ' . self::charsetSeguro($charset));
            return false;
        }
        $re = '/^(\s*(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+)(?:`?\w+`?\.)?`?(\w+)`?/i';
        if (!preg_match($re, $t, $m) || str_starts_with(strtolower($m[2]), 'n10_')) {
            return false; // DROP, LOCK, SET, USE, CREATE DATABASE e views
        }
        $tabela = self::PREFIXO . strtolower($m[2]);
        $t = preg_replace($re, '$1`' . $tabela . '`', $t, 1);
        if (stripos($m[1], 'CREATE') !== false) {
            // collation do MySQL 8 que o MariaDB não conhece, e chaves estrangeiras (a área separada não precisa)
            $t = preg_replace('/utf8mb4_0900_\w+/i', 'utf8mb4_unicode_ci', $t);
            $t = preg_replace('/,\s*CONSTRAINT\s+`?\w+`?\s+FOREIGN KEY[^,)]*\([^)]*\)\s*REFERENCES[^,)]*\([^)]*\)(\s+ON\s+(DELETE|UPDATE)\s+\w+(\s+\w+)?)*/i', '', $t);
            Banco::pdo()->exec("DROP TABLE IF EXISTS `$tabela`");
        }
        Banco::pdo()->exec($t);
        return true;
    }

    private static function charsetSeguro(string $c): string
    {
        return in_array($c, ['utf8', 'utf8mb4', 'latin1', 'cp1252', 'binary'], true) ? ($c === 'cp1252' ? 'latin1' : $c) : 'utf8mb4';
    }

    /** Índices na área separada, para a conferência e a importação ficarem rápidas (cada um só se a coluna existir) */
    private static function indexar(): void
    {
        $indices = ['n10_clientes' => ['Numero', 'CPF_CNPJ', 'Representante'], 'n10_notas' => ['GUID_Nota', 'NumeroCliente', 'CodigoVendedor'],
            'n10_itensnota' => ['GUID_Nota', 'CodigoProduto'], 'n10_contaspagrec' => ['CredorDevedor', 'GUID_Nota'],
            'n10_contatos' => ['NumeroCliente'], 'n10_followup' => ['cliente'], 'n10_produtos' => ['CodigoProduto'], 'n10_precos' => ['CodigoProduto']];
        $existentes = self::tabelasDaArea();
        foreach ($indices as $tabela => $colunas) {
            if (!in_array($tabela, $existentes, true)) {
                continue;
            }
            foreach ($colunas as $col) {
                try {
                    Banco::pdo()->exec("ALTER TABLE `$tabela` ADD INDEX `ix_$col` (`$col`)");
                } catch (\PDOException) {
                    // coluna não existe nesse backup, ou o índice já existe
                }
            }
        }
    }
}
