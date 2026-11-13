<?php
namespace App\Services;

use App\Core\Banco;
use RuntimeException;

/**
 * Aniversário: no dia, o sistema do aniversariante fica decorado e todos podem deixar os parabéns no mural.
 * Só se escreve no dia do aniversário; cada aniversário (ano) tem o seu mural.
 */
class AniversarioService
{
    /** A pessoa faz aniversário hoje? */
    public static function eHoje(?array $u): bool
    {
        return !empty($u['data_nascimento']) && substr((string) $u['data_nascimento'], 5) === date('m-d');
    }

    /** Quem faz aniversário hoje (ativos) */
    public static function deHoje(): array
    {
        return Banco::todos("SELECT id, nome, apelido, foto FROM usuarios WHERE ativo = 1 AND data_nascimento IS NOT NULL
            AND DATE_FORMAT(data_nascimento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') ORDER BY nome");
    }

    /** Mensagens do mural deste ano */
    public static function mensagens(int $aniversarianteId): array
    {
        return Banco::todos('SELECT m.*, u.nome AS autor, u.foto AS autor_foto FROM aniversario_mensagens m JOIN usuarios u ON u.id = m.autor_id
            WHERE m.aniversariante_id = ? AND m.ano = ? ORDER BY m.id DESC', [$aniversarianteId, (int) date('Y')]);
    }

    /** Deixa os parabéns (só no dia; não para si mesmo) */
    public static function escrever(int $aniversarianteId, int $autorId, string $texto): int
    {
        $u = Banco::um('SELECT id, nome, data_nascimento, ativo FROM usuarios WHERE id = ?', [$aniversarianteId]);
        if (!$u || !$u['ativo'] || !self::eHoje($u)) {
            throw new RuntimeException('Hoje não é o aniversário desta pessoa.');
        }
        if ($aniversarianteId === $autorId) {
            throw new RuntimeException('O mural é para os colegas deixarem os parabéns para você. 🎉');
        }
        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > 500) {
            throw new RuntimeException('Escreva a sua mensagem (até 500 letras).');
        }
        $id = Banco::inserir('aniversario_mensagens', ['aniversariante_id' => $aniversarianteId, 'ano' => (int) date('Y'), 'autor_id' => $autorId, 'texto' => $texto]);
        $autor = (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$autorId]);
        NotificacaoService::avisar([$aniversarianteId], 'aniversario', "$autor deixou parabéns para você 🎉", '/#mural-aniversario', ['texto' => mb_substr($texto, 0, 120)]);
        return $id;
    }

    /** Apaga a própria mensagem */
    public static function apagar(int $mensagemId, int $autorId): bool
    {
        return Banco::executar('DELETE FROM aniversario_mensagens WHERE id = ? AND autor_id = ?', [$mensagemId, $autorId]) > 0;
    }
}
