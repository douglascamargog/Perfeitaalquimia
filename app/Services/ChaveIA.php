<?php
namespace App\Services;

use App\Core\Auditoria;

/**
 * Chave da IA (Anthropic) cadastrada pela tela: Administração › "Chave da IA".
 *
 * Fica em config/segredos.php (a pasta config/ não é pública), com permissão só do dono do arquivo,
 * fora do git, do banco de dados e dos pacotes de atualização. Nunca é mostrada de novo na tela
 * (só o começo e o fim, para saber qual está cadastrada). Se o config.php também tiver uma chave, vale a da tela.
 */
class ChaveIA
{
    private const ARQUIVO = RAIZ . '/config/segredos.php';

    /** "sk-ant-…AbCd" (ou null) */
    public static function mascarada(): ?string
    {
        $c = (string) config('integracoes.anthropic.chave', '');
        return $c === '' ? null : mb_substr($c, 0, 7) . '…' . mb_substr($c, -4);
    }

    /** Grava (ou apaga, com $chave vazia). Devolve uma mensagem de erro ou null */
    public static function salvar(string $chave, int $usuarioId): ?string
    {
        $chave = trim($chave);
        if ($chave !== '' && !preg_match('/^sk-ant-[A-Za-z0-9_\-]{20,}$/', $chave)) {
            return 'Isso não parece uma chave da Anthropic: ela começa com sk-ant- e é bem comprida. Copie de novo em console.anthropic.com › API Keys.';
        }
        $erro = self::gravar(['integracoes', 'anthropic', 'chave'], $chave);
        if (!$erro) {
            Auditoria::registrar('configuracao', null, $chave === '' ? 'remover_chave_ia' : 'trocar_chave_ia', null, ['chave' => $chave === '' ? null : mb_substr($chave, 0, 7) . '…' . mb_substr($chave, -4)]);
        }
        return $erro;
    }

    /** Grava um valor no config/segredos.php ($valor vazio apaga). Usado também pelo Ligue Talk. Devolve erro ou null */
    public static function gravar(array $caminho, string $valor): ?string
    {
        $segredos = is_file(self::ARQUIVO) ? (array) require self::ARQUIVO : [];
        $ref = &$segredos;
        foreach (array_slice($caminho, 0, -1) as $parte) {
            $ref[$parte] = (array) ($ref[$parte] ?? []);
            $ref = &$ref[$parte];
        }
        if ($valor === '') {
            unset($ref[end($caminho)]);
        } else {
            $ref[end($caminho)] = $valor;
        }
        unset($ref);
        $conteudo = "<?php\n// Chaves cadastradas pela tela do One (Administração). NÃO compartilhe este arquivo.\nreturn " . var_export($segredos, true) . ";\n";
        $tmp = self::ARQUIVO . '.tmp';
        if (@file_put_contents($tmp, $conteudo, LOCK_EX) === false || !@rename($tmp, self::ARQUIVO)) {
            @unlink($tmp);
            return 'Não consegui gravar: a pasta config/ do servidor não aceita escrita. No cPanel, dê permissão de escrita à pasta config/ (755) e tente de novo.';
        }
        @chmod(self::ARQUIVO, 0600);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::ARQUIVO, true);
        }
        // vale já nesta requisição
        $alvo = &$GLOBALS['config'];
        foreach (array_slice($caminho, 0, -1) as $parte) {
            $alvo[$parte] = (array) ($alvo[$parte] ?? []);
            $alvo = &$alvo[$parte];
        }
        $alvo[end($caminho)] = $valor;
        return null;
    }
}
