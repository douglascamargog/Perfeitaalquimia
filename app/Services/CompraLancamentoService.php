<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Integrations\Integracoes;
use RuntimeException;

/**
 * Compras lançadas (Financeiro › Compras): cada NF ou compra da empresa.
 *  - Lançar à mão: fornecedor, NF (com o arquivo), data e as linhas (item, quantidade, valor).
 *    Cada linha entra no estoque do item, atualiza o último preço do fornecedor e anexa a NF nos documentos do item.
 *  - Do Omie (notas de entrada): item já ligado entra sozinho (avisa quem compra, a Produção e a Diretoria);
 *    item que nunca foi ligado fica "pendente" até alguém dizer a qual item do One ele corresponde (uma vez só).
 *  - Orçamento da Qualidade recebido no Depósito também vira lançamento (origem "orcamento").
 */
class CompraLancamentoService
{
    public const ORIGENS = ['manual' => 'Lançada no One', 'omie' => 'Nota do Omie', 'orcamento' => 'Orçamento da Qualidade'];

    /**
     * Lança a compra. $d: fornecedor, cnpj, numero_nf, serie, emissao, observacoes, origem, omie_codigo, compra_id
     * $linhas: [['materia_prima_id' => id|null, 'omie_item' => ..., 'descricao' => ..., 'quantidade' => n, 'valor_unitario' => v], ...]
     * $nf: arquivo já guardado (ItemCompraService::guardar) ou null. Devolve o id do lançamento.
     */
    public static function lancar(array $d, array $linhas, ?array $nf, ?int $usuarioId, ?string $quando = null): int
    {
        $fornecedor = mb_substr(trim((string) ($d['fornecedor'] ?? '')), 0, 160);
        $emissao = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['emissao'] ?? '')) ? $d['emissao'] : date('Y-m-d');
        $linhas = array_values(array_filter($linhas, fn($l) => (float) ($l['quantidade'] ?? 0) > 0 && (!empty($l['materia_prima_id']) || !empty($l['omie_item']))));
        if ($fornecedor === '') {
            throw new RuntimeException('Informe o fornecedor.');
        }
        if (!$linhas) {
            throw new RuntimeException('Coloque pelo menos um item com quantidade.');
        }
        if ($emissao > date('Y-m-d')) {
            throw new RuntimeException('A data da compra não pode ser no futuro.');
        }
        $origem = array_key_exists($d['origem'] ?? '', self::ORIGENS) ? $d['origem'] : 'manual';
        if (!empty($d['omie_codigo']) && Banco::valor('SELECT 1 FROM compras_lancadas WHERE omie_codigo = ?', [$d['omie_codigo']])) {
            throw new RuntimeException('Esta nota do Omie já foi lançada.');
        }
        return Banco::transacao(function () use ($d, $linhas, $nf, $usuarioId, $quando, $fornecedor, $emissao, $origem) {
            $total = round(array_sum(array_map(fn($l) => (float) $l['quantidade'] * (float) ($l['valor_unitario'] ?? 0), $linhas)), 2);
            $pendente = (bool) array_filter($linhas, fn($l) => empty($l['materia_prima_id']));
            $id = Banco::inserir('compras_lancadas', array_filter([
                'origem' => $origem, 'situacao' => $pendente ? 'pendente' : 'lancado', 'numero_nf' => mb_substr(trim((string) ($d['numero_nf'] ?? '')), 0, 30) ?: null,
                'serie' => mb_substr(trim((string) ($d['serie'] ?? '')), 0, 10) ?: null, 'fornecedor' => $fornecedor,
                'cnpj' => mb_substr(trim((string) ($d['cnpj'] ?? '')), 0, 18) ?: null, 'emissao' => $emissao, 'valor_total' => $total,
                'omie_codigo' => $d['omie_codigo'] ?? null, 'compra_id' => $d['compra_id'] ?? null,
                'arquivo' => $nf['arquivo'] ?? null, 'arquivo_nome' => $nf['nome'] ?? null, 'arquivo_mime' => $nf['mime'] ?? null,
                'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 500) ?: null,
                'lancado_por' => $usuarioId, 'criado_em' => $quando,
            ], fn($v) => $v !== null));
            foreach ($linhas as $l) {
                $item = (int) ($l['materia_prima_id'] ?? 0) ?: null;
                $descricao = mb_substr(trim((string) ($l['descricao'] ?? '')), 0, 200) ?: (string) Banco::valor('SELECT nome FROM materias_primas WHERE id = ?', [$item]);
                $linha = Banco::inserir('compras_lancadas_itens', array_filter([
                    'lancamento_id' => $id, 'materia_prima_id' => $item, 'omie_item' => $l['omie_item'] ?? null, 'descricao' => $descricao ?: 'Item',
                    'quantidade' => round((float) $l['quantidade'], 3), 'valor_unitario' => round((float) ($l['valor_unitario'] ?? 0), 4),
                    'total' => round((float) $l['quantidade'] * (float) ($l['valor_unitario'] ?? 0), 2),
                ], fn($v) => $v !== null));
                if ($item) {
                    self::efetivarLinha($linha, $usuarioId, $quando);
                }
            }
            Auditoria::registrar('compra_lancada', $id, 'lancar', null, ['fornecedor' => $fornecedor, 'nf' => $d['numero_nf'] ?? null, 'total' => $total, 'origem' => $origem]);
            return $id;
        });
    }

    /** Uma linha ligada a um item: entra no estoque, atualiza o fornecedor do item e anexa a NF nos documentos */
    private static function efetivarLinha(int $linhaId, ?int $usuarioId, ?string $quando = null): void
    {
        $l = Banco::um('SELECT i.*, c.fornecedor, c.cnpj, c.numero_nf, c.emissao, c.arquivo, c.arquivo_nome, c.arquivo_mime, c.origem FROM compras_lancadas_itens i
            JOIN compras_lancadas c ON c.id = i.lancamento_id WHERE i.id = ? AND i.lancado = 0', [$linhaId]);
        if (!$l || !$l['materia_prima_id']) {
            return;
        }
        $item = (int) $l['materia_prima_id'];
        $fornecedor = ItemCompraService::fornecedorPorNome($item, $l['fornecedor'], $l['cnpj']);
        Banco::executar('UPDATE mp_fornecedores SET ultimo_preco = ?, ultima_compra_em = GREATEST(COALESCE(ultima_compra_em, ?), ?) WHERE id = ?',
            [(float) $l['valor_unitario'] ?: null, $l['emissao'], $l['emissao'], $fornecedor]);
        $obs = trim($l['fornecedor'] . ($l['origem'] === 'omie' ? ' · nota do Omie' : ''));
        ProducaoService::entrada($item, (float) $l['quantidade'], (float) $l['valor_unitario'], (string) $l['numero_nf'], $obs, $usuarioId,
            $quando ?? ($l['emissao'] < date('Y-m-d') ? $l['emissao'] . ' ' . date('H:i:s') : null), ['fornecedor_id' => $fornecedor, 'lancamento_id' => (int) $l['lancamento_id']]);
        if ($l['arquivo']) {
            ItemCompraService::anexarNf($item, ['arquivo' => $l['arquivo'], 'nome' => (string) $l['arquivo_nome'], 'mime' => (string) $l['arquivo_mime']],
                (int) $l['lancamento_id'], $fornecedor, $usuarioId, 'NF ' . ($l['numero_nf'] ?: '') . ' · ' . $l['fornecedor'] . ' · ' . data_br($l['emissao']));
        }
        Banco::executar('UPDATE compras_lancadas_itens SET lancado = 1 WHERE id = ?', [$linhaId]);
    }

    /** Item da nota do Omie que estava sem ligação: liga a um item do One (e as próximas notas entram sozinhas) */
    public static function ligarItemOmie(int $linhaId, int $itemId, ?int $usuarioId): void
    {
        $l = Banco::um('SELECT * FROM compras_lancadas_itens WHERE id = ? AND lancado = 0', [$linhaId]);
        if (!$l || !Banco::valor('SELECT 1 FROM materias_primas WHERE id = ?', [$itemId])) {
            throw new RuntimeException('Item não encontrado.');
        }
        Banco::transacao(function () use ($l, $itemId, $usuarioId) {
            if ($l['omie_item']) {
                Banco::executar('UPDATE materias_primas SET omie_codigo = ? WHERE id = ?', [$l['omie_item'], $itemId]);
                // Todas as linhas pendentes com o mesmo código do Omie passam para este item
                $mesmas = array_column(Banco::todos('SELECT id FROM compras_lancadas_itens WHERE omie_item = ? AND lancado = 0', [$l['omie_item']]), 'id');
            } else {
                $mesmas = [$l['id']];
            }
            foreach ($mesmas as $id) {
                Banco::executar('UPDATE compras_lancadas_itens SET materia_prima_id = ? WHERE id = ?', [$itemId, $id]);
                self::efetivarLinha((int) $id, $usuarioId);
            }
            Banco::executar("UPDATE compras_lancadas c SET situacao = 'lancado' WHERE situacao = 'pendente'
                AND NOT EXISTS (SELECT 1 FROM compras_lancadas_itens i WHERE i.lancamento_id = c.id AND i.lancado = 0)");
            Auditoria::registrar('materia_prima', $itemId, 'ligar_omie', null, ['omie_item' => $l['omie_item']]);
        });
    }

    /**
     * Busca as notas de entrada do Omie e lança as novas. Devolve [novas, pendentes].
     * Avisa quem compra (Financeiro e Qualidade), a Produção (se tiver matéria-prima/embalagem) e a Diretoria.
     */
    public static function sincronizarOmie(?int $usuarioId = null): array
    {
        $erp = Integracoes::erp();
        if (!method_exists($erp, 'listarNotasEntrada')) {
            return [0, 0];
        }
        // Busca a partir de uma semana antes da última nota do Omie já lançada (a mesma nota nunca entra duas vezes)
        $ultima = Banco::valor("SELECT MAX(emissao) FROM compras_lancadas WHERE origem = 'omie'");
        $desde = $ultima ? date('Y-m-d', strtotime("$ultima -7 days")) : null;
        $novas = 0;
        $pendentes = 0;
        foreach ($erp->listarNotasEntrada($desde) as $n) {
            if (Banco::valor('SELECT 1 FROM compras_lancadas WHERE omie_codigo = ?', [(string) $n['codigo']])) {
                continue;
            }
            $linhas = [];
            foreach ($n['itens'] as $i) {
                $item = Banco::valor('SELECT id FROM materias_primas WHERE omie_codigo = ?', [(string) $i['codigo']]);
                $linhas[] = ['materia_prima_id' => $item ? (int) $item : null, 'omie_item' => (string) $i['codigo'], 'descricao' => $i['descricao'],
                    'quantidade' => (float) $i['quantidade'], 'valor_unitario' => (float) $i['valor_unitario']];
            }
            $id = self::lancar(['fornecedor' => $n['fornecedor'], 'cnpj' => $n['cnpj'] ?? null, 'numero_nf' => $n['numero'], 'serie' => $n['serie'] ?? null,
                'emissao' => $n['emissao'], 'origem' => 'omie', 'omie_codigo' => (string) $n['codigo']], $linhas, null, $usuarioId);
            $novas++;
            $pendentes += (int) Banco::valor('SELECT COUNT(*) FROM compras_lancadas_itens WHERE lancamento_id = ? AND lancado = 0', [$id]);
            self::avisarEntrada($id, $usuarioId);
        }
        return [$novas, $pendentes];
    }

    /** "Entrou no estoque": quem compra, a Produção (matéria-prima/embalagem/rótulo) e a Diretoria */
    private static function avisarEntrada(int $id, ?int $quemFez): void
    {
        $c = Banco::um('SELECT * FROM compras_lancadas WHERE id = ?', [$id]);
        $itens = Banco::todos('SELECT i.descricao, i.lancado, m.tipo FROM compras_lancadas_itens i LEFT JOIN materias_primas m ON m.id = i.materia_prima_id WHERE i.lancamento_id = ?', [$id]);
        $entraram = array_filter($itens, fn($i) => $i['lancado']);
        $faltam = count($itens) - count($entraram);
        $titulo = $entraram ? 'Entrou no estoque: ' . implode(', ', array_slice(array_column($entraram, 'descricao'), 0, 3)) . (count($entraram) > 3 ? '…' : '')
            : 'Nota do Omie para ligar: ' . $c['fornecedor'];
        $texto = 'NF ' . ($c['numero_nf'] ?: '—') . ' · ' . $c['fornecedor'] . ' · ' . dinheiro((float) $c['valor_total']) . ($faltam ? " · $faltam item(ns) para ligar" : '');
        $link = '/financeiro/compras/' . $id;
        $compradores = array_merge(self::comPermissao('compras.lancar'), array_map('intval', array_column(Banco::todos(
            "SELECT uf.usuario_id FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE f.slug = ?", [(string) \App\Core\Config::get('compras.funcao', 'qualidade')]), 'usuario_id')));
        NotificacaoService::avisar($compradores, 'compra_entrada', $titulo, $link, ['texto' => $texto], $quemFez);
        if (array_filter($entraram, fn($i) => $i['tipo'] !== 'consumo')) {
            NotificacaoService::avisar(NotificacaoService::destinatarios('producao.gerenciar', 'producao'), 'compra_entrada', $titulo, $link, ['texto' => $texto], $quemFez);
        }
        if ($entraram && PHP_SAPI !== 'cli') {
            NotificacaoService::diretoria('compras', $titulo, $link, ['texto' => $texto], $quemFez);
        }
    }

    /** Pessoas ativas que têm a permissão (sem olhar o foco) */
    private static function comPermissao(string $permissao): array
    {
        $ids = [];
        foreach (Banco::todos('SELECT u.id, u.perfil_id, p.slug FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.ativo = 1') as $u) {
            if ($u['slug'] === 'admin') {
                continue; // admin tem tudo, mas não é quem compra
            }
            if (in_array($permissao, \App\Models\Usuario::permissoes((int) $u['perfil_id'], (int) $u['id']), true)) {
                $ids[] = (int) $u['id'];
            }
        }
        return $ids;
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public static function listar(array $f, int $limite = 200): array
    {
        $w = ['1 = 1'];
        $par = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['de'] ?? ''))) {
            $w[] = 'c.emissao >= ?';
            $par[] = $f['de'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['ate'] ?? ''))) {
            $w[] = 'c.emissao <= ?';
            $par[] = $f['ate'];
        }
        if (($f['q'] ?? '') !== '') {
            $w[] = '(c.fornecedor LIKE ? OR c.numero_nf LIKE ? OR EXISTS (SELECT 1 FROM compras_lancadas_itens i WHERE i.lancamento_id = c.id AND i.descricao LIKE ?))';
            array_push($par, "%{$f['q']}%", "{$f['q']}%", "%{$f['q']}%");
        }
        if (($f['situacao'] ?? '') === 'pendente') {
            $w[] = "c.situacao = 'pendente'";
        }
        return Banco::todos('SELECT c.*, u.nome AS lancado_por_nome, (SELECT COUNT(*) FROM compras_lancadas_itens i WHERE i.lancamento_id = c.id) AS qtd_itens,
                (SELECT GROUP_CONCAT(i.descricao ORDER BY i.id SEPARATOR \', \') FROM compras_lancadas_itens i WHERE i.lancamento_id = c.id) AS itens
            FROM compras_lancadas c LEFT JOIN usuarios u ON u.id = c.lancado_por WHERE ' . implode(' AND ', $w) . ' ORDER BY c.emissao DESC, c.id DESC LIMIT ' . (int) $limite, $par);
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT c.*, u.nome AS lancado_por_nome FROM compras_lancadas c LEFT JOIN usuarios u ON u.id = c.lancado_por WHERE c.id = ?', [$id]);
    }

    public static function itens(int $id): array
    {
        return Banco::todos('SELECT i.*, m.nome AS item, m.unidade, m.tipo FROM compras_lancadas_itens i LEFT JOIN materias_primas m ON m.id = i.materia_prima_id
            WHERE i.lancamento_id = ? ORDER BY i.id', [$id]);
    }

    /** Linhas de notas do Omie ainda sem item do One */
    public static function pendentes(): array
    {
        return Banco::todos("SELECT i.*, c.fornecedor, c.numero_nf, c.emissao FROM compras_lancadas_itens i JOIN compras_lancadas c ON c.id = i.lancamento_id
            WHERE i.lancado = 0 AND i.materia_prima_id IS NULL ORDER BY c.emissao DESC, i.id");
    }

    /** Total gasto por categoria (tipo ou categoria do item) no período */
    public static function porCategoria(string $de, string $ate): array
    {
        return Banco::todos("SELECT CASE WHEN m.tipo = 'consumo' THEN COALESCE(m.categoria, 'Outros') WHEN m.rotulo = 1 THEN 'Rótulos'
                WHEN m.tipo = 'embalagem' THEN 'Embalagens' ELSE 'Matérias-primas' END AS categoria, SUM(i.total) AS total
            FROM compras_lancadas_itens i JOIN compras_lancadas c ON c.id = i.lancamento_id JOIN materias_primas m ON m.id = i.materia_prima_id
            WHERE i.lancado = 1 AND c.emissao BETWEEN ? AND ?
            GROUP BY CASE WHEN m.tipo = 'consumo' THEN COALESCE(m.categoria, 'Outros') WHEN m.rotulo = 1 THEN 'Rótulos' WHEN m.tipo = 'embalagem' THEN 'Embalagens' ELSE 'Matérias-primas' END
            ORDER BY total DESC", [$de, $ate]);
    }
}
