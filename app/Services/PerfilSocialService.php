<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use RuntimeException;

/**
 * Perfil das pessoas, a parte pessoal e personalizável (V11.0):
 * status do dia, frase, cor e capa do perfil, interesses, "pergunte-me sobre", ordem dos blocos,
 * conquistas (selos, sem valores de venda), recados e elogios entre colegas.
 * Tudo fica só dentro do One. Nada de dados de clientes.
 */
class PerfilSocialService
{
    /** Capas prontas (galeria): chave => [nome, fundo CSS] */
    public const CAPAS = [
        'aurora'    => ['Aurora', 'linear-gradient(120deg, #0f3d2e 0%, #1f7a5a 45%, #caff4d 100%)'],
        'oceano'    => ['Oceano', 'linear-gradient(120deg, #0b2545 0%, #13678a 50%, #45c4b0 100%)'],
        'por_do_sol' => ['Pôr do sol', 'linear-gradient(120deg, #3d0c45 0%, #c2185b 45%, #ffb347 100%)'],
        'floresta'  => ['Floresta', 'linear-gradient(135deg, #10291e 0%, #2d6a4f 50%, #95d5b2 100%)'],
        'lavanda'   => ['Lavanda', 'linear-gradient(120deg, #2e1a47 0%, #7b5ea7 50%, #e0c3fc 100%)'],
        'areia'     => ['Areia', 'linear-gradient(120deg, #5c4033 0%, #c69c6d 50%, #f6e7cb 100%)'],
        'noite'     => ['Noite estrelada', 'radial-gradient(circle at 20% 30%, rgba(255,255,255,.35) 0 1px, transparent 2px), radial-gradient(circle at 70% 60%, rgba(255,255,255,.3) 0 1px, transparent 2px), linear-gradient(160deg, #0d1b2a 0%, #1b263b 60%, #415a77 100%)'],
        'cereja'    => ['Cereja', 'linear-gradient(120deg, #590d22 0%, #c9184a 50%, #ffccd5 100%)'],
        'grafite'   => ['Grafite', 'linear-gradient(120deg, #1f2421 0%, #4a4e4d 50%, #a3a9a5 100%)'],
    ];

    /** Cores do perfil (anel da foto, destaques). A pessoa também pode escolher uma livre. */
    public const CORES = ['#1f7a5a', '#13678a', '#c2185b', '#7b5ea7', '#e76f51', '#f4a261', '#2a9d8f', '#264653', '#8ab817'];

    /** Emojis do status do dia */
    public const EMOJIS = ['☕', '🎯', '📞', '🚀', '🏖️', '🤒', '🏠', '🚗', '📦', '🎉', '🧠', '🍀'];

    /** Blocos do perfil (a pessoa escolhe a ordem e quais aparecem) */
    public const BLOCOS = [
        'sobre' => 'Sobre', 'interesses' => 'Interesses e "pergunte-me sobre"', 'conquistas' => 'Conquistas', 'elogios' => 'Elogios',
        'recados' => 'Recados', 'favoritos' => 'Favoritos', 'familia' => 'Família e pets',
    ];

    // ------------------------------------------------------------------
    // Personalização
    // ------------------------------------------------------------------

    /** Ordem dos blocos: [['chave' => 'sobre', 'visivel' => true], ...]. Texto salvo: "sobre,recados,-familia" (- = escondido) */
    public static function blocos(?string $salvo): array
    {
        $lista = [];
        foreach (array_filter(explode(',', (string) $salvo)) as $item) {
            $chave = ltrim($item, '-');
            if (isset(self::BLOCOS[$chave]) && !isset($lista[$chave])) {
                $lista[$chave] = ['chave' => $chave, 'visivel' => $item[0] !== '-'];
            }
        }
        foreach (array_keys(self::BLOCOS) as $chave) { // blocos novos entram no fim, visíveis
            $lista[$chave] ??= ['chave' => $chave, 'visivel' => true];
        }
        return array_values($lista);
    }

    /** Fundo da capa: imagem enviada ou tema da galeria (ou o padrão) */
    public static function fundoCapa(array $p): string
    {
        if (!empty($p['capa'])) {
            return "center / cover no-repeat url('" . url('/' . $p['capa']) . "')";
        }
        return self::CAPAS[$p['capa_tema'] ?? ''][1] ?? self::CAPAS['aurora'][1];
    }

