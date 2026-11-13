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

    /** Quem administra o sistema vê também os recados técnicos ('admin') */
    public static function ehAdministrador(): bool
    {
        return \App\Core\Auth::pode('sistema.atualizar') || \App\Core\Auth::pode('usuarios.gerenciar');
    }

    /** A versão tem algo para esta pessoa? (a equipe não vê versões só com recado técnico) */
    public static function temAlgoPara(array $versao, bool $admin): bool
    {
        return !empty($versao['novo']) || !empty($versao['melhoria']) || !empty($versao['correcao']) || ($admin && !empty($versao['admin']));
    }

    /** Versões que a pessoa ainda não viu (no máximo 3). Quem nunca viu nenhuma vê só a atual. */
    public static function naoVistas(?string $vista, bool $admin = false): array
    {
        if ($vista === self::atual()) {
            return [];
        }
        $novas = array_values(array_filter(self::lista(), fn($v) => self::temAlgoPara($v, $admin)
            && ($vista === null || $vista === '' || version_compare((string) $v['versao'], $vista, '>'))));
        // quem nunca viu nenhuma vê só a mais recente; os outros, as que perderam (até 3)
        return array_slice($novas, 0, ($vista === null || $vista === '') ? 1 : 3);
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
        $ids = array_column(Banco::todos('SELECT id, perfil_id FROM usuarios WHERE ativo = 1'), 'perfil_id', 'id');
        if (!self::temAlgoPara($v, false)) { // só recado técnico: avisa só quem administra
            $ids = array_filter($ids, function ($perfil, $id) {
                $p = \App\Models\Usuario::permissoes((int) $perfil, (int) $id);
                return array_intersect(['*', 'sistema.atualizar', 'usuarios.gerenciar'], $p) !== [];
            }, ARRAY_FILTER_USE_BOTH);
        }
        return NotificacaoService::avisar(array_keys($ids), 'versao_nova', 'One atualizado: ' . self::nome() . ' — ' . $v['titulo'],
            '/novidades', ['texto' => 'Veja o que há de novo.']);
    }
}
