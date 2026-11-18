<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Desenvolvedor do sistema: a pessoa que mantém o One (marcada em Administração, nunca pelo nome no código).
 *  - Só ela vê as telas de desenvolvimento: Organograma, Atualização, Importar Nota10, Zerar dados e Dados de teste
 *    (itens do menu com 'so_desenvolvedor' e a permissão virtual "sistema.desenvolvedor", que nem o Admin '*' tem sozinho).
 *  - Pode trocar, só para ela, o jeito de ver o One: "Desenvolvedor (tudo)" ou "como uma função" (ex.: Gestor de Marketing).
 *    No modo função, o One usa só aquela função e as permissões do perfil de quem trabalha nela.
 */
class Desenvolvedor
{
    /** Perfil usado quando ninguém trabalha na função ainda: o de mesmo identificador, senão o da área */
    private const PERFIL_DA_AREA = ['comercial' => 'vendedor', 'marketing' => 'marketing', 'financeiro' => 'financeiro', 'operacao' => 'producao', 'diretoria' => 'diretoria'];

    public static function existe(): bool
    {
        return (bool) Banco::valor('SELECT 1 FROM usuarios WHERE desenvolvedor = 1 AND ativo = 1 LIMIT 1');
    }

    public static function lista(): array
    {
        return Banco::todos('SELECT id, nome, apelido, foto FROM usuarios WHERE desenvolvedor = 1 AND ativo = 1 ORDER BY nome');
    }

    /** Perfil que a função usa no "ver como": o mais comum entre as pessoas da função; senão o de mesmo identificador; senão o da área */
    public static function perfilDaFuncao(array $funcao, ?int $exceto = null): ?array
    {
        $p = Banco::um('SELECT p.id, p.slug, p.nome FROM usuario_funcoes uf JOIN usuarios u ON u.id = uf.usuario_id AND u.ativo = 1 AND u.desenvolvedor = 0
                JOIN perfis p ON p.id = u.perfil_id WHERE uf.funcao_id = ? AND u.id <> ? AND p.slug <> \'admin\'
                GROUP BY p.id, p.slug, p.nome ORDER BY COUNT(*) DESC, p.id LIMIT 1', [(int) $funcao['id'], (int) $exceto]);
        if ($p) {
            return $p;
        }
        foreach ([(string) $funcao['slug'], self::PERFIL_DA_AREA[(string) ($funcao['area'] ?? '')] ?? 'vendedor'] as $slug) {
            if ($p = Banco::um('SELECT id, slug, nome FROM perfis WHERE slug = ?', [$slug])) {
                return $p;
            }
        }
        return null;
    }

    /** Opções do seletor: as funções da pessoa primeiro, depois as outras ativas */
    public static function opcoes(int $usuarioId): array
    {
        $minhas = array_column(Banco::todos('SELECT funcao_id FROM usuario_funcoes WHERE usuario_id = ?', [$usuarioId]), 'funcao_id');
        $lista = [];
        foreach (Banco::todos('SELECT id, slug, nome, area FROM funcoes WHERE ativa = 1 ORDER BY ordem, nome') as $f) {
            $perfil = self::perfilDaFuncao($f, $usuarioId);
            $lista[] = $f + ['minha' => in_array($f['id'], $minhas), 'perfil' => $perfil['nome'] ?? null];
        }
        usort($lista, fn($a, $b) => (int) $b['minha'] <=> (int) $a['minha']);
        return $lista;
    }

    /** Troca o modo (null = desenvolvedor, vê tudo). Só para quem é desenvolvedor. */
    public static function verComo(?int $funcaoId): void
    {
        $u = Auth::usuario();
        if (!Auth::ehDesenvolvedor()) {
            throw new RuntimeException('Só o desenvolvedor do sistema troca o modo.');
        }
        if ($funcaoId && !Banco::valor('SELECT 1 FROM funcoes WHERE id = ? AND ativa = 1', [$funcaoId])) {
            throw new RuntimeException('Função não encontrada.');
        }
        Banco::executar('UPDATE usuarios SET ver_como_funcao_id = ? WHERE id = ?', [$funcaoId ?: null, (int) $u['id']]);
        Auditoria::registrar('usuario', (int) $u['id'], 'ver_como', null, ['funcao_id' => $funcaoId]);
    }

    /** Marca ou desmarca alguém como desenvolvedor. O primeiro se marca sozinho (quem gerencia usuários); depois, só um desenvolvedor marca outro. */
    public static function marcar(int $usuarioId, bool $sim): void
    {
        $primeiro = !self::existe();
        if (!(Auth::ehDesenvolvedor() || ($primeiro && Auth::pode('usuarios.gerenciar') && $usuarioId === (int) Auth::id()))) {
            throw new RuntimeException('Só um desenvolvedor do sistema pode mudar isso.');
        }
        if (!$sim && count(self::lista()) <= 1 && Banco::valor('SELECT desenvolvedor FROM usuarios WHERE id = ?', [$usuarioId])) {
            throw new RuntimeException('Precisa ficar ao menos um desenvolvedor do sistema.');
        }
        Banco::executar('UPDATE usuarios SET desenvolvedor = ?, ver_como_funcao_id = NULL WHERE id = ?', [$sim ? 1 : 0, $usuarioId]);
        Auditoria::registrar('usuario', $usuarioId, $sim ? 'marcar_desenvolvedor' : 'desmarcar_desenvolvedor');
    }
}
