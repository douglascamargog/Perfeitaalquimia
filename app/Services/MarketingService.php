<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Listas;
use App\Models\Post;

/**
 * Regras do Marketing (posts do Instagram e campanhas).
 *
 * Aprovação (quando marketing.aprovacao_obrigatoria = sim):
 *   - Só quem tem marketing.aprovar leva o post para "Aprovado" (ou pede ajuste).
 *   - "Agendado" e "Publicado" só para post aprovado.
 *   - Mudou imagem ou legenda de um post já aprovado → a aprovação cai e ele volta para "Aguardando aprovação".
 * Tudo vira comentário no histórico do post e registro na auditoria.
 */
class MarketingService
{
    public const LIMITE_LEGENDA = 2200;   // limite de caracteres do Instagram
    public const LIMITE_HASHTAGS = 30;
    private const TIPOS_IMAGEM = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const TAMANHO_MAXIMO = 8 * 1024 * 1024;

    public static function etapaDoTipo(string $tipo): ?array
    {
        foreach (Listas::etapasPosts() as $e) {
            if ($e['tipo'] === $tipo) {
                return $e;
            }
        }
        return null;
    }

    private static function aprovacaoObrigatoria(): bool
    {
        return (bool) Config::get('marketing.aprovacao_obrigatoria', true);
    }

