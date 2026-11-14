<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Tarefas do Marketing e pedidos dos outros setores ao Marketing.
 *
 *  - Qualquer pessoa pede ao Marketing (tipo + descrição + prazo + anexo). O TIPO diz qual FUNÇÃO recebe
 *    (Social Media, Gestor de Marketing, E-commerce e Marketplaces...) — configurável em Listas.
 *  - a_fazer → fazendo → aprovacao (quem aprova o Marketing) → feito. Dá para concluir direto, sem aprovação.
 *  - Quem pediu é avisado quando fica pronto e quando o Marketing comenta. Quem recebe é avisado do pedido novo.
 *  - Quem faz ou aprova o Marketing (marketing.editar / marketing.aprovar) vê todas; os outros, só os próprios pedidos.
 */
class MarketingTarefaService
{
    public const SITUACOES = [
        'a_fazer'   => ['A fazer', 'azul'],
        'fazendo'   => ['Fazendo', 'laranja'],
        'aprovacao' => ['Esperando aprovação', 'roxo'],
        'feito'     => ['Feito', 'verde'],
        'cancelado' => ['Cancelado', 'cinza'],
    ];
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const TAMANHO_MAXIMO = 15 * 1024 * 1024;

    private const CAMPOS = 't.*, ti.nome AS tipo_nome, f.nome AS funcao, r.nome AS responsavel, s.nome AS solicitante';
    private const JUNCOES = 'FROM mkt_tarefas t LEFT JOIN mkt_tipos ti ON ti.id = t.tipo_id LEFT JOIN funcoes f ON f.slug = t.funcao_slug
        LEFT JOIN usuarios r ON r.id = t.responsavel_id LEFT JOIN usuarios s ON s.id = t.solicitante_id';

    public static function pasta(): string
    {
        return RAIZ . '/storage/marketing';
    }

    public static function tipos(): array
    {
        return Banco::todos('SELECT t.*, f.nome AS funcao FROM mkt_tipos t LEFT JOIN funcoes f ON f.slug = t.funcao_slug WHERE t.ativo = 1 ORDER BY t.ordem, t.nome');
    }

    /** Funções que recebem tarefas do Marketing (as dos tipos + a da pessoa) */
    public static function funcoes(): array
    {
        return Banco::todos('SELECT DISTINCT f.slug, f.nome FROM funcoes f JOIN mkt_tipos t ON t.funcao_slug = f.slug WHERE f.ativa = 1 ORDER BY f.nome');
    }

    /** Slugs das funções da pessoa */
    public static function minhasFuncoes(?int $usuarioId = null): array
    {
        return array_column(Banco::todos('SELECT f.slug FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ?',
            [$usuarioId ?? (int) Auth::id()]), 'slug');
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE t.id = ?', [$id]);
    }

