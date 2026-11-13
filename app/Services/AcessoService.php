<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;

/**
 * Administração › Acessos: a grade "aba × perfil" (sem acesso / ver / ver e editar),
 * as ações especiais e as exceções de cada pessoa. Grava nas mesmas tabelas que o login usa
 * (perfil_permissoes e usuario_permissoes). O perfil Admin tem tudo e fica fora da grade.
 */
class AcessoService
{
    public static function definicao(): array
    {
        return require RAIZ . '/app/acessos.php';
    }

    /** Perfis da grade (todos menos o Admin) */
    public static function perfis(): array
    {
        return Banco::todos("SELECT id, slug, nome FROM perfis WHERE slug <> 'admin' ORDER BY id");
    }

    /** Nível de uma aba para um conjunto de permissões: 0 sem acesso · 1 ver · 2 ver e editar */
    public static function nivel(array $permissoes, ?string $ver, string $editar): int
    {
        if (in_array('*', $permissoes, true) || in_array($editar, $permissoes, true)) {
            return 2;
        }
        return $ver && in_array($ver, $permissoes, true) ? 1 : 0;
    }

    /** Monta a grade para a tela: [perfil_id => [permissões]] */
    public static function permissoesPorPerfil(): array
    {
        $r = [];
        foreach (self::perfis() as $p) {
            $r[(int) $p['id']] = array_column(Banco::todos('SELECT permissao FROM perfil_permissoes WHERE perfil_id = ?', [$p['id']]), 'permissao');
        }
        return $r;
    }

    /**
     * Grava a grade. $niveis[perfil_id][indice da aba] = 0|1|2 · $especiais[perfil_id][permissao] = '1'
     * Só mexe nas permissões que aparecem na grade (as outras do perfil ficam como estão).
     */
    public static function salvarGrade(array $niveis, array $especiais): int
    {
        $def = self::definicao();
        $abas = array_merge(...array_values($def['abas']));
        $mudancas = 0;
        Banco::transacao(function () use ($abas, $def, $niveis, $especiais, &$mudancas) {
            foreach (self::perfis() as $p) {
                $id = (int) $p['id'];
                $antes = array_column(Banco::todos('SELECT permissao FROM perfil_permissoes WHERE perfil_id = ?', [$id]), 'permissao');
                $depois = $antes;
                foreach ($abas as $i => [, $ver, $editar]) {
                    $n = (int) ($niveis[$id][$i] ?? self::nivel($antes, $ver, $editar));
                    $depois = array_diff($depois, array_filter([$ver, $editar]));
                    if ($n === 2) {
                        $depois[] = $editar;
                    } elseif ($n === 1 && $ver) {
                        $depois[] = $ver;
                    }
                }
                foreach (array_keys($def['especiais']) as $perm) {
                    $depois = array_diff($depois, [$perm]);
                    if (!empty($especiais[$id][$perm])) {
                        $depois[] = $perm;
                    }
                }
                $depois = array_values(array_unique($depois));
                $sai = array_diff($antes, $depois);
                $entra = array_diff($depois, $antes);
                if ($sai || $entra) {
                    if ($sai) {
                        Banco::executar('DELETE FROM perfil_permissoes WHERE perfil_id = ? AND permissao IN (' . implode(',', array_fill(0, count($sai), '?')) . ')', [$id, ...array_values($sai)]);
                    }
                    foreach ($entra as $perm) {
                        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) VALUES (?, ?)', [$id, $perm]);
                    }
                    Auditoria::registrar('acessos', $id, 'perfil', ['retirou' => array_values($sai)], ['deu' => array_values($entra), 'perfil' => $p['nome']]);
                    $mudancas += count($sai) + count($entra);
                }
            }
        });
        return $mudancas;
    }

    /** Exceções de uma pessoa: [permissao => 1 (dá) | 0 (tira)] */
    public static function excecoes(int $usuarioId): array
    {
        return array_map('intval', array_column(Banco::todos('SELECT permissao, concedida FROM usuario_permissoes WHERE usuario_id = ?', [$usuarioId]), 'concedida', 'permissao'));
    }

    /**
     * Grava as exceções da pessoa a partir do nível escolhido por aba (ou "igual ao perfil").
     * $escolhas[indice da aba] = '' (igual ao perfil) | '0' | '1' | '2'
     */
    public static function salvarExcecoes(int $usuarioId, int $perfilId, array $escolhas, array $especiais): void
    {
        $def = self::definicao();
        $abas = array_merge(...array_values($def['abas']));
        $doPerfil = array_column(Banco::todos('SELECT permissao FROM perfil_permissoes WHERE perfil_id = ?', [$perfilId]), 'permissao');
        $antes = self::excecoes($usuarioId);
        $novas = [];
        foreach ($abas as $i => [, $ver, $editar]) {
            $e = (string) ($escolhas[$i] ?? '');
            if ($e === '' || (int) $e === self::nivel($doPerfil, $ver, $editar)) {
                continue; // igual ao perfil: sem exceção
            }
            $n = (int) $e;
            if ($ver) {
                $novas[$ver] = $n >= 1 ? 1 : 0;
            }
            $novas[$editar] = $n === 2 ? 1 : 0;
        }
        foreach (array_keys($def['especiais']) as $perm) {
            $e = (string) ($especiais[$perm] ?? '');
            if ($e !== '' && (int) $e !== (int) in_array($perm, $doPerfil, true)) {
                $novas[$perm] = (int) $e;
            }
        }
        Banco::transacao(function () use ($usuarioId, $novas) {
            Banco::executar('DELETE FROM usuario_permissoes WHERE usuario_id = ?', [$usuarioId]);
            foreach ($novas as $perm => $dar) {
                Banco::inserir('usuario_permissoes', ['usuario_id' => $usuarioId, 'permissao' => $perm, 'concedida' => $dar]);
            }
        });
        if ($antes != $novas) {
            Auditoria::registrar('acessos', $usuarioId, 'pessoa', ['excecoes' => $antes], ['excecoes' => $novas]);
        }
    }
}
