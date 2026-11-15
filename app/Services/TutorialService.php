<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Menu;

/**
 * Tutorial do One em cada tela (V13.0).
 *
 * Cada tela do menu tem um passo a passo (tabela tutorial_telas): o One escurece a tela e aponta cada parte.
 * Para pessoas NOVAS (usuarios.tutorial_ativo = 1) ele abre sozinho, toda vez que ela entra na tela, até ela
 * clicar em "Aprendi tudo desta tela". Para todo mundo, abre quando pede ("O que dá pra fazer aqui?").
 * Quando a pessoa aprende todas as telas que pode abrir, o One comemora e para de abrir sozinho.
 */
class TutorialService
{
    private static ?array $telas = null;

    /** Todos os tutoriais ativos: [rota => linha com 'passos' decodificado] */
    public static function telas(): array
    {
        if (self::$telas === null) {
            self::$telas = [];
            foreach (Banco::todos('SELECT id, rota, titulo, passos_json, ordem FROM tutorial_telas WHERE ativo = 1 ORDER BY ordem, id') as $t) {
                $t['passos'] = array_values(array_filter((array) json_decode($t['passos_json'], true), fn($p) => is_array($p) && trim((string) ($p['texto'] ?? '')) !== ''));
                unset($t['passos_json']);
                self::$telas[$t['rota']] = $t;
            }
        }
        return self::$telas;
    }

    /** O tutorial de uma página (o começo de endereço mais longo; "/" só vale para o Início) */
    public static function daRota(string $pagina): ?array
    {
        $pagina = '/' . trim($pagina, '/');
        $melhor = null;
        foreach (self::telas() as $rota => $t) {
            $ok = $rota === '/' ? $pagina === '/' : str_starts_with($pagina . '/', $rota . '/');
            if ($ok && strlen($rota) > strlen((string) ($melhor['rota'] ?? ''))) {
                $melhor = $t;
            }
        }
        return $melhor && $melhor['passos'] ? $melhor : null;
    }

    /** A pessoa logada pode abrir esta tela? (mesma regra do menu; tela fora do menu = sim) */
    public static function podeVer(string $rota): bool
    {
        $item = Menu::itemDe($rota);
        if (!$item) {
            return true;
        }
        return \App\Controllers\InicioController::visivel($item);
    }

    /** Rotas que a pessoa logada pode abrir (entram no "você aprendeu X de Y telas") */
    public static function rotasDaPessoa(): array
    {
        return array_values(array_filter(array_keys(array_filter(self::telas(), fn($t) => $t['passos'])), [self::class, 'podeVer']));
    }

    /** Rotas já aprendidas: [rota => data] */
    public static function aprendidas(int $usuarioId): array
    {
        return array_column(Banco::todos('SELECT rota, aprendido_em FROM tutorial_progresso WHERE usuario_id = ? AND aprendido_em IS NOT NULL', [$usuarioId]), 'aprendido_em', 'rota');
    }

    /** [feitas, total] da pessoa logada */
    public static function progresso(): array
    {
        $rotas = self::rotasDaPessoa();
        $feitas = count(array_intersect($rotas, array_keys(self::aprendidas((int) Auth::id()))));
        return [$feitas, count($rotas)];
    }

    /**
     * Para o One na tela (JSON): o tutorial desta página e se deve abrir sozinho.
     * Abre sozinho quando a pessoa está com o tutorial ligado e ainda não aprendeu esta tela.
     */
    public static function daTela(string $pagina): ?array
    {
        $t = self::daRota($pagina);
        if (!$t || !Auth::logado()) {
            return null;
        }
        $usuario = Auth::usuario();
        $id = (int) $usuario['id'];
        $aprendido = (bool) Banco::valor('SELECT 1 FROM tutorial_progresso WHERE usuario_id = ? AND rota = ? AND aprendido_em IS NOT NULL', [$id, $t['rota']]);
        $automatico = (int) ($usuario['tutorial_ativo'] ?? 0) === 1 && !$aprendido;
        if ($automatico) {
            Banco::executar('INSERT INTO tutorial_progresso (usuario_id, rota, vezes) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE vezes = vezes + 1', [$id, $t['rota']]);
        }
        [$feitas, $total] = self::progresso();
        return ['rota' => $t['rota'], 'titulo' => $t['titulo'], 'passos' => $t['passos'], 'aprendido' => $aprendido,
            'automatico' => $automatico, 'feitas' => $feitas, 'total' => $total];
    }