    public static function cor(array $p): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) ($p['cor_perfil'] ?? '')) ? $p['cor_perfil'] : '#1f7a5a';
    }

    /** Etiquetas de "interesses" e "pergunte-me sobre" */
    public static function etiquetas(?string $texto): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map(fn($t) => mb_substr(trim($t), 0, 40), explode(',', (string) $texto)), 'strlen'))), 0, 12);
    }

    /** Meu perfil › Personalizar: frase, cor, capa da galeria, interesses, pergunte-me sobre e ordem dos blocos */
    public static function salvarPersonalizacao(int $uid, array $post): void
    {
        $cor = (string) ($post['cor_perfil'] ?? '');
        if ($cor === 'livre') { // "Outra cor": vem do seletor de cor
            $cor = (string) ($post['cor_livre'] ?? '');
        }
        $dados = [
            'frase' => mb_substr(trim((string) ($post['frase'] ?? '')), 0, 140) ?: null,
            'cor_perfil' => preg_match('/^#[0-9a-f]{6}$/i', $cor) ? strtolower($cor) : null,
            'capa_tema' => isset(self::CAPAS[$post['capa_tema'] ?? '']) ? $post['capa_tema'] : null,
            'interesses' => implode(', ', self::etiquetas((string) ($post['interesses'] ?? ''))) ?: null,
            'pergunte_sobre' => mb_substr(implode(', ', self::etiquetas((string) ($post['pergunte_sobre'] ?? ''))), 0, 200) ?: null,
        ];
        // Ordem: número digitado em cada bloco (ou a ordem da tela); "mostrar" desmarcado = escondido
        $ordem = [];
        foreach (array_keys(self::BLOCOS) as $i => $chave) {
            $ordem[$chave] = is_numeric($post['ordem'][$chave] ?? null) ? (float) $post['ordem'][$chave] : $i + 1;
        }
        asort($ordem);
        $mostrar = (array) ($post['mostrar'] ?? []);
        $dados['blocos'] = implode(',', array_map(fn($c) => (isset($mostrar[$c]) ? '' : '-') . $c, array_keys($ordem)));
        self::gravar($uid, $dados);
        Auditoria::registrar('usuario', $uid, 'perfil_personalizar', null, $dados);
    }

    /** Status do dia ("☕ focada em ligações hoje"); some depois de X horas (Configurações › Pessoas) */
    public static function salvarStatus(int $uid, string $emoji, string $texto): void
    {
        $texto = mb_substr(trim($texto), 0, 80);
        $emoji = in_array($emoji, self::EMOJIS, true) ? $emoji : ($texto !== '' ? '💬' : '');
        $horas = (int) Config::get('pessoas.status_horas', 24);
        self::gravar($uid, $texto === '' && $emoji === '' ? ['status_emoji' => null, 'status_texto' => null, 'status_ate' => null] : [
            'status_emoji' => $emoji, 'status_texto' => $texto ?: null, 'status_ate' => $horas > 0 ? date('Y-m-d H:i:s', time() + $horas * 3600) : null,
        ]);
    }

    /** Status que ainda está no ar: ['emoji' => , 'texto' => ] ou null */
    public static function status(array $p): ?array
    {
        if (empty($p['status_emoji']) && empty($p['status_texto'])) {
            return null;
        }
        if (!empty($p['status_ate']) && $p['status_ate'] < date('Y-m-d H:i:s')) {
            return null;
        }
        return ['emoji' => (string) $p['status_emoji'], 'texto' => (string) $p['status_texto']];
    }

    private static function gravar(int $uid, array $dados): void
    {
        $colunas = array_keys($dados);
        Banco::executar('INSERT INTO usuario_perfil (usuario_id, ' . implode(', ', $colunas) . ') VALUES (?' . str_repeat(', ?', count($colunas)) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $colunas)), [$uid, ...array_values($dados)]);
    }

    // ------------------------------------------------------------------
    // Recados
    // ------------------------------------------------------------------

    public static function recados(int $perfilId, int $limite = 30): array
    {
        return Banco::todos('SELECT r.*, u.nome AS autor, u.foto AS autor_foto FROM perfil_recados r JOIN usuarios u ON u.id = r.autor_id
            WHERE r.perfil_id = ? AND r.oculto_em IS NULL ORDER BY r.criado_em DESC, r.id DESC LIMIT ' . max(1, $limite), [$perfilId]);
    }

    public static function deixarRecado(int $perfilId, int $autorId, string $texto): int
    {
        $texto = mb_substr(trim($texto), 0, 500);
        if ($texto === '') {
            throw new RuntimeException('Escreva o recado.');
        }
        if (!Banco::valor('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$perfilId])) {
            throw new RuntimeException('Pessoa não encontrada.');
        }
        $id = Banco::inserir('perfil_recados', ['perfil_id' => $perfilId, 'autor_id' => $autorId, 'texto' => $texto]);
        if ($perfilId !== $autorId) {
            $autor = (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$autorId]);
            NotificacaoService::avisar([$perfilId], 'perfil_recado', "$autor deixou um recado no seu perfil 💬", "/pessoas/$perfilId#recados",
                ['texto' => mb_strimwidth($texto, 0, 120, '…')], $autorId);
        }
        return $id;
    }

    /** Quem escreveu, o dono do perfil ou quem administra as pessoas pode tirar o recado */
    public static function apagarRecado(int $id, int $uid): bool
    {
        $r = Banco::um('SELECT * FROM perfil_recados WHERE id = ? AND oculto_em IS NULL', [$id]);
        if (!$r || !in_array($uid, [(int) $r['autor_id'], (int) $r['perfil_id']], true) && !Auth::pode('usuarios.gerenciar')) {
            return false;
        }
        Banco::executar('UPDATE perfil_recados SET oculto_em = NOW(), oculto_por = ? WHERE id = ?', [$uid, $id]);
        Auditoria::registrar('usuario', (int) $r['perfil_id'], 'recado_removido', ['texto' => $r['texto'], 'autor_id' => $r['autor_id']], null);
        return true;
    }

    // ------------------------------------------------------------------
    // Elogios
    // ------------------------------------------------------------------

    /** Tipos de elogio (Configurações › Pessoas): chave => ['emoji' => , 'nome' => ] */
    public static function tiposElogio(): array
    {
        $tipos = [];
        foreach ((array) Config::get('pessoas.tipos_elogio', ['🙌 Ajudou muito']) as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            // primeiro "pedaço" é o emoji quando não tem letra nem número
            [$primeiro, $resto] = array_pad(explode(' ', $item, 2), 2, '');
            $temEmoji = $resto !== '' && !preg_match('/[\p{L}\p{N}]/u', $primeiro);
            $nome = $temEmoji ? trim($resto) : $item;
            $tipos[mb_substr(trim(preg_replace('/[^a-z0-9]+/', '_', Normalizar::semAcento(mb_strtolower($nome))), '_'), 0, 40)] = ['emoji' => $temEmoji ? $primeiro : '👏', 'nome' => $nome];
        }
        return $tipos;
    }

    public static function elogiar(int $paraId, int $deId, string $tipo, string $texto = ''): int
    {
        $tipos = self::tiposElogio();
        if (!isset($tipos[$tipo])) {
            throw new RuntimeException('Escolha o tipo de elogio.');
        }
        if ($paraId === $deId) {
            throw new RuntimeException('Elogio é para os colegas 🙂');
        }
        if (!Banco::valor('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$paraId])) {
            throw new RuntimeException('Pessoa não encontrada.');
        }
        if (Banco::valor('SELECT 1 FROM perfil_elogios WHERE para_id = ? AND de_id = ? AND tipo = ? AND DATE(criado_em) = CURDATE() AND oculto_em IS NULL', [$paraId, $deId, $tipo])) {
            throw new RuntimeException('Você já deu esse elogio hoje. Que tal outro tipo?');
        }
        $id = Banco::inserir('perfil_elogios', ['para_id' => $paraId, 'de_id' => $deId, 'tipo' => $tipo, 'texto' => mb_substr(trim($texto), 0, 300) ?: null]);
        $de = (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$deId]);
        NotificacaoService::avisar([$paraId], 'perfil_elogio', "{$tipos[$tipo]['emoji']} $de te elogiou: {$tipos[$tipo]['nome']}", "/pessoas/$paraId#elogios",
            ['texto' => trim($texto) !== '' ? mb_strimwidth(trim($texto), 0, 120, '…') : null], $deId);
        return $id;
    }

    public static function elogios(int $paraId, int $limite = 12): array
    {
        $tipos = self::tiposElogio();
        $lista = Banco::todos('SELECT e.*, u.nome AS autor, u.foto AS autor_foto FROM perfil_elogios e JOIN usuarios u ON u.id = e.de_id
            WHERE e.para_id = ? AND e.oculto_em IS NULL ORDER BY e.criado_em DESC, e.id DESC LIMIT ' . max(1, $limite), [$paraId]);
        foreach ($lista as &$e) {
            $e['emoji'] = $tipos[$e['tipo']]['emoji'] ?? '👏';
            $e['nome_tipo'] = $tipos[$e['tipo']]['nome'] ?? $e['tipo'];
        }
        return $lista;
    }

    /** Quantos de cada tipo (para os "selos" de elogio): [['emoji','nome','total']] do mais recebido ao menos */
    public static function resumoElogios(int $paraId): array
    {
        $tipos = self::tiposElogio();
        $r = [];
        foreach (Banco::todos('SELECT tipo, COUNT(*) AS total FROM perfil_elogios WHERE para_id = ? AND oculto_em IS NULL GROUP BY tipo ORDER BY total DESC', [$paraId]) as $l) {
            $r[] = ['emoji' => $tipos[$l['tipo']]['emoji'] ?? '👏', 'nome' => $tipos[$l['tipo']]['nome'] ?? $l['tipo'], 'total' => (int) $l['total']];
        }
        return $r;
    }

    public static function apagarElogio(int $id, int $uid): bool
    {
        $e = Banco::um('SELECT * FROM perfil_elogios WHERE id = ? AND oculto_em IS NULL', [$id]);
        if (!$e || (int) $e['de_id'] !== $uid && !Auth::pode('usuarios.gerenciar')) {
            return false;
        }
        Banco::executar('UPDATE perfil_elogios SET oculto_em = NOW() WHERE id = ?', [$id]);
        return true;
    }

    // ------------------------------------------------------------------
    // Conquistas (selos). Só selos: nada de valores em reais.
    // ------------------------------------------------------------------

    /** [['emoji', 'titulo', 'detalhe']] */
    public static function conquistas(array $p): array
    {
        $id = (int) $p['id'];
        $selos = [];
        if (!empty($p['data_admissao'])) {
            $dias = (int) floor((time() - strtotime($p['data_admissao'])) / 86400);
            $anos = intdiv($dias, 365);
            if ($dias >= 0 && $dias < 90) {
                $selos[] = ['🌱', 'Chegou agora', 'Bem-vindo(a) à equipe!'];
            } elseif ($anos >= 1) {
                $selos[] = [$anos >= 5 ? '🏆' : ($anos >= 3 ? '🥇' : '⭐'), $anos . ($anos === 1 ? ' ano' : ' anos') . ' de casa', 'Desde ' . data_br($p['data_admissao'])];
            }
        }
        // Metas batidas (Meu mês): quantos meses a pessoa chegou na meta
        $meses = [];
        foreach (Banco::todos('SELECT mes, meta FROM meu_mes WHERE usuario_id = ? AND meta > 0 AND mes <= ? ORDER BY mes DESC', [$id, date('Y-m')]) as $m) {
            if (MetaService::totalMes($id, $m['mes']) >= (float) $m['meta']) {
                $meses[] = $m['mes'];
            }
        }
        if ($meses) {
            [$a, $m] = explode('-', $meses[0]);
            $selos[] = ['🎯', count($meses) === 1 ? 'Bateu a meta' : 'Bateu a meta ' . count($meses) . ' vezes', 'A última em ' . mes_curto((int) $m) . '/' . $a];
        }
        $elogios = (int) Banco::valor('SELECT COUNT(*) FROM perfil_elogios WHERE para_id = ? AND oculto_em IS NULL', [$id]);
        if ($elogios >= 10) {
            $selos[] = ['💛', 'Querido(a) pela equipe', "$elogios elogios recebidos"];
        } elseif ($elogios >= 3) {
            $selos[] = ['👏', 'Reconhecido(a)', "$elogios elogios recebidos"];
        }
        $deu = (int) Banco::valor('SELECT COUNT(*) FROM perfil_elogios WHERE de_id = ? AND oculto_em IS NULL', [$id]);
        if ($deu >= 5) {
            $selos[] = ['🤗', 'Espalha elogios', "Elogiou os colegas $deu vezes"];
        }
        $preenchidos = count(array_filter(['sobre_mim', 'hobbies', 'comida', 'musica', 'frase', 'interesses'], fn($c) => !empty($p[$c])));
        if ($preenchidos >= 5 && !empty($p['foto'])) {
            $selos[] = ['✨', 'Perfil completo', 'Contou tudo sobre si'];
        }
        if (!empty($p['data_nascimento']) && substr($p['data_nascimento'], 5) === date('m-d')) {
            $selos[] = ['🎂', 'Aniversariante do dia', 'Deixe os parabéns!'];
        }
        return $selos;
    }

    /** Para o diretório: elogios recebidos por pessoa [id => total] */
    public static function totaisElogios(): array
    {
        return array_map('intval', array_column(Banco::todos('SELECT para_id, COUNT(*) AS total FROM perfil_elogios WHERE oculto_em IS NULL GROUP BY para_id'), 'total', 'para_id'));
    }
}
