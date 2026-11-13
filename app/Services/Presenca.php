<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Menu;
use App\Core\Sessao;

/**
 * Quem está online: fotinhas no topo com o nome da aba onde cada pessoa está.
 *  - registra a cada página aberta e no resumo que o sino busca a cada minuto;
 *  - guarda só o NOME DA ABA (ex.: "Carteira"), nunca o cliente ou o endereço da página (LGPD);
 *  - online = deu sinal nos últimos 3 minutos.
 */
class Presenca
{
    public const MINUTOS_ONLINE = 3;

    public static function registrar(int $usuarioId, string $caminho): void
    {
        if ($usuarioId <= 0) {
            return;
        }
        $onde = mb_substr(Menu::abaDe($caminho), 0, 60);
        // No máximo uma gravação a cada 45 s (o sino consulta a cada 60 s), a não ser que a pessoa mude de aba
        if (Sessao::get('presenca_onde') === $onde && time() - (int) Sessao::get('presenca_em', 0) < 45) {
            return;
        }
        try {
            Banco::executar('UPDATE usuarios SET visto_em = ?, visto_onde = ? WHERE id = ?', [date('Y-m-d H:i:s'), $onde, $usuarioId]);
        } catch (\Throwable) {
            return; // banco ainda sem as colunas (atualização sendo aplicada): tenta na próxima
        }
        Sessao::set('presenca_onde', $onde);
        Sessao::set('presenca_em', time());
    }

    /** Quem está online agora (sem a própria pessoa), do sinal mais recente para o mais antigo */
    public static function online(int $exceto): array
    {
        try {
            $lista = Banco::todos(
                "SELECT u.id, u.nome, u.apelido, u.foto, u.visto_em, u.visto_onde,
                        (SELECT GROUP_CONCAT(f.nome ORDER BY f.nome SEPARATOR ', ') FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = u.id) AS funcoes
                 FROM usuarios u WHERE u.ativo = 1 AND u.id <> ? AND u.visto_em >= ? ORDER BY u.visto_em DESC",
                [$exceto, date('Y-m-d H:i:s', time() - self::MINUTOS_ONLINE * 60)]
            );
        } catch (\Throwable) {
            return [];
        }
        foreach ($lista as &$u) {
            $min = intdiv(max(0, time() - strtotime((string) $u['visto_em'])), 60);
            $u['ha'] = $min < 1 ? 'agora' : "há $min min";
        }
        return $lista;
    }
}
