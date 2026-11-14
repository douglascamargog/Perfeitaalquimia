<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Orçamentos e compras da Qualidade (matéria-prima, rótulos, embalagens e itens avulsos).
 *   rascunho → (cotações) → aprovacao (a Qualidade escolhe QUEM aprova) → aprovado | refazer | recusado
 *   aprovado → comprado ("Comprei", previsão de chegada) → recebido ("Chegou", no Depósito; matéria-prima entra no estoque).
 * Anexos das cotações ficam em storage/compras (fora da pasta pública) e só saem pelo controller.
 */
class CompraService
{
    public const TIPOS = ['materia_prima' => 'Matéria-prima', 'rotulo' => 'Rótulo', 'embalagem' => 'Embalagem', 'avulso' => 'Item avulso'];
    public const SITUACOES = [
        'rascunho'  => ['Montando o orçamento', 'cinza'],
        'aprovacao' => ['Esperando aprovação', 'laranja'],
        'refazer'   => ['Pedir outra cotação', 'laranja'],
        'aprovado'  => ['Aprovado · comprar', 'lima'],
        'recusado'  => ['Recusado', 'vermelho'],
        'comprado'  => ['Comprado · esperando chegar', 'azul'],
        'recebido'  => ['Recebido', 'verde'],
        'cancelado' => ['Cancelado', 'cinza'],
    ];
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    private const TAMANHO_MAXIMO = 15 * 1024 * 1024;

