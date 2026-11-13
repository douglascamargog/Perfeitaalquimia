<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Versões do One (lista em app/versoes.php) e o aviso de "Novidades" para a equipe.
 *  - cada pessoa guarda a última versão que viu (usuarios.versao_vista);
 *  - enquanto não viu a atual, a janela de novidades aparece uma vez ao abrir o sistema;
 *  - quando uma atualização é aplicada, todos os usuários ativos recebem um aviso no sino.
 */
class Versoes
{
    private static ?array $lista = null;

    /** Todas as versões, da mais nova para a mais antiga */
    public static function lista(): array
    {
        return self::$lista ??= require RAIZ . '/app/versoes.php';
    }

    /** Número da versão atual, ex.: "2.0" */
    public static function atual(): string
    {
        return (string) self::lista()[0]['versao'];
    }

    /** Nome completo, ex.: "ONE_V2.0" (é também o nome do pacote .zip) */
    public static function nome(?string $versao = null): string
    {
        return 'ONE_V' . ($versao ?? self::atual());
    }

    /** Versões que a pessoa ainda não viu (no máximo 3). Quem nunca viu nenhuma vê só a atual. */
    public static function naoVistas(?string $vista): array
    {
        if ($vista === self::atual()) {
            return [];
        }
        if ($vista === null || $vista === '') {
            return [self::lista()[0]];
        }
        $novas = array_filter(self::lista(), fn($v) => version_compare((string) $v['versao'], $vista, '>'));
        return array_slice(array_values($novas), 0, 3);
    }

    public static function marcarVista(int $usuarioId): void
    {
        Banco::executar('UPDATE usuarios SET versao_vista = ? WHERE id = ?', [self::atual(), $usuarioId]);
    }

    /**
     * Chamado ao abrir qualquer tela: na primeira vez que o sistema roda uma versão nova, avisa a equipe.
     * Assim o aviso sai mesmo quando a atualização só troca arquivos (sem nada para "Aplicar").
     * O INSERT IGNORE garante um aviso só, mesmo com duas pessoas abrindo ao mesmo tempo.
     */
    public static function avisarSeNova(bool $instalacaoNova = false): int
    {
        try {
            if (Banco::valor('SELECT 1 FROM migracoes WHERE arquivo = ?', ['versao:' . self::atual()])) {
                return 0; // já avisada (o caso de quase todas as páginas: só uma leitura rápida)
            }
            $primeiraVez = Banco::executar('INSERT IGNORE INTO migracoes (arquivo) VALUES (?)', ['versao:' . self::atual()]) === 1;
        } catch (\Throwable) {
            return 0; // banco ainda sem a tabela de controle (instalação não feita)
        }
        if (!$primeiraVez) {
            return 0;
        }
        if ($instalacaoNova) { // instalação nova: ninguém precisa das novidades
            Banco::executar('UPDATE usuarios SET versao_vista = ?', [self::atual()]);
            return 0;
        }
        return self::avisarEquipe();
    }

    /** Depois de aplicar uma atualização: aviso no sino para todos os usuários ativos (uma vez por versão) */
    public static function avisarEquipe(): int
    {
        $v = self::lista()[0];
        $qtd = count($v['novo'] ?? []) + count($v['melhoria'] ?? []) + count($v['correcao'] ?? []);
        $ids = array_column(Banco::todos('SELECT id FROM usuarios WHERE ativo = 1'), 'id');
        return NotificacaoService::avisar($ids, 'versao_nova', 'One atualizado: ' . self::nome() . ' — ' . $v['titulo'],
            '/novidades', ['texto' => $qtd . ' mudança(s): veja o que há de novo.']);
    }
}