    /**
     * "Aprendi tudo desta tela": grava e, se a pessoa terminou todas as telas dela, desliga o tutorial automático
     * e comemora. Devolve [feitas, total, terminou].
     */
    public static function aprendi(string $rota): array
    {
        $id = (int) Auth::id();
        if (!isset(self::telas()[$rota])) {
            return array_merge(self::progresso(), [false]);
        }
        Banco::executar('INSERT INTO tutorial_progresso (usuario_id, rota, vezes, aprendido_em) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE aprendido_em = COALESCE(aprendido_em, NOW())', [$id, $rota]);
        [$feitas, $total] = self::progresso();
        $terminou = $total > 0 && $feitas >= $total && (int) (Auth::usuario()['tutorial_ativo'] ?? 0) === 1;
        if ($terminou) {
            Banco::executar('UPDATE usuarios SET tutorial_ativo = 0 WHERE id = ?', [$id]);
            OneTela::festejar('Você aprendeu todas as telas! 🎓', 'Agora eu paro de abrir os tutoriais sozinho. Se esquecer algo, é só me chamar e clicar em "O que dá pra fazer aqui?".');
        }
        return [$feitas, $total, $terminou];
    }

    /** Liga de novo o tutorial de uma pessoa (o gestor, na tela Equipe do Treinamento): as telas voltam a "não aprendidas" */
    public static function reiniciar(int $usuarioId): void
    {
        Banco::executar('UPDATE usuarios SET tutorial_ativo = 1 WHERE id = ?', [$usuarioId]);
        Banco::executar('UPDATE tutorial_progresso SET aprendido_em = NULL, vezes = 0 WHERE usuario_id = ?', [$usuarioId]);
        \App\Core\Auditoria::registrar('usuario', $usuarioId, 'reiniciar_tutorial');
    }

    /** Quantas telas cada pessoa já aprendeu: [usuario_id => total] (tela Equipe) */
    public static function contagemPorPessoa(): array
    {
        return array_map('intval', array_column(Banco::todos('SELECT usuario_id, COUNT(*) n FROM tutorial_progresso WHERE aprendido_em IS NOT NULL GROUP BY usuario_id'), 'n', 'usuario_id'));
    }

    /** Salvar os passos de uma tela (tela de edição do Treinamento). Uma linha por passo: "seletor | texto" ou só o texto. */
    public static function salvar(int $id, string $titulo, string $passosTexto, bool $ativo): void
    {
        $passos = [];
        foreach (preg_split('/\R/', $passosTexto) as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            [$alvo, $texto] = str_contains($linha, '|') ? array_map('trim', explode('|', $linha, 2)) : ['', $linha];
            $passos[] = ['alvo' => mb_substr($alvo, 0, 200), 'texto' => mb_substr($texto, 0, 400)];
        }
        Banco::executar('UPDATE tutorial_telas SET titulo = ?, passos_json = ?, ativo = ?, editado_em = NOW() WHERE id = ?',
            [mb_substr(trim($titulo), 0, 120) ?: 'Tela', json_encode($passos, JSON_UNESCAPED_UNICODE), $ativo ? 1 : 0, $id]);
        \App\Core\Auditoria::registrar('configuracao', $id, 'editar_tutorial', null, ['passos' => count($passos)]);
        self::$telas = null;
    }
}