    public static function pasta(): string
    {
        return RAIZ . '/storage/compras';
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    /** $aba: abertos | aprovacao | concluidos | minhas (esperando a minha decisão) */
    public static function lista(string $aba, ?int $usuarioId = null): array
    {
        $w = match ($aba) {
            'aprovacao'  => "c.situacao = 'aprovacao'",
            'concluidos' => "c.situacao IN ('recebido', 'recusado', 'cancelado')",
            'minhas'     => "c.situacao = 'aprovacao' AND c.aprovador_id = " . (int) $usuarioId,
            default      => "c.situacao IN ('rascunho', 'refazer', 'aprovado', 'comprado')",
        };
        return Banco::todos("SELECT c.*, a.nome AS aprovador, s.nome AS solicitante, m.nome AS materia, dp.nome AS projeto,
                ct.fornecedor, ct.valor_total AS valor_escolhido,
                (SELECT COUNT(*) FROM compra_cotacoes x WHERE x.compra_id = c.id) AS cotacoes,
                (SELECT MIN(x.valor_total) FROM compra_cotacoes x WHERE x.compra_id = c.id) AS menor_valor
            FROM compras c LEFT JOIN usuarios a ON a.id = c.aprovador_id LEFT JOIN usuarios s ON s.id = c.solicitado_por
            LEFT JOIN materias_primas m ON m.id = c.materia_prima_id LEFT JOIN dp_projetos dp ON dp.id = c.dp_projeto_id
            LEFT JOIN compra_cotacoes ct ON ct.id = c.cotacao_id
            WHERE $w ORDER BY FIELD(c.situacao, 'refazer', 'aprovado', 'aprovacao', 'rascunho', 'comprado'), c.necessario_ate IS NULL, c.necessario_ate, c.id DESC LIMIT 300");
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT c.*, a.nome AS aprovador, s.nome AS solicitante, r.nome AS recebedor, m.nome AS materia, m.unidade AS mp_unidade,
                dp.nome AS projeto FROM compras c LEFT JOIN usuarios a ON a.id = c.aprovador_id LEFT JOIN usuarios s ON s.id = c.solicitado_por
                LEFT JOIN usuarios r ON r.id = c.recebido_por LEFT JOIN materias_primas m ON m.id = c.materia_prima_id
                LEFT JOIN dp_projetos dp ON dp.id = c.dp_projeto_id WHERE c.id = ?', [$id]);
    }

    public static function cotacoes(int $id): array
    {
        return Banco::todos('SELECT * FROM compra_cotacoes WHERE compra_id = ? ORDER BY valor_total, id', [$id]);
    }

    public static function historico(int $id): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario FROM compra_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.compra_id = ? ORDER BY h.id DESC', [$id]);
    }

    /** Preços pagos antes pela mesma matéria-prima (cotação escolhida das compras recebidas) */
    public static function precosAnteriores(?int $mpId, int $exceto = 0): array
    {
        if (!$mpId) {
            return [];
        }
        return Banco::todos("SELECT c.id, c.recebido_em, c.quantidade, c.unidade, ct.fornecedor, ct.valor_unitario FROM compras c
            JOIN compra_cotacoes ct ON ct.id = c.cotacao_id WHERE c.materia_prima_id = ? AND c.situacao = 'recebido' AND c.id <> ?
            ORDER BY c.recebido_em DESC LIMIT 5", [$mpId, $exceto]);
    }

    /** Sugestões: matérias-primas abaixo do mínimo e projetos do Desenvolvimento com a etapa de compras aberta (sem orçamento aberto) */
    public static function sugestoes(): array
    {
        $abertas = array_map('intval', array_column(Banco::todos("SELECT materia_prima_id FROM compras
            WHERE materia_prima_id IS NOT NULL AND situacao IN ('rascunho', 'aprovacao', 'refazer', 'aprovado', 'comprado')"), 'materia_prima_id'));
        $mps = array_values(array_filter(ProducaoService::materias(['situacao' => 'abaixo']), fn($m) => !in_array((int) $m['id'], $abertas, true)));
        $projetos = Banco::todos("SELECT p.id, p.nome FROM dp_projetos p JOIN dp_projeto_etapas pe ON pe.projeto_id = p.id AND pe.situacao = 'andamento'
            JOIN dp_etapas e ON e.id = pe.etapa_id AND e.checagem IN ('compras', 'grafica') WHERE p.situacao = 'andamento'
            AND NOT EXISTS (SELECT 1 FROM compras c WHERE c.dp_projeto_id = p.id AND c.situacao NOT IN ('recusado', 'cancelado'))
            GROUP BY p.id, p.nome ORDER BY p.id");
        return ['materias' => array_slice($mps, 0, 12), 'projetos' => $projetos];
    }

    /** Quem a Qualidade pode escolher para aprovar: todas as pessoas ativas, com Admin e quem aprova pedidos primeiro */
    public static function aprovadores(): array
    {
        return Banco::todos("SELECT u.id, u.nome, pf.nome AS perfil,
                (pf.slug IN ('admin', 'diretoria') OR EXISTS (SELECT 1 FROM perfil_permissoes pp WHERE pp.perfil_id = u.perfil_id AND pp.permissao = 'pedidos.aprovar')) AS sugerido
            FROM usuarios u JOIN perfis pf ON pf.id = u.perfil_id WHERE u.ativo = 1 ORDER BY sugerido DESC, u.nome");
    }

    /** Pode ver o orçamento: quem faz compras, quem vê compras, o aprovador escolhido e o Depósito (para receber) */
    public static function podeVer(array $c): bool
    {
        return Auth::pode('compras.solicitar') || Auth::pode('compras.ver') || (int) $c['aprovador_id'] === Auth::id()
            || (in_array($c['situacao'], ['comprado', 'recebido'], true) && self::podeReceber());
    }

    public static function podeReceber(): bool
    {
        return Auth::pode('deposito.operar') || Auth::pode('producao.gerenciar');
    }

    // ------------------------------------------------------------------
    // Montar o orçamento
    // ------------------------------------------------------------------

    public static function salvar(?int $id, array $d, int $usuarioId): int
    {
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'avulso';
        $mp = (int) ($d['materia_prima_id'] ?? 0) ?: null;
        if ($tipo === 'avulso') {
            $mp = null;
        }
        $materia = $mp ? Banco::um('SELECT id, nome, unidade FROM materias_primas WHERE id = ?', [$mp]) : null;
        $titulo = mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 160) ?: ($materia['nome'] ?? '');
        if ($titulo === '') {
            throw new RuntimeException('Diga o que vai comprar.');
        }
        $qtd = ProducaoService::numero((string) ($d['quantidade'] ?? '1'));
        if ($qtd <= 0) {
            throw new RuntimeException('A quantidade precisa ser maior que zero.');
        }
        $dados = [
            'tipo' => $tipo, 'titulo' => $titulo, 'materia_prima_id' => $materia ? (int) $materia['id'] : null,
            'dp_projeto_id' => (int) ($d['dp_projeto_id'] ?? 0) ?: null, 'quantidade' => $qtd,
            'unidade' => $materia['unidade'] ?? (mb_substr(trim((string) ($d['unidade'] ?? 'un')), 0, 10) ?: 'un'),
            'motivo' => trim((string) ($d['motivo'] ?? '')) ?: null,
            'necessario_ate' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['necessario_ate'] ?? '')) ? $d['necessario_ate'] : null,
        ];
        if ($id) {
            $c = self::exigir($id, ['rascunho', 'refazer', 'aprovacao']);
            Banco::atualizar('compras', $id, $dados);
            self::registrar($id, $usuarioId, 'Dados do orçamento alterados.');
            Auditoria::registrar('compra', $id, 'editar', array_intersect_key($c, $dados), $dados);
            return $id;
        }
        $novo = Banco::inserir('compras', $dados + ['solicitado_por' => $usuarioId]);
        self::registrar($novo, $usuarioId, 'Orçamento criado.');
        Auditoria::registrar('compra', $novo, 'criar', null, $dados);
        return $novo;
    }

    /** Cotação (fornecedor, preço, frete, prazo, condição, anexo opcional) */
    public static function adicionarCotacao(int $id, array $d, array $arquivo, int $usuarioId): int
    {
        $c = self::exigir($id, ['rascunho', 'refazer']);
        $fornecedor = mb_substr(trim((string) ($d['fornecedor'] ?? '')), 0, 160);
        if ($fornecedor === '') {
            throw new RuntimeException('Informe o fornecedor.');
        }
        $unit = ProducaoService::numero((string) ($d['valor_unitario'] ?? '0'));
        $frete = ProducaoService::numero((string) ($d['frete'] ?? '0'));
        if ($unit <= 0) {
            throw new RuntimeException('Informe o valor unitário.');
        }
        $total = round($unit * (float) $c['quantidade'] + $frete, 2);
        [$nome, $original, $mime] = self::guardarAnexo($arquivo);
        $cot = Banco::inserir('compra_cotacoes', [
            'compra_id' => $id, 'fornecedor' => $fornecedor, 'valor_unitario' => $unit, 'frete' => $frete, 'valor_total' => $total,
            'prazo_dias' => ($d['prazo_dias'] ?? '') !== '' ? max(0, (int) $d['prazo_dias']) : null,
            'condicao' => mb_substr(trim((string) ($d['condicao'] ?? '')), 0, 120) ?: null,
            'observacao' => mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 500) ?: null,
            'arquivo' => $nome, 'arquivo_nome' => $original, 'arquivo_mime' => $mime, 'criado_por' => $usuarioId,
        ]);
        self::registrar($id, $usuarioId, "Cotação de $fornecedor: " . dinheiro($total) . '.');
        return $cot;
    }

    public static function removerCotacao(int $id, int $cotacaoId, int $usuarioId): void
    {
        self::exigir($id, ['rascunho', 'refazer']);
        $cot = Banco::um('SELECT * FROM compra_cotacoes WHERE id = ? AND compra_id = ?', [$cotacaoId, $id]);
        if ($cot) {
            Banco::executar('DELETE FROM compra_cotacoes WHERE id = ?', [$cotacaoId]);
            Banco::executar('UPDATE compras SET cotacao_id = NULL WHERE id = ? AND cotacao_id = ?', [$id, $cotacaoId]);
            self::registrar($id, $usuarioId, 'Cotação de ' . $cot['fornecedor'] . ' removida.');
        }
    }

    /** Escolhe a cotação e manda para a pessoa que vai aprovar (escolhida pela Qualidade) */
    public static function enviar(int $id, int $cotacaoId, int $aprovadorId, string $motivoEscolha, int $usuarioId): void
    {
        $c = self::exigir($id, ['rascunho', 'refazer']);
        $cot = Banco::um('SELECT * FROM compra_cotacoes WHERE id = ? AND compra_id = ?', [$cotacaoId, $id]);
        if (!$cot) {
            throw new RuntimeException('Escolha uma das cotações.');
        }
        $aprovador = Banco::um('SELECT id, nome FROM usuarios WHERE id = ? AND ativo = 1', [$aprovadorId]);
        if (!$aprovador) {
            throw new RuntimeException('Escolha quem vai aprovar.');
        }
        $menor = (float) Banco::valor('SELECT MIN(valor_total) FROM compra_cotacoes WHERE compra_id = ?', [$id]);
        if ((float) $cot['valor_total'] > $menor + 0.004 && mb_strlen(trim($motivoEscolha)) < 3) {
            throw new RuntimeException('A cotação escolhida não é a mais barata: escreva o motivo da escolha.');
        }
        Banco::atualizar('compras', $id, ['situacao' => 'aprovacao', 'cotacao_id' => $cotacaoId, 'aprovador_id' => $aprovadorId, 'enviado_em' => date('Y-m-d H:i:s'),
            'escolha_motivo' => mb_substr(trim($motivoEscolha), 0, 300) ?: null, 'decidido_em' => null, 'decisao_comentario' => null]);
        self::registrar($id, $usuarioId, 'Enviado para aprovação de ' . $aprovador['nome'] . ' · ' . $cot['fornecedor'] . ' ' . dinheiro((float) $cot['valor_total']) . '.');
        Auditoria::registrar('compra', $id, 'enviar', ['situacao' => $c['situacao']], ['situacao' => 'aprovacao', 'aprovador' => $aprovadorId]);
        NotificacaoService::avisar([$aprovadorId], 'compra_aprovar', 'Orçamento para aprovar: ' . $c['titulo'], '/compras/' . $id,
            ['texto' => $cot['fornecedor'] . ' · ' . dinheiro((float) $cot['valor_total'])], $usuarioId);
    }

    /** Decisão de quem foi escolhido (ou do Admin): aprovado | refazer | recusado */
    public static function decidir(int $id, string $decisao, string $comentario, int $usuarioId): string
    {
        $c = self::exigir($id, ['aprovacao']);
        if ((int) $c['aprovador_id'] !== $usuarioId && !Auth::pode('*')) {
            throw new RuntimeException('Este orçamento foi enviado para ' . ($c['aprovador'] ?? 'outra pessoa') . ' aprovar.');
        }
        if (!in_array($decisao, ['aprovado', 'refazer', 'recusado'], true)) {
            throw new RuntimeException('Decisão inválida.');
        }
        if ($decisao !== 'aprovado' && mb_strlen(trim($comentario)) < 3) {
            throw new RuntimeException('Escreva o motivo (o que precisa mudar ou por que foi recusado).');
        }
        Banco::atualizar('compras', $id, ['situacao' => $decisao, 'decidido_em' => date('Y-m-d H:i:s'), 'decisao_comentario' => mb_substr(trim($comentario), 0, 500) ?: null]);
        $textos = ['aprovado' => 'Aprovado', 'refazer' => 'Pediu outra cotação', 'recusado' => 'Recusado'];
        self::registrar($id, $usuarioId, $textos[$decisao] . (trim($comentario) !== '' ? ': ' . trim($comentario) : '.'));
        Auditoria::registrar('compra', $id, 'decidir', ['situacao' => 'aprovacao'], ['situacao' => $decisao, 'comentario' => $comentario]);
        Banco::executar("UPDATE notificacoes SET lida_em = NOW() WHERE tipo = 'compra_aprovar' AND link = ? AND lida_em IS NULL", ['/compras/' . $id]);
        NotificacaoService::avisar([(int) $c['solicitado_por']], 'compra_decidida', $textos[$decisao] . ': ' . $c['titulo'], '/compras/' . $id,
            ['texto' => trim($comentario) ?: null], $usuarioId);
        return ['aprovado' => 'Orçamento aprovado. A Qualidade foi avisada para comprar.', 'refazer' => 'Pedido de outra cotação enviado para a Qualidade.',
            'recusado' => 'Orçamento recusado.'][$decisao];
    }

    public static function comprar(int $id, string $previsto, int $usuarioId): void
    {
        self::exigir($id, ['aprovado']);
        $previsto = preg_match('/^\d{4}-\d{2}-\d{2}$/', $previsto) ? $previsto : null;
        Banco::atualizar('compras', $id, ['situacao' => 'comprado', 'comprado_em' => date('Y-m-d H:i:s'), 'previsto_em' => $previsto]);
        self::registrar($id, $usuarioId, 'Compra feita' . ($previsto ? ' · chega até ' . data_br($previsto) : '') . '.');
        Auditoria::registrar('compra', $id, 'comprar', ['situacao' => 'aprovado'], ['situacao' => 'comprado', 'previsto' => $previsto]);
    }

    /** Chegou (Depósito ou Produção): matéria-prima/rótulo ligado dá entrada no estoque com o valor da cotação escolhida */
    public static function receber(int $id, float $quantidade, string $observacao, int $usuarioId): void
    {
        $c = self::exigir($id, ['comprado']);
        if ($quantidade <= 0) {
            throw new RuntimeException('Informe a quantidade que chegou.');
        }
        Banco::transacao(function () use ($c, $quantidade, $observacao, $usuarioId) {
            $cot = $c['cotacao_id'] ? Banco::um('SELECT * FROM compra_cotacoes WHERE id = ?', [$c['cotacao_id']]) : null;
            if ($c['materia_prima_id']) {
                ProducaoService::entrada((int) $c['materia_prima_id'], $quantidade, (float) ($cot['valor_unitario'] ?? 0), '',
                    trim('Compra #' . $c['id'] . ($cot ? ' · ' . $cot['fornecedor'] : '') . ($observacao !== '' ? ' · ' . $observacao : '')), $usuarioId);
            }
            Banco::atualizar('compras', (int) $c['id'], ['situacao' => 'recebido', 'recebido_em' => date('Y-m-d H:i:s'), 'recebido_por' => $usuarioId, 'recebido_qtd' => $quantidade]);
            $texto = 'Chegou: ' . ProducaoService::qtd($quantidade, (string) $c['unidade']) . ($c['materia_prima_id'] ? ' (entrou no estoque)' : '') . ($observacao !== '' ? ' · ' . $observacao : '') . '.';
            self::registrar((int) $c['id'], $usuarioId, $texto);
            Auditoria::registrar('compra', (int) $c['id'], 'receber', ['situacao' => 'comprado'], ['situacao' => 'recebido', 'quantidade' => $quantidade]);
            if ((int) $c['solicitado_por'] !== $usuarioId) {
                NotificacaoService::avisar([(int) $c['solicitado_por']], 'compra_recebida', 'Chegou: ' . $c['titulo'], '/compras/' . $c['id'], ['texto' => $texto], $usuarioId);
            }
        });
    }

    public static function cancelar(int $id, string $motivo, int $usuarioId): void
    {
        $c = self::exigir($id, ['rascunho', 'refazer', 'aprovacao', 'aprovado']);
        Banco::atualizar('compras', $id, ['situacao' => 'cancelado']);
        self::registrar($id, $usuarioId, 'Cancelado' . (trim($motivo) !== '' ? ': ' . trim($motivo) : '.'));
        Auditoria::registrar('compra', $id, 'cancelar', ['situacao' => $c['situacao']], ['situacao' => 'cancelado']);
    }

    /** Anexo de cotação para baixar (o controller confere quem pode ver) */
    public static function anexo(int $cotacaoId): ?array
    {
        $cot = Banco::um('SELECT * FROM compra_cotacoes WHERE id = ? AND arquivo IS NOT NULL', [$cotacaoId]);
        if (!$cot) {
            return null;
        }
        $caminho = self::pasta() . '/' . basename($cot['arquivo']);
        return is_file($caminho) ? $cot + ['caminho' => $caminho] : null;
    }

    // ------------------------------------------------------------------
    // Contadores (menu, sino, Início)
    // ------------------------------------------------------------------

    public static function qtdParaAprovar(int $usuarioId): int
    {
        return (int) Banco::valor("SELECT COUNT(*) FROM compras WHERE situacao = 'aprovacao' AND aprovador_id = ?", [$usuarioId]);
    }

    /** Para a Qualidade: aprovados para comprar + pedidos de outra cotação */
    public static function qtdParaAgir(): int
    {
        return (int) Banco::valor("SELECT COUNT(*) FROM compras WHERE situacao IN ('aprovado', 'refazer')");
    }

    // ------------------------------------------------------------------

    private static function exigir(int $id, array $situacoes): array
    {
        $c = self::buscar($id);
        if (!$c) {
            throw new RuntimeException('Orçamento não encontrado.');
        }
        if (!in_array($c['situacao'], $situacoes, true)) {
            throw new RuntimeException('Este orçamento está em "' . (self::SITUACOES[$c['situacao']][0] ?? $c['situacao']) . '" e não pode ser alterado assim.');
        }
        return $c;
    }

    private static function registrar(int $id, ?int $usuarioId, string $texto): void
    {
        Banco::inserir('compra_historico', ['compra_id' => $id, 'usuario_id' => $usuarioId, 'texto' => mb_substr($texto, 0, 600)]);
    }

    /** Guarda o anexo (opcional). Devolve [nome no disco, nome original, mime] ou [null, null, null] */
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
            throw new RuntimeException('Envie o orçamento em PDF, JPG ou PNG.');
        }
        if (!is_dir(self::pasta()) && !mkdir(self::pasta(), 0750, true)) {
            throw new RuntimeException('A pasta dos orçamentos não pôde ser criada.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], self::pasta() . '/' . $nome)) {
            throw new RuntimeException('Não foi possível guardar o anexo.');
        }
        return [$nome, mb_substr(basename((string) $arquivo['name']), 0, 200), $mime];
    }
}