    public static function historico(int $id): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario FROM mkt_tarefa_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.tarefa_id = ? ORDER BY h.criado_em DESC, h.id DESC', [$id]);
    }

    public static function podeVer(array $t): bool
    {
        return self::podeMexer() || Auth::pode('marketing.aprovar') || (int) $t['solicitante_id'] === Auth::id() || (int) $t['responsavel_id'] === Auth::id();
    }

    public static function podeMexer(): bool
    {
        return Auth::pode('marketing.editar');
    }

    /**
     * Tarefas abertas + feitas nos últimos 7 dias. $so: 'minhas' (das minhas funções ou comigo) | 'todas'.
     */
    public static function quadro(string $so = 'minhas'): array
    {
        $w = ["(t.situacao IN ('a_fazer','fazendo','aprovacao') OR (t.situacao = 'feito' AND t.concluido_em >= NOW() - INTERVAL 7 DAY))"];
        $par = [];
        if ($so === 'minhas') {
            $funcoes = self::minhasFuncoes();
            $cond = ['t.responsavel_id = ?'];
            $par[] = Auth::id();
            if ($funcoes) {
                $cond[] = "t.funcao_slug IN ('" . implode("','", array_map(fn($f) => preg_replace('/[^a-z0-9_]/', '', $f), $funcoes)) . "')";
            }
            if (Auth::pode('marketing.aprovar')) {
                $cond[] = "t.situacao = 'aprovacao'"; // quem aprova vê tudo que espera aprovação
            }
            $w[] = '(' . implode(' OR ', $cond) . ')';
        }
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE ' . implode(' AND ', $w)
            . ' ORDER BY t.urgente DESC, t.prazo IS NULL, t.prazo, t.id', $par);
    }

    /** Pedidos que a pessoa fez ao Marketing */
    public static function meusPedidos(int $usuarioId): array
    {
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE t.solicitante_id = ? AND t.setor IS NOT NULL
            AND (t.situacao NOT IN ('feito','cancelado') OR COALESCE(t.concluido_em, t.atualizado_em, t.criado_em) >= NOW() - INTERVAL 30 DAY)
            ORDER BY t.situacao IN ('feito','cancelado'), t.criado_em DESC LIMIT 50", [$usuarioId]);
    }

    /** Prazos do período (para o mini calendário) */
    public static function prazosEntre(string $de, string $ate): array
    {
        return Banco::todos("SELECT t.id, t.titulo, t.prazo, t.situacao, t.urgente FROM mkt_tarefas t
            WHERE t.prazo BETWEEN ? AND ? AND t.situacao <> 'cancelado' ORDER BY t.prazo, t.id", [$de, $ate]);
    }

    /** Números do resumo (Início do Marketing) */
    public static function resumo(): array
    {
        $hoje = date('Y-m-d');
        $seg = date('Y-m-d', strtotime('monday this week'));
        $dom = date('Y-m-d', strtotime('sunday this week'));
        return [
            'posts_semana' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p WHERE p.data_publicacao BETWEEN ? AND ?", [$seg, $dom]),
            'aprovacao' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE situacao = 'aprovacao'")
                + (int) Banco::valor("SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE e.tipo = 'aprovacao'"),
            'atrasadas' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE situacao IN ('a_fazer','fazendo') AND prazo < ?", [$hoje]),
            'pedidos_novos' => (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE setor IS NOT NULL AND situacao = 'a_fazer'"),
            'rotulos' => (int) Banco::valor("SELECT COUNT(*) FROM dp_projeto_etapas pe JOIN dp_etapas e ON e.id = pe.etapa_id JOIN dp_projetos p ON p.id = pe.projeto_id
                WHERE pe.situacao = 'andamento' AND p.situacao = 'andamento' AND e.funcao_slug IN (SELECT DISTINCT funcao_slug FROM mkt_tipos)"),
        ];
    }

    /** Quantas esperam a pessoa agora (contador do menu): atrasadas das funções dela + aprovações, se aprova */
    public static function pendentes(int $usuarioId): int
    {
        $funcoes = self::minhasFuncoes($usuarioId);
        $n = 0;
        if ($funcoes) {
            $lista = "'" . implode("','", array_map(fn($f) => preg_replace('/[^a-z0-9_]/', '', $f), $funcoes)) . "'";
            $n += (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE situacao IN ('a_fazer','fazendo') AND (prazo < CURDATE() OR urgente = 1)
                AND (funcao_slug IN ($lista) OR responsavel_id = ?)", [$usuarioId]);
        }
        if (Auth::pode('marketing.aprovar')) {
            $n += (int) Banco::valor("SELECT COUNT(*) FROM mkt_tarefas WHERE situacao = 'aprovacao'");
        }
        return $n;
    }

    // ------------------------------------------------------------------
    // Criar e mudar
    // ------------------------------------------------------------------

    /**
     * Cria a tarefa. $pedido = true: veio de outro setor ("Pedir ao Marketing"). Devolve [id, erros].
     * $d: titulo, descricao, tipo_id, prazo, urgente, funcao_slug (só tarefa interna), responsavel_id
     */
    public static function criar(array $d, ?array $arquivo, int $usuarioId, bool $pedido): array
    {
        $titulo = mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 160);
        $tipo = Banco::um('SELECT * FROM mkt_tipos WHERE id = ? AND ativo = 1', [(int) ($d['tipo_id'] ?? 0)]);
        $erros = [];
        if ($titulo === '') {
            $erros[] = 'Escreva em poucas palavras o que você precisa.';
        }
        if (!$tipo) {
            $erros[] = 'Escolha o tipo do pedido.';
        }
        $prazo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['prazo'] ?? '')) ? $d['prazo'] : null;
        if ($prazo && $prazo < date('Y-m-d')) {
            $erros[] = 'O prazo não pode ser uma data que já passou.';
        }
        if ($erros) {
            return [null, $erros];
        }
        try {
            [$anexo, $anexoNome, $anexoMime] = self::guardarAnexo($arquivo ?? []);
        } catch (RuntimeException $e) {
            return [null, [$e->getMessage()]];
        }
        $funcao = $tipo['funcao_slug'];
        if (!$pedido && ($d['funcao_slug'] ?? '') !== '' && Banco::valor('SELECT 1 FROM funcoes WHERE slug = ?', [$d['funcao_slug']])) {
            $funcao = $d['funcao_slug'];
        }
        $setor = null;
        if ($pedido) {
            $setor = Banco::valor('SELECT f.nome FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ? ORDER BY f.ordem LIMIT 1', [$usuarioId])
                ?: (string) Banco::valor('SELECT p.nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.id = ?', [$usuarioId]);
        }
        $id = Banco::inserir('mkt_tarefas', array_filter([
            'titulo' => $titulo, 'descricao' => mb_substr(trim((string) ($d['descricao'] ?? '')), 0, 5000) ?: null, 'tipo_id' => (int) $tipo['id'],
            'funcao_slug' => $funcao, 'responsavel_id' => !$pedido ? ((int) ($d['responsavel_id'] ?? 0) ?: null) : null,
            'urgente' => !empty($d['urgente']) ? 1 : 0, 'prazo' => $prazo, 'solicitante_id' => $usuarioId, 'setor' => $setor,
            'anexo' => $anexo, 'anexo_nome' => $anexoNome, 'anexo_mime' => $anexoMime,
        ], fn($v) => $v !== null));
        self::historico_($id, $pedido ? "Pedido feito ($setor)." : 'Tarefa criada.', $usuarioId);
        Auditoria::registrar('mkt_tarefa', $id, 'criar', null, ['titulo' => $titulo, 'funcao' => $funcao]);
        // Avisa quem é da função que recebe (e o responsável, se já tem)
        $nomeFuncao = (string) Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$funcao]);
        NotificacaoService::avisar(array_merge(self::pessoasDaFuncao($funcao), array_filter([(int) ($d['responsavel_id'] ?? 0)])), 'mkt_tarefa',
            ($pedido ? "Pedido ao Marketing ($nomeFuncao): " : 'Tarefa nova: ') . $titulo, '/marketing/tarefas/' . $id,
            ['texto' => trim(($setor ? "De $setor" : '') . ($prazo ? ' · prazo ' . data_br($prazo) : '') . (!empty($d['urgente']) ? ' · URGENTE' : ''), ' ·')], $usuarioId);
        return [$id, []];
    }

    /**
     * Muda a situação. $acao: comecar | aprovacao | aprovar | devolver | concluir | cancelar | reabrir
     * Devolve o erro (ou null).
     */
    public static function mudar(array $t, string $acao, int $usuarioId, string $comentario = ''): ?string
    {
        $comentario = mb_substr(trim($comentario), 0, 1000);
        $agora = date('Y-m-d H:i:s');
        $marketing = self::podeMexer();
        $sit = $t['situacao'];
        $dados = ['atualizado_em' => $agora];
        switch ($acao) {
            case 'comecar':
                if (!$marketing || $sit !== 'a_fazer') {
                    return 'Só dá para começar uma tarefa que está "A fazer".';
                }
                $dados += ['situacao' => 'fazendo', 'iniciado_em' => $agora, 'responsavel_id' => $t['responsavel_id'] ?: $usuarioId];
                $texto = 'Começou a fazer.';
                break;
            case 'aprovacao':
                if (!$marketing || !in_array($sit, ['a_fazer', 'fazendo'], true)) {
                    return 'Esta tarefa não pode ir para aprovação agora.';
                }
                $dados += ['situacao' => 'aprovacao', 'responsavel_id' => $t['responsavel_id'] ?: $usuarioId];
                $texto = 'Enviou para aprovação.';
                break;
            case 'aprovar':
            case 'devolver':
                if (!Auth::pode('marketing.aprovar') || $sit !== 'aprovacao') {
                    return 'Só quem aprova o Marketing decide, e só tarefas esperando aprovação.';
                }
                if ($acao === 'devolver' && $comentario === '') {
                    return 'Escreva o que precisa ajustar.';
                }
                $dados += $acao === 'aprovar' ? ['situacao' => 'feito', 'concluido_em' => $agora] : ['situacao' => 'fazendo'];
                $texto = $acao === 'aprovar' ? 'Aprovou: tarefa concluída.' : 'Pediu ajuste.';
                break;
            case 'concluir':
                if (!$marketing || !in_array($sit, ['a_fazer', 'fazendo'], true)) {
                    return 'Esta tarefa não pode ser concluída agora.';
                }
                $dados += ['situacao' => 'feito', 'concluido_em' => $agora, 'responsavel_id' => $t['responsavel_id'] ?: $usuarioId];
                $texto = 'Concluiu.';
                break;
            case 'cancelar':
                $dono = (int) $t['solicitante_id'] === $usuarioId && $sit === 'a_fazer';
                if (!($marketing || $dono) || in_array($sit, ['feito', 'cancelado'], true)) {
                    return 'Esta tarefa não pode ser cancelada.';
                }
                $dados += ['situacao' => 'cancelado'];
                $texto = 'Cancelou.';
                break;
            case 'reabrir':
                if (!$marketing || !in_array($sit, ['feito', 'cancelado'], true)) {
                    return 'Só dá para reabrir tarefa feita ou cancelada.';
                }
                $dados += ['situacao' => 'a_fazer', 'concluido_em' => null];
                $texto = 'Reabriu.';
                break;
            default:
                return 'Ação desconhecida.';
        }
        Banco::atualizar('mkt_tarefas', (int) $t['id'], $dados);
        self::historico_((int) $t['id'], $texto . ($comentario !== '' ? ' ' . $comentario : ''), $usuarioId);
        Auditoria::registrar('mkt_tarefa', (int) $t['id'], $acao, ['situacao' => $sit], ['situacao' => $dados['situacao'] ?? $sit]);
        $link = '/marketing/tarefas/' . (int) $t['id'];
        $novo = $dados['situacao'] ?? $sit;
        if ($novo === 'aprovacao') {
            Banco::executar("UPDATE notificacoes SET lida_em = NOW() WHERE tipo = 'mkt_aprovar' AND link = ? AND lida_em IS NULL", [$link]);
            NotificacaoService::avisar(NotificacaoService::destinatarios('marketing.aprovar', 'marketing'), 'mkt_aprovar', 'Para aprovar: ' . $t['titulo'], $link,
                ['texto' => (string) $t['funcao']], $usuarioId);
        }
        if ($acao === 'devolver') {
            NotificacaoService::avisar([(int) ($t['responsavel_id'] ?? 0)], 'mkt_ajuste', 'Ajustar: ' . $t['titulo'], $link, ['texto' => $comentario], $usuarioId);
        }
        if ($novo === 'feito' && $t['setor'] !== null) {
            NotificacaoService::avisar([(int) $t['solicitante_id']], 'mkt_pronto', 'Pronto: ' . $t['titulo'], $link, ['texto' => 'O Marketing concluiu o seu pedido.'], $usuarioId);
        }
        PendenciasService::limpar();
        return null;
    }

    /** Comentário. Do Marketing → avisa quem pediu. De quem pediu → avisa o responsável (ou a função). */
    public static function comentar(array $t, string $texto, int $usuarioId): ?string
    {
        $texto = mb_substr(trim($texto), 0, 1000);
        if ($texto === '') {
            return 'Escreva o comentário.';
        }
        self::historico_((int) $t['id'], $texto, $usuarioId);
        $para = (int) $t['solicitante_id'] === $usuarioId
            ? ($t['responsavel_id'] ? [(int) $t['responsavel_id']] : self::pessoasDaFuncao((string) $t['funcao_slug']))
            : [(int) $t['solicitante_id'], (int) ($t['responsavel_id'] ?? 0)];
        NotificacaoService::avisar($para, 'mkt_comentario', 'Comentário: ' . $t['titulo'], '/marketing/tarefas/' . (int) $t['id'], ['texto' => $texto], $usuarioId);
        return null;
    }

    /** Encaminhar para outra função e/ou pessoa, mudar prazo */
    public static function encaminhar(array $t, array $d, int $usuarioId): ?string
    {
        if (!self::podeMexer()) {
            return 'Só o Marketing encaminha tarefas.';
        }
        $funcao = (string) ($d['funcao_slug'] ?? '');
        if (!Banco::valor('SELECT 1 FROM funcoes WHERE slug = ?', [$funcao])) {
            return 'Escolha a função.';
        }
        $resp = (int) ($d['responsavel_id'] ?? 0) ?: null;
        $prazo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['prazo'] ?? '')) ? $d['prazo'] : null;
        Banco::atualizar('mkt_tarefas', (int) $t['id'], ['funcao_slug' => $funcao, 'responsavel_id' => $resp, 'prazo' => $prazo, 'atualizado_em' => date('Y-m-d H:i:s')]);
        $nome = (string) Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$funcao]);
        $pessoa = $resp ? (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$resp]) : '';
        self::historico_((int) $t['id'], "Encaminhou para $nome" . ($pessoa ? " ($pessoa)" : '') . ($prazo ? ' · prazo ' . data_br($prazo) : '') . '.', $usuarioId);
        if ($funcao !== $t['funcao_slug'] || $resp !== ($t['responsavel_id'] ? (int) $t['responsavel_id'] : null)) {
            NotificacaoService::avisar($resp ? [$resp] : self::pessoasDaFuncao($funcao), 'mkt_tarefa', 'Tarefa para você: ' . $t['titulo'], '/marketing/tarefas/' . (int) $t['id'], [], $usuarioId);
        }
        return null;
    }

    /** Pessoas ativas de uma função */
    public static function pessoasDaFuncao(string $slug): array
    {
        return array_map('intval', array_column(Banco::todos('SELECT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id
            JOIN funcoes f ON f.id = uf.funcao_id WHERE f.slug = ? AND u.ativo = 1', [$slug]), 'id'));
    }

    /** Pessoas do Marketing (para "quem faz") */
    public static function pessoasDoMarketing(): array
    {
        $slugs = array_column(self::funcoes(), 'slug');
        if (!$slugs) {
            return [];
        }
        return Banco::todos("SELECT DISTINCT u.id, u.nome FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id
            WHERE u.ativo = 1 AND f.slug IN ('" . implode("','", array_map(fn($s) => preg_replace('/[^a-z0-9_]/', '', $s), $slugs)) . "') ORDER BY u.nome");
    }

    // ------------------------------------------------------------------

    private static function historico_(int $id, string $texto, ?int $usuarioId): void
    {
        Banco::inserir('mkt_tarefa_historico', ['tarefa_id' => $id, 'usuario_id' => $usuarioId, 'texto' => mb_substr($texto, 0, 1000)]);
    }

    /** Guarda o anexo (opcional) fora da pasta pública. Devolve [nome no disco, nome original, mime] */
    private static function guardarAnexo(array $arquivo): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null, null];
        }
        if ($arquivo['error'] === UPLOAD_ERR_INI_SIZE || $arquivo['error'] === UPLOAD_ERR_FORM_SIZE || (int) $arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new RuntimeException('O anexo passa de 15 MB.');
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new RuntimeException('Não foi possível receber o anexo. Tente de novo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::MIMES[$mime])) {
            throw new RuntimeException('Envie o anexo em PDF, JPG, PNG ou WEBP.');
        }
        if (!is_dir(self::pasta()) && !mkdir(self::pasta(), 0750, true)) {
            throw new RuntimeException('A pasta dos anexos não pôde ser criada.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], self::pasta() . '/' . $nome)) {
            throw new RuntimeException('Não foi possível guardar o anexo.');
        }
        return [$nome, mb_substr(basename((string) $arquivo['name']), 0, 200), $mime];
    }
}