    /** Pode levar o post para esta etapa? Devolve null (pode) ou o motivo (não pode) */
    public static function bloqueio(array $post, array $etapa): ?string
    {
        if ($etapa['tipo'] === 'aprovado' && !Auth::pode('marketing.aprovar')) {
            return 'Só quem aprova posts pode marcar como "' . $etapa['nome'] . '". Envie para aprovação.';
        }
        if (in_array($etapa['tipo'], ['agendado', 'publicado'], true) && self::aprovacaoObrigatoria() && !$post['aprovado_em']) {
            return 'Este post ainda não foi aprovado. Envie para aprovação antes de agendar ou publicar.';
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Salvar post
    // ------------------------------------------------------------------

    /** Valida os campos do formulário. Devolve [dados, erros] */
    public static function validar(array $e): array
    {
        $erros = [];
        $dados = [
            'titulo'          => mb_substr(trim((string) ($e['titulo'] ?? '')), 0, 160),
            'formato'         => array_key_exists($e['formato'] ?? '', Post::FORMATOS) ? $e['formato'] : 'feed',
            'data_publicacao' => (string) ($e['data_publicacao'] ?? ''),
            'hora'            => preg_match('/^\d{2}:\d{2}$/', (string) ($e['hora'] ?? '')) ? $e['hora'] : null,
            'legenda'         => trim((string) ($e['legenda'] ?? '')) ?: null,
            'hashtags'        => trim(preg_replace('/\s+/', ' ', (string) ($e['hashtags'] ?? ''))) ?: null,
            'campanha_id'     => (int) ($e['campanha_id'] ?? 0) ?: null,
            'responsavel_id'  => (int) ($e['responsavel_id'] ?? 0) ?: null,
        ];
        if ($dados['titulo'] === '') {
            $erros[] = 'Dê um título ao post (só para a equipe identificar).';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dados['data_publicacao'])) {
            $erros[] = 'Informe a data de publicação.';
        }
        if (mb_strlen((string) $dados['legenda']) > self::LIMITE_LEGENDA) {
            $erros[] = 'A legenda passou de ' . self::LIMITE_LEGENDA . ' caracteres (limite do Instagram).';
        }
        if (preg_match_all('/#[\p{L}\p{N}_]+/u', (string) $dados['legenda'] . ' ' . $dados['hashtags']) > self::LIMITE_HASHTAGS) {
            $erros[] = 'O Instagram aceita no máximo ' . self::LIMITE_HASHTAGS . ' hashtags por post.';
        }
        if ($dados['campanha_id'] && !Banco::valor('SELECT 1 FROM mkt_campanhas WHERE id = ?', [$dados['campanha_id']])) {
            $dados['campanha_id'] = null;
        }
        if ($dados['responsavel_id'] && !in_array($dados['responsavel_id'], array_map('intval', array_column(Post::responsaveis(), 'id')), true)) {
            $dados['responsavel_id'] = null;
        }
        return [$dados, $erros];
    }

    public static function criar(array $dados, ?int $midiaId, int $usuarioId): int
    {
        $dados += ['etapa_id' => (int) (Listas::etapasPosts()[0]['id']), 'midia_id' => $midiaId, 'criado_por' => $usuarioId];
        $dados['responsavel_id'] ??= $usuarioId;
        $id = Banco::inserir('mkt_posts', $dados);
        Auditoria::registrar('post', $id, 'criar', null, $dados);
        self::comentar($id, $usuarioId, 'Post criado.', 'etapa');
        return $id;
    }

    /** Atualiza. Se mudou imagem/legenda de post aprovado, a aprovação cai. Devolve mensagem */
    public static function atualizar(array $post, array $dados, ?int $midiaId, int $usuarioId): string
    {
        if ($midiaId) {
            $dados['midia_id'] = $midiaId;
        }
        $mensagem = 'Post salvo.';
        $mudouConteudo = ($midiaId && $midiaId !== (int) $post['midia_id']) || (string) $dados['legenda'] !== (string) $post['legenda'] || (string) $dados['hashtags'] !== (string) $post['hashtags'];
        if ($post['aprovado_em'] && $mudouConteudo && self::aprovacaoObrigatoria() && $post['etapa_tipo'] !== 'publicado') {
            $aguardando = self::etapaDoTipo('aprovacao');
            $dados += ['aprovado_em' => null, 'aprovado_por' => null] + ($aguardando ? ['etapa_id' => (int) $aguardando['id']] : []);
            self::comentar((int) $post['id'], $usuarioId, 'Imagem ou legenda mudou depois da aprovação: o post voltou para aprovação.', 'etapa');
            $mensagem = 'Post salvo. Como a imagem/legenda mudou, ele voltou para aprovação.';
        }
        Banco::atualizar('mkt_posts', (int) $post['id'], $dados);
        Auditoria::registrar('post', (int) $post['id'], 'editar', $post, $dados);
        return $mensagem;
    }

    public static function mudarData(array $post, string $data, int $usuarioId): void
    {
        Banco::atualizar('mkt_posts', (int) $post['id'], ['data_publicacao' => $data]);
        Auditoria::registrar('post', (int) $post['id'], 'data', ['data_publicacao' => $post['data_publicacao']], ['data_publicacao' => $data]);
        self::comentar((int) $post['id'], $usuarioId, 'Data mudou de ' . data_br($post['data_publicacao']) . ' para ' . data_br($data) . '.', 'etapa');
    }

    /** Muda a etapa respeitando a aprovação. Devolve null (ok) ou o motivo do bloqueio */
    public static function mudarEtapa(array $post, int $etapaId, int $usuarioId): ?string
    {
        $etapa = Banco::um('SELECT * FROM mkt_post_etapas WHERE id = ? AND ativa = 1', [$etapaId]);
        if (!$etapa) {
            return 'Etapa inválida.';
        }
        if ((int) $post['etapa_id'] === $etapaId) {
            return null;
        }
        if ($motivo = self::bloqueio($post, $etapa)) {
            return $motivo;
        }
        $dados = ['etapa_id' => $etapaId];
        if ($etapa['tipo'] === 'aprovado') {
            $dados += ['aprovado_por' => $usuarioId, 'aprovado_em' => date('Y-m-d H:i:s'), 'motivo_ajuste' => null];
        }
        Banco::atualizar('mkt_posts', (int) $post['id'], $dados);
        Auditoria::registrar('post', (int) $post['id'], 'etapa', ['etapa_id' => $post['etapa_id']], $dados);
        self::comentar((int) $post['id'], $usuarioId, $post['etapa'] . ' → ' . $etapa['nome'], $etapa['tipo'] === 'aprovado' ? 'aprovacao' : 'etapa');
        return null;
    }

    public static function aprovar(array $post, int $usuarioId): ?string
    {
        $etapa = self::etapaDoTipo('aprovado');
        return $etapa ? self::mudarEtapa($post, (int) $etapa['id'], $usuarioId) : 'Não existe etapa do tipo "Aprovado" (Configurações › Listas).';
    }

    public static function pedirAjuste(array $post, string $motivo, int $usuarioId): void
    {
        $etapa = self::etapaDoTipo('ajuste') ?? self::etapaDoTipo('producao');
        $dados = ['motivo_ajuste' => mb_substr($motivo, 0, 500), 'aprovado_em' => null, 'aprovado_por' => null] + ($etapa ? ['etapa_id' => (int) $etapa['id']] : []);
        Banco::atualizar('mkt_posts', (int) $post['id'], $dados);
        Auditoria::registrar('post', (int) $post['id'], 'ajuste', ['etapa_id' => $post['etapa_id']], $dados);
        self::comentar((int) $post['id'], $usuarioId, 'Pediu ajuste: ' . $motivo, 'ajuste');
    }

    public static function publicar(array $post, string $link, int $usuarioId): ?string
    {
        $etapa = self::etapaDoTipo('publicado');
        if (!$etapa) {
            return 'Não existe etapa do tipo "Publicado".';
        }
        if ($motivo = self::bloqueio($post, $etapa)) {
            return $motivo;
        }
        Banco::atualizar('mkt_posts', (int) $post['id'], ['etapa_id' => (int) $etapa['id'], 'link_publicado' => $link]);
        Auditoria::registrar('post', (int) $post['id'], 'publicar', ['etapa_id' => $post['etapa_id']], ['etapa_id' => (int) $etapa['id'], 'link_publicado' => $link]);
        self::comentar((int) $post['id'], $usuarioId, 'Publicado: ' . $link, 'etapa');
        return null;
    }

    public static function comentar(int $postId, ?int $usuarioId, string $texto, string $tipo = 'comentario'): void
    {
        Banco::inserir('mkt_post_comentarios', ['post_id' => $postId, 'usuario_id' => $usuarioId, 'texto' => mb_substr($texto, 0, 2000), 'tipo' => $tipo]);
    }

    // ------------------------------------------------------------------
    // Imagem do post (biblioteca de mídia)
    // ------------------------------------------------------------------

    /**
     * Guarda a imagem enviada em public/media/marketing/ (nome = sha1 do conteúdo: a mesma imagem não é guardada duas vezes).
     * Devolve o id na tabela midias, null se não veio arquivo. Lança \RuntimeException com mensagem amigável.
     */
    public static function guardarImagem(?array $arquivo, string $alt): ?int
    {
        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new \RuntimeException('Não foi possível receber a imagem. Tente de novo (até 8 MB).');
        }
        if ($arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new \RuntimeException('A imagem passou de 8 MB. Exporte em tamanho menor (1080 × 1350 px é o ideal para o feed).');
        }
        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::TIPOS_IMAGEM[$tipo])) {
            throw new \RuntimeException('Envie uma imagem JPG, PNG, WEBP ou GIF. (Vídeos de reels: anexe a capa e guarde o vídeo no Drive.)');
        }
        $hash = sha1_file($arquivo['tmp_name']);
        $existente = Banco::valor('SELECT id FROM midias WHERE hash = ?', [$hash]);
        if ($existente) {
            return (int) $existente;
        }
        $caminho = 'media/marketing/' . $hash . '.' . self::TIPOS_IMAGEM[$tipo];
        $pasta = RAIZ . '/public/media/marketing';
        if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
            throw new \RuntimeException('Sem permissão para gravar imagens no servidor.');
        }
        if (!move_uploaded_file($arquivo['tmp_name'], RAIZ . '/public/' . $caminho)) {
            throw new \RuntimeException('Não foi possível guardar a imagem no servidor.');
        }
        return Banco::inserir('midias', ['arquivo' => $caminho, 'tipo' => $tipo, 'tamanho' => (int) $arquivo['size'], 'hash' => $hash,
            'alt' => mb_substr($alt, 0, 255), 'origem' => 'Marketing']);
    }

    // ------------------------------------------------------------------
    // Calendário
    // ------------------------------------------------------------------

    /** Semanas (seg → dom) que cobrem o mês. Cada semana: lista de 7 datas */
    public static function semanas(string $mes): array
    {
        $inicio = strtotime("$mes-01");
        $primeiro = strtotime('monday this week', $inicio);
        $ultimo = strtotime('sunday this week', strtotime(date('Y-m-t', $inicio)));
        $semanas = [];
        for ($d = $primeiro; $d <= $ultimo; $d = strtotime('+7 days', $d)) {
            $semanas[] = array_map(fn($i) => date('Y-m-d', strtotime("+$i days", $d)), range(0, 6));
        }
        return $semanas;
    }

    /** Datas comemorativas do ano (Configurações): ['MM-DD' => 'Nome'] */
    public static function datasComemorativas(): array
    {
        $datas = [];
        foreach ((array) Config::get('marketing.datas_comemorativas', []) as $item) {
            if (preg_match('#^(\d{2})/(\d{2})\s+(.+)$#u', trim($item), $m)) {
                $datas[$m[2] . '-' . $m[1]] = $m[3];
            }
        }
        return $datas;
    }

    /** Meta de posts por semana */
    public static function metaSemanal(): int
    {
        return max(0, (int) Config::get('marketing.posts_por_semana_meta', 5));
    }

    // ------------------------------------------------------------------
    // Campanhas
    // ------------------------------------------------------------------

    /** Valida e salva uma campanha (nova ou existente). Devolve [id, erros] */
    public static function salvarCampanha(?int $id, array $e, int $usuarioId): array
    {
        $dados = [
            'nome' => mb_substr(trim((string) ($e['nome'] ?? '')), 0, 120),
            'objetivo' => mb_substr(trim((string) ($e['objetivo'] ?? '')), 0, 255) ?: null,
            'inicio' => (string) ($e['inicio'] ?? ''), 'fim' => (string) ($e['fim'] ?? ''),
            'cor' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($e['cor'] ?? '')) ? $e['cor'] : '#9bd344',
            'meta_descricao' => mb_substr(trim((string) ($e['meta_descricao'] ?? '')), 0, 255) ?: null,
            'orcamento' => \App\Controllers\MeuMesController::valor((string) ($e['orcamento'] ?? '')) ?: null,
            'produto_id' => (int) ($e['produto_id'] ?? 0) ?: null,
            'situacao' => in_array($e['situacao'] ?? '', ['planejada', 'ativa', 'encerrada'], true) ? $e['situacao'] : 'planejada',
            'observacoes' => trim((string) ($e['observacoes'] ?? '')) ?: null,
        ];
        $erros = [];
        if ($dados['nome'] === '') {
            $erros[] = 'Informe o nome da campanha.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dados['inicio']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dados['fim']) || $dados['fim'] < $dados['inicio']) {
            $erros[] = 'Confira as datas: o fim precisa ser igual ou depois do início.';
        }
        if ($dados['produto_id'] && !Banco::valor('SELECT 1 FROM produtos WHERE id = ?', [$dados['produto_id']])) {
            $dados['produto_id'] = null;
        }
        if ($erros) {
            return [null, $erros];
        }
        if ($id) {
            $antes = Banco::um('SELECT * FROM mkt_campanhas WHERE id = ?', [$id]);
            Banco::atualizar('mkt_campanhas', $id, $dados);
            Auditoria::registrar('campanha', $id, 'editar', $antes, $dados);
        } else {
            $id = Banco::inserir('mkt_campanhas', $dados + ['criado_por' => $usuarioId]);
            Auditoria::registrar('campanha', $id, 'criar', null, $dados);
        }
        return [$id, []];
    }
}
