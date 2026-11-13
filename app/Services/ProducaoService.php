<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use RuntimeException;

/**
 * Produção e matérias-primas — TODAS as regras e TODA mudança de estoque da fábrica passam por aqui.
 *
 *  - Matéria-prima: entrada de compra (soma), consumo (ordem de produção) e ajuste de inventário.
 *  - Ficha técnica: quanto de cada matéria-prima vai em 1 unidade do produto.
 *  - Ordem de produção: concluir = baixa das matérias-primas + soma do produto acabado (tudo junto ou nada).
 *  - Estoque acabado: soma na produção, baixa na venda faturada (configurável) e ajuste de inventário.
 *  - Alertas: matéria-prima abaixo do mínimo / acabando (pelo consumo médio) e reabastecimento do acabado.
 *
 * Se um dia o plano do Omie controlar a produção, é este Service que passa a falar com o Omie.
 * Erros de regra viram RuntimeException com a mensagem para mostrar na tela.
 */
class ProducaoService
{
    public const SITUACOES = [
        'planejada' => ['Planejada', 'laranja'],
        'concluida' => ['Concluída', 'verde'],
        'cancelada' => ['Cancelada', 'cinza'],
    ];
    public const TIPOS_MP = ['entrada' => 'Entrada de compra', 'consumo' => 'Consumo (produção)', 'ajuste' => 'Ajuste de inventário'];
    public const TIPOS_ACABADO = ['producao' => 'Produção', 'venda' => 'Venda faturada', 'ajuste' => 'Ajuste de inventário'];

    // ------------------------------------------------------------------
    // Configurações
    // ------------------------------------------------------------------

    public static function unidades(): array
    {
        return Config::get('producao.unidades', ['kg', 'g', 'L', 'ml', 'un']) ?: ['un'];
    }

    private static function diasConsumo(): int
    {
        return max(7, (int) Config::get('producao.dias_consumo', 30));
    }

    /** Número com vírgula ("1.234,5" ou "0,25") → float */
    public static function numero(string $texto): float
    {
        $t = trim(str_replace(' ', '', $texto));
        if (str_contains($t, ',')) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        }
        return is_numeric($t) ? (float) $t : 0.0;
    }

    /** Quantidade para mostrar: até 3 casas, sem zeros sobrando (1,5 kg · 60 un) */
    public static function qtd(float $valor, string $unidade = ''): string
    {
        $txt = rtrim(rtrim(number_format($valor, 3, ',', '.'), '0'), ',');
        $txt = $txt === '-0' ? '0' : $txt;
        return $unidade !== '' ? "$txt $unidade" : $txt;
    }

    // ------------------------------------------------------------------
    // Matérias-primas
    // ------------------------------------------------------------------

    /**
     * Lista com consumo médio por dia e previsão de quando acaba.
     * $f: q (busca), situacao (abaixo | acabando | ''), inativas (bool)
     */
    public static function materias(array $f = []): array
    {
        $w = [($f['inativas'] ?? false) ? '1 = 1' : 'm.ativo = 1'];
        $par = [self::diasConsumo()];
        if (($f['q'] ?? '') !== '') {
            $w[] = '(m.nome LIKE ? OR m.codigo LIKE ? OR m.fornecedor LIKE ?)';
            array_push($par, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        $lista = Banco::todos('SELECT m.*, COALESCE(c.consumo, 0) AS consumo_periodo,
                (SELECT COUNT(*) FROM ficha_tecnica ft WHERE ft.materia_prima_id = m.id) AS produtos
            FROM materias_primas m
            LEFT JOIN (SELECT materia_prima_id, -SUM(quantidade) AS consumo FROM mp_movimentos
                       WHERE tipo = \'consumo\' AND criado_em >= NOW() - INTERVAL ? DAY GROUP BY materia_prima_id) c ON c.materia_prima_id = m.id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY m.nome', $par);
        $lista = array_map([self::class, 'comPrevisao'], $lista);
        $situacao = $f['situacao'] ?? '';
        if ($situacao !== '') {
            $lista = array_values(array_filter($lista, fn($m) => $situacao === 'abaixo' ? $m['abaixo'] : ($m['abaixo'] || $m['acabando'])));
        }
        return $lista;
    }

    /** Acrescenta consumo/dia, dias restantes, data prevista e os alertas */
    private static function comPrevisao(array $m): array
    {
        $m['estoque'] = (float) $m['estoque'];
        $m['estoque_minimo'] = (float) $m['estoque_minimo'];
        $m['consumo_dia'] = (float) $m['consumo_periodo'] / self::diasConsumo();
        $m['dias_restantes'] = $m['consumo_dia'] > 0 ? (int) floor($m['estoque'] / $m['consumo_dia']) : null;
        $m['acaba_em'] = $m['dias_restantes'] !== null ? date('Y-m-d', strtotime("+{$m['dias_restantes']} days")) : null;
        $m['abaixo'] = $m['estoque_minimo'] > 0 && $m['estoque'] < $m['estoque_minimo'];
        $m['acabando'] = $m['dias_restantes'] !== null && $m['dias_restantes'] < (int) Config::get('producao.dias_alerta', 15);
        return $m;
    }

    public static function materia(int $id): ?array
    {
        $m = Banco::um('SELECT m.*, COALESCE((SELECT -SUM(quantidade) FROM mp_movimentos WHERE materia_prima_id = m.id AND tipo = \'consumo\'
                AND criado_em >= NOW() - INTERVAL ? DAY), 0) AS consumo_periodo FROM materias_primas m WHERE m.id = ?', [self::diasConsumo(), $id]);
        return $m ? self::comPrevisao($m) : null;
    }

    /** Valida e grava o cadastro (sem mexer no estoque: estoque só muda por entrada ou ajuste) */
    public static function salvarMateria(?int $id, array $d, ?int $usuarioId): int
    {
        $dados = [
            'nome'           => mb_substr(trim((string) ($d['nome'] ?? '')), 0, 160),
            'codigo'         => mb_substr(trim((string) ($d['codigo'] ?? '')), 0, 40) ?: null,
            'unidade'        => (string) ($d['unidade'] ?? ''),
            'estoque_minimo' => max(0, self::numero((string) ($d['estoque_minimo'] ?? '0'))),
            'fornecedor'     => mb_substr(trim((string) ($d['fornecedor'] ?? '')), 0, 160) ?: null,
            'custo_unitario' => max(0, self::numero((string) ($d['custo_unitario'] ?? '0'))),
            'observacoes'    => trim((string) ($d['observacoes'] ?? '')) ?: null,
            'ativo'          => !empty($d['ativo']) ? 1 : 0,
        ];
        if ($dados['nome'] === '') {
            throw new RuntimeException('Informe o nome da matéria-prima.');
        }
        if (!in_array($dados['unidade'], self::unidades(), true)) {
            throw new RuntimeException('Escolha uma unidade da lista.');
        }
        $repetida = Banco::valor('SELECT id FROM materias_primas WHERE nome = ? AND id <> ?', [$dados['nome'], (int) $id]);
        if ($repetida) {
            throw new RuntimeException('Já existe uma matéria-prima com esse nome.');
        }
        if ($id) {
            $antes = Banco::um('SELECT * FROM materias_primas WHERE id = ?', [$id]);
            if (!$antes) {
                throw new RuntimeException('Matéria-prima não encontrada.');
            }
            if ($antes['unidade'] !== $dados['unidade'] && Banco::valor('SELECT 1 FROM mp_movimentos WHERE materia_prima_id = ? LIMIT 1', [$id])) {
                throw new RuntimeException('A unidade não pode mudar depois que a matéria-prima já teve movimentos (as quantidades ficariam erradas).');
            }
            Banco::atualizar('materias_primas', $id, $dados);
            Auditoria::registrar('materia_prima', $id, 'editar', $antes, $dados);
            return $id;
        }
        $novo = Banco::inserir('materias_primas', $dados);
        Auditoria::registrar('materia_prima', $novo, 'criar', null, $dados);
        // Estoque inicial (opcional) entra como ajuste de inventário, para ficar no histórico
        $inicial = self::numero((string) ($d['estoque_inicial'] ?? '0'));
        if ($inicial > 0) {
            self::movimentarMp($novo, 'ajuste', $inicial, ['observacao' => 'Estoque inicial do cadastro', 'usuario_id' => $usuarioId]);
        }
        return $novo;
    }

    /** Entrada de compra: soma ao estoque e atualiza o último custo */
    public static function entrada(int $mpId, float $quantidade, float $custoUnitario, string $nota, string $observacao, ?int $usuarioId, ?string $quando = null): void
    {
        if ($quantidade <= 0) {
            throw new RuntimeException('A quantidade da compra precisa ser maior que zero.');
        }
        Banco::transacao(function () use ($mpId, $quantidade, $custoUnitario, $nota, $observacao, $usuarioId, $quando) {
            self::movimentarMp($mpId, 'entrada', $quantidade, ['custo_unitario' => $custoUnitario > 0 ? $custoUnitario : null,
                'referencia' => $nota !== '' ? mb_substr("NF $nota", 0, 80) : null, 'observacao' => $observacao, 'usuario_id' => $usuarioId], $quando);
            if ($custoUnitario > 0) {
                Banco::executar('UPDATE materias_primas SET custo_unitario = ? WHERE id = ?', [$custoUnitario, $mpId]);
            }
            if ($quando === null) {
                Auditoria::registrar('materia_prima', $mpId, 'entrada', null, ['quantidade' => $quantidade, 'custo_unitario' => $custoUnitario, 'nota' => $nota]);
            }
        });
    }

    /** Ajuste de inventário: informa a contagem física; o sistema grava a diferença (motivo obrigatório) */
    public static function ajustar(int $mpId, float $contagem, string $motivo, ?int $usuarioId, ?string $quando = null): float
    {
        if ($contagem < 0) {
            throw new RuntimeException('A contagem não pode ser negativa.');
        }
        if (mb_strlen(trim($motivo)) < 3) {
            throw new RuntimeException('Escreva o motivo do ajuste (ex.: "contagem de inventário", "perda", "vencido").');
        }
        return Banco::transacao(function () use ($mpId, $contagem, $motivo, $usuarioId, $quando) {
            $atual = (float) Banco::valor('SELECT estoque FROM materias_primas WHERE id = ? FOR UPDATE', [$mpId]);
            $diferenca = round($contagem - $atual, 3);
            if (abs($diferenca) < 0.0005) {
                return 0.0;
            }
            self::movimentarMp($mpId, 'ajuste', $diferenca, ['observacao' => $motivo, 'usuario_id' => $usuarioId], $quando);
            Auditoria::registrar('materia_prima', $mpId, 'ajuste', ['estoque' => $atual], ['estoque' => $contagem, 'motivo' => $motivo]);
            return $diferenca;
        });
    }

    public static function movimentosMp(int $mpId, int $limite = 100): array
    {
        return Banco::todos('SELECT mv.*, u.nome AS usuario FROM mp_movimentos mv LEFT JOIN usuarios u ON u.id = mv.usuario_id
            WHERE mv.materia_prima_id = ? ORDER BY mv.criado_em DESC, mv.id DESC LIMIT ' . $limite, [$mpId]);
    }

    /** Único ponto que muda o estoque de matéria-prima (trava a linha, soma e grava o movimento com o saldo) */
    private static function movimentarMp(int $mpId, string $tipo, float $quantidade, array $extra = [], ?string $quando = null): float
    {
        return Banco::transacao(function () use ($mpId, $tipo, $quantidade, $extra, $quando) {
            $atual = Banco::valor('SELECT estoque FROM materias_primas WHERE id = ? FOR UPDATE', [$mpId]);
            if ($atual === null || $atual === false) {
                throw new RuntimeException('Matéria-prima não encontrada.');
            }
            $saldo = round((float) $atual + $quantidade, 3);
            Banco::executar('UPDATE materias_primas SET estoque = ? WHERE id = ?', [$saldo, $mpId]);
            Banco::inserir('mp_movimentos', array_filter([
                'materia_prima_id' => $mpId, 'tipo' => $tipo, 'quantidade' => round($quantidade, 3), 'saldo' => $saldo,
                'custo_unitario' => $extra['custo_unitario'] ?? null, 'referencia' => $extra['referencia'] ?? null,
                'ordem_id' => $extra['ordem_id'] ?? null, 'observacao' => isset($extra['observacao']) ? mb_substr((string) $extra['observacao'], 0, 255) : null,
                'usuario_id' => $extra['usuario_id'] ?? null, 'criado_em' => $quando,
            ], fn($v) => $v !== null && $v !== ''));
            return $saldo;
        });
    }

    // ------------------------------------------------------------------
    // Ficha técnica
    // ------------------------------------------------------------------

    /** Produtos com a quantidade de itens na ficha e o custo por unidade */
    public static function produtosComFicha(string $busca = '', string $filtro = ''): array
    {
        $w = ['p.ativo = 1'];
        $par = [];
        if ($busca !== '') {
            $w[] = '(p.nome LIKE ? OR p.codigo LIKE ?)';
            array_push($par, "%$busca%", "$busca%");
        }
        if ($filtro === 'com') {
            $w[] = 'f.itens > 0';
        } elseif ($filtro === 'sem') {
            $w[] = 'f.itens IS NULL';
        }
        return Banco::todos('SELECT p.id, p.codigo, p.nome, p.embalagem, c.nome AS categoria, COALESCE(f.itens, 0) AS itens, COALESCE(f.custo, 0) AS custo
            FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN (SELECT ft.produto_id, COUNT(*) AS itens, SUM(ft.quantidade * m.custo_unitario) AS custo
                       FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id GROUP BY ft.produto_id) f ON f.produto_id = p.id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY (f.itens IS NULL), p.nome', $par);
    }

    /** Linhas da ficha de um produto, com o custo de cada uma (pelo último custo) */
    public static function ficha(int $produtoId): array
    {
        return Banco::todos('SELECT ft.materia_prima_id, ft.quantidade, m.nome, m.unidade, m.custo_unitario, m.estoque, m.ativo,
                ROUND(ft.quantidade * m.custo_unitario, 4) AS custo
            FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id WHERE ft.produto_id = ? ORDER BY m.nome', [$produtoId]);
    }

    public static function custoUnitario(int $produtoId): float
    {
        return (float) Banco::valor('SELECT COALESCE(SUM(ft.quantidade * m.custo_unitario), 0) FROM ficha_tecnica ft
            JOIN materias_primas m ON m.id = ft.materia_prima_id WHERE ft.produto_id = ?', [$produtoId]);
    }

    /** Grava a ficha inteira. $linhas = [materia_prima_id => quantidade por unidade]; quantidade 0 = tira da ficha */
    public static function salvarFicha(int $produtoId, array $linhas): int
    {
        if (!Banco::valor('SELECT 1 FROM produtos WHERE id = ?', [$produtoId])) {
            throw new RuntimeException('Produto não encontrado.');
        }
        $limpas = [];
        foreach ($linhas as $mp => $q) {
            $q = is_string($q) ? self::numero($q) : (float) $q;
            if ((int) $mp > 0 && $q > 0) {
                $limpas[(int) $mp] = round($q, 5);
            }
        }
        if ($limpas) {
            $existem = Banco::valor('SELECT COUNT(*) FROM materias_primas WHERE id IN (' . implode(',', array_keys($limpas)) . ')');
            if ((int) $existem !== count($limpas)) {
                throw new RuntimeException('Alguma matéria-prima da ficha não existe mais. Recarregue a tela.');
            }
        }
        return Banco::transacao(function () use ($produtoId, $limpas) {
            $antes = array_column(self::ficha($produtoId), 'quantidade', 'materia_prima_id');
            Banco::executar('DELETE FROM ficha_tecnica WHERE produto_id = ?', [$produtoId]);
            foreach ($limpas as $mp => $q) {
                Banco::inserir('ficha_tecnica', ['produto_id' => $produtoId, 'materia_prima_id' => $mp, 'quantidade' => $q]);
            }
            Auditoria::registrar('ficha_tecnica', $produtoId, 'salvar', ['itens' => $antes], ['itens' => $limpas]);
            return count($limpas);
        });
    }

    // ------------------------------------------------------------------
    // Ordens de produção
    // ------------------------------------------------------------------

    /**
     * Prévia do consumo: para cada matéria-prima da ficha, quanto vai usar, quanto tem e quanto falta.
     * Retorna ['linhas' => [...], 'ok' => bool, 'custo' => float, 'sem_ficha' => bool]
     */
    public static function previa(int $produtoId, int $quantidade): array
    {
        $linhas = [];
        $ok = true;
        $custo = 0.0;
        foreach (self::ficha($produtoId) as $l) {
            $precisa = round((float) $l['quantidade'] * $quantidade, 3);
            $falta = max(0, round($precisa - (float) $l['estoque'], 3));
            $ok = $ok && $falta <= 0;
            $custo += $precisa * (float) $l['custo_unitario'];
            $linhas[] = ['materia_prima_id' => (int) $l['materia_prima_id'], 'nome' => $l['nome'], 'unidade' => $l['unidade'],
                'por_unidade' => (float) $l['quantidade'], 'precisa' => $precisa, 'estoque' => (float) $l['estoque'], 'falta' => $falta];
        }
        return ['linhas' => $linhas, 'ok' => $ok && $linhas !== [], 'custo' => round($custo, 2), 'sem_ficha' => $linhas === []];
    }

    public static function criarOrdem(array $d, ?int $usuarioId, string $origem = 'manual', ?string $quando = null): int
    {
        $produtoId = (int) ($d['produto_id'] ?? 0);
        $qtd = (int) ($d['quantidade'] ?? 0);
        if (!Banco::valor('SELECT 1 FROM produtos WHERE id = ? AND ativo = 1', [$produtoId])) {
            throw new RuntimeException('Escolha o produto.');
        }
        if ($qtd < 1 || $qtd > 1000000) {
            throw new RuntimeException('Informe quantas unidades vão ser fabricadas.');
        }
        if (!Banco::valor('SELECT 1 FROM ficha_tecnica WHERE produto_id = ? LIMIT 1', [$produtoId])) {
            throw new RuntimeException('Este produto ainda não tem ficha técnica. Cadastre a ficha (o que vai em cada unidade) antes de produzir.');
        }
        $data = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $dados = array_filter([
            'produto_id' => $produtoId, 'quantidade' => $qtd, 'origem' => $origem === 'reabastecimento' ? 'reabastecimento' : 'manual',
            'lote' => mb_substr(trim((string) ($d['lote'] ?? '')), 0, 40) ?: null, 'validade' => $data($d['validade'] ?? null),
            'previsto_para' => $data($d['previsto_para'] ?? null), 'observacoes' => trim((string) ($d['observacoes'] ?? '')) ?: null,
            'criado_por' => $usuarioId, 'criado_em' => $quando,
        ], fn($v) => $v !== null);
        $id = Banco::inserir('ordens_producao', $dados);
        if ($quando === null) {
            Auditoria::registrar('ordem_producao', $id, 'criar', null, $dados);
        }
        return $id;
    }

    /**
     * Conclui a ordem: confere o estoque, baixa cada matéria-prima (quantidade × ficha) e soma o produto acabado.
     * $quantidadeReal = unidades que realmente saíram (pode ser diferente do planejado). Tudo ou nada.
     */
    public static function concluirOrdem(int $id, ?int $usuarioId, ?int $quantidadeReal = null, ?string $quando = null): array
    {
        return Banco::transacao(function () use ($id, $usuarioId, $quantidadeReal, $quando) {
            $op = Banco::um('SELECT * FROM ordens_producao WHERE id = ? FOR UPDATE', [$id]);
            if (!$op) {
                throw new RuntimeException('Ordem de produção não encontrada.');
            }
            if ($op['situacao'] !== 'planejada') {
                throw new RuntimeException('Esta ordem já está ' . strtolower(self::SITUACOES[$op['situacao']][0] ?? $op['situacao']) . '.');
            }
            $qtd = $quantidadeReal ?? (int) $op['quantidade'];
            if ($qtd < 1) {
                throw new RuntimeException('Informe quantas unidades foram fabricadas.');
            }
            // Trava as matérias-primas da ficha antes de conferir (ninguém mexe no meio)
            Banco::todos('SELECT m.id FROM materias_primas m JOIN ficha_tecnica ft ON ft.materia_prima_id = m.id WHERE ft.produto_id = ? FOR UPDATE', [$op['produto_id']]);
            $previa = self::previa((int) $op['produto_id'], $qtd);
            if ($previa['sem_ficha']) {
                throw new RuntimeException('O produto está sem ficha técnica.');
            }
            if (!$previa['ok']) {
                $faltas = array_map(fn($l) => $l['nome'] . ': faltam ' . self::qtd($l['falta'], $l['unidade']), array_filter($previa['linhas'], fn($l) => $l['falta'] > 0));
                throw new RuntimeException('Estoque insuficiente para concluir. ' . implode(' · ', $faltas) . '.');
            }
            foreach ($previa['linhas'] as $l) {
                self::movimentarMp($l['materia_prima_id'], 'consumo', -$l['precisa'], ['referencia' => "OP $id", 'ordem_id' => $id, 'usuario_id' => $usuarioId], $quando);
            }
            self::movimentarAcabado((int) $op['produto_id'], 'producao', $qtd, ['referencia' => "OP $id", 'ordem_id' => $id,
                'observacao' => $op['lote'] ? 'Lote ' . $op['lote'] : null, 'usuario_id' => $usuarioId], $quando);
            $depois = ['situacao' => 'concluida', 'quantidade' => $qtd, 'custo_total' => $previa['custo'], 'concluida_por' => $usuarioId, 'concluida_em' => $quando ?? date('Y-m-d H:i:s')];
            Banco::atualizar('ordens_producao', $id, $depois);
            if ($quando === null) {
                Auditoria::registrar('ordem_producao', $id, 'concluir', ['situacao' => 'planejada', 'quantidade' => (int) $op['quantidade']], $depois);
            }
            return $previa;
        });
    }

    public static function cancelarOrdem(int $id, string $motivo, ?int $usuarioId): void
    {
        $op = Banco::um('SELECT * FROM ordens_producao WHERE id = ?', [$id]);
        if (!$op || $op['situacao'] !== 'planejada') {
            throw new RuntimeException('Só uma ordem planejada pode ser cancelada.');
        }
        $obs = trim(($op['observacoes'] ? $op['observacoes'] . "\n" : '') . 'Cancelada' . ($motivo !== '' ? ": $motivo" : '.'));
        Banco::atualizar('ordens_producao', $id, ['situacao' => 'cancelada', 'cancelada_em' => date('Y-m-d H:i:s'), 'observacoes' => $obs]);
        Auditoria::registrar('ordem_producao', $id, 'cancelar', ['situacao' => 'planejada'], ['situacao' => 'cancelada', 'motivo' => $motivo]);
    }

    public static function ordem(int $id): ?array
    {
        return Banco::um('SELECT o.*, p.codigo, p.nome AS produto, p.embalagem, uc.nome AS criador, uf.nome AS concluidor FROM ordens_producao o
            JOIN produtos p ON p.id = o.produto_id LEFT JOIN usuarios uc ON uc.id = o.criado_por LEFT JOIN usuarios uf ON uf.id = o.concluida_por
            WHERE o.id = ?', [$id]);
    }

    /** Matérias-primas que a ordem concluída realmente usou (dos movimentos) */
    public static function consumoDaOrdem(int $id): array
    {
        return Banco::todos('SELECT m.nome, m.unidade, -mv.quantidade AS quantidade FROM mp_movimentos mv JOIN materias_primas m ON m.id = mv.materia_prima_id
            WHERE mv.ordem_id = ? AND mv.tipo = \'consumo\' ORDER BY m.nome', [$id]);
    }

    /** $f: situacao, produto, q (lote ou nº) · paginado */
    public static function ordens(array $f, int $pagina = 1, int $porPagina = 30): array
    {
        $w = ['1 = 1'];
        $par = [];
        if (isset(self::SITUACOES[$f['situacao'] ?? ''])) {
            $w[] = 'o.situacao = ?';
            $par[] = $f['situacao'];
        }
        if ((int) ($f['produto'] ?? 0) > 0) {
            $w[] = 'o.produto_id = ?';
            $par[] = (int) $f['produto'];
        }
        if (($f['q'] ?? '') !== '') {
            $w[] = '(o.lote LIKE ? OR o.id = ? OR p.nome LIKE ? OR p.codigo LIKE ?)';
            array_push($par, "%{$f['q']}%", (int) $f['q'], "%{$f['q']}%", "{$f['q']}%");
        }
        $where = implode(' AND ', $w);
        $total = (int) Banco::valor("SELECT COUNT(*) FROM ordens_producao o JOIN produtos p ON p.id = o.produto_id WHERE $where", $par);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $itens = Banco::todos("SELECT o.*, p.codigo, p.nome AS produto FROM ordens_producao o JOIN produtos p ON p.id = o.produto_id WHERE $where
            ORDER BY o.situacao = 'planejada' DESC, COALESCE(o.concluida_em, o.criado_em) DESC, o.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $par);
        return compact('itens', 'total', 'pagina', 'paginas');
    }

    // ------------------------------------------------------------------
    // Produto acabado
    // ------------------------------------------------------------------

    /**
     * Produtos com controle na fábrica (têm ficha ou estoque): estoque, mínimo, vendas por dia, cobertura,
     * unidades já planejadas e a sugestão de reabastecimento.
     */
    public static function acabados(string $busca = '', bool $soReabastecer = false): array
    {
        $dias = self::diasConsumo();
        $par = [$dias];
        $w = ['p.ativo = 1', '(e.produto_id IS NOT NULL OR EXISTS (SELECT 1 FROM ficha_tecnica ft WHERE ft.produto_id = p.id))'];
        if ($busca !== '') {
            $w[] = '(p.nome LIKE ? OR p.codigo LIKE ?)';
            array_push($par, "%$busca%", "$busca%");
        }
        $lista = Banco::todos('SELECT p.id, p.codigo, p.nome, p.embalagem, COALESCE(e.quantidade, 0) AS quantidade, COALESCE(e.minimo, 0) AS minimo,
                COALESCE(v.vendido, 0) AS vendido_periodo, COALESCE(pl.planejado, 0) AS planejado,
                EXISTS (SELECT 1 FROM ficha_tecnica ft WHERE ft.produto_id = p.id) AS tem_ficha
            FROM produtos p LEFT JOIN estoque_acabado e ON e.produto_id = p.id
            LEFT JOIN (SELECT i.produto_id, SUM(i.quantidade) AS vendido FROM pedido_itens i JOIN pedidos pe ON pe.id = i.pedido_id
                       WHERE pe.situacao = \'faturado\' AND pe.faturado_em >= NOW() - INTERVAL ? DAY GROUP BY i.produto_id) v ON v.produto_id = p.id
            LEFT JOIN (SELECT produto_id, SUM(quantidade) AS planejado FROM ordens_producao WHERE situacao = \'planejada\' GROUP BY produto_id) pl ON pl.produto_id = p.id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY p.nome', $par);
        $cobertura = max(1, (int) Config::get('producao.dias_cobertura', 30));
        foreach ($lista as &$a) {
            $a['quantidade'] = (int) $a['quantidade'];
            $a['minimo'] = (int) $a['minimo'];
            $a['planejado'] = (int) $a['planejado'];
            $a['venda_dia'] = (float) $a['vendido_periodo'] / $dias;
            $a['dias_cobertura'] = $a['venda_dia'] > 0 ? (int) floor(max(0, $a['quantidade']) / $a['venda_dia']) : null;
            $a['abaixo'] = $a['minimo'] > 0 && $a['quantidade'] < $a['minimo'];
            // Sugestão: cobrir N dias de venda, no mínimo o dobro do estoque mínimo; desconta o que já está planejado
            $alvo = max((int) ceil($a['venda_dia'] * $cobertura), 2 * $a['minimo']);
            $a['sugestao'] = $a['abaixo'] ? max(0, $alvo - $a['quantidade'] - $a['planejado']) : 0;
        }
        unset($a);
        if ($soReabastecer) {
            $lista = array_values(array_filter($lista, fn($a) => $a['abaixo']));
        }
        return $lista;
    }

    /** Produtos abaixo do mínimo (pedido de reabastecimento) */
    public static function reabastecer(): array
    {
        return self::acabados('', true);
    }

    /** Cria a ordem sugerida de reabastecimento para o produto */
    public static function gerarReabastecimento(int $produtoId, ?int $usuarioId): int
    {
        $a = current(array_filter(self::reabastecer(), fn($x) => (int) $x['id'] === $produtoId));
        if (!$a) {
            throw new RuntimeException('Este produto não está abaixo do mínimo.');
        }
        if ($a['sugestao'] < 1) {
            throw new RuntimeException('Já existe ordem planejada suficiente para este produto.');
        }
        return self::criarOrdem(['produto_id' => $produtoId, 'quantidade' => $a['sugestao'],
            'observacoes' => 'Reabastecimento: estoque ' . $a['quantidade'] . ' abaixo do mínimo ' . $a['minimo'] . '.'], $usuarioId, 'reabastecimento');
    }

    public static function definirMinimo(int $produtoId, int $minimo): void
    {
        $minimo = max(0, $minimo);
        $antes = Banco::valor('SELECT minimo FROM estoque_acabado WHERE produto_id = ?', [$produtoId]);
        Banco::executar('INSERT INTO estoque_acabado (produto_id, quantidade, minimo) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE minimo = VALUES(minimo)', [$produtoId, $minimo]);
        if ((string) $antes !== (string) $minimo) {
            Auditoria::registrar('estoque_acabado', $produtoId, 'minimo', ['minimo' => $antes], ['minimo' => $minimo]);
        }
    }

    public static function ajustarAcabado(int $produtoId, int $contagem, string $motivo, ?int $usuarioId): int
    {
        if ($contagem < 0) {
            throw new RuntimeException('A contagem não pode ser negativa.');
        }
        if (mb_strlen(trim($motivo)) < 3) {
            throw new RuntimeException('Escreva o motivo do ajuste.');
        }
        return Banco::transacao(function () use ($produtoId, $contagem, $motivo, $usuarioId) {
            $atual = (int) Banco::valor('SELECT quantidade FROM estoque_acabado WHERE produto_id = ? FOR UPDATE', [$produtoId]);
            $dif = $contagem - $atual;
            if ($dif === 0) {
                return 0;
            }
            self::movimentarAcabado($produtoId, 'ajuste', $dif, ['observacao' => $motivo, 'usuario_id' => $usuarioId]);
            Auditoria::registrar('estoque_acabado', $produtoId, 'ajuste', ['quantidade' => $atual], ['quantidade' => $contagem, 'motivo' => $motivo]);
            return $dif;
        });
    }

    public static function movimentosAcabado(int $produtoId, int $limite = 100): array
    {
        return Banco::todos('SELECT mv.*, u.nome AS usuario FROM acabado_movimentos mv LEFT JOIN usuarios u ON u.id = mv.usuario_id
            WHERE mv.produto_id = ? ORDER BY mv.criado_em DESC, mv.id DESC LIMIT ' . $limite, [$produtoId]);
    }

    /**
     * Pedido faturado: tira as unidades do estoque acabado (se a configuração estiver ligada).
     * Só mexe nos produtos que a fábrica controla (já têm linha em estoque_acabado) e nunca baixa o mesmo pedido duas vezes.
     */
    public static function baixarVenda(int $pedidoId, ?string $quando = null): int
    {
        if (!Config::get('producao.baixar_no_faturamento', true)) {
            return 0;
        }
        if (Banco::valor("SELECT 1 FROM acabado_movimentos WHERE pedido_id = ? AND tipo = 'venda' LIMIT 1", [$pedidoId])) {
            return 0;
        }
        $itens = Banco::todos('SELECT i.produto_id, SUM(i.quantidade) AS qtd FROM pedido_itens i JOIN estoque_acabado e ON e.produto_id = i.produto_id
            WHERE i.pedido_id = ? GROUP BY i.produto_id', [$pedidoId]);
        foreach ($itens as $i) {
            self::movimentarAcabado((int) $i['produto_id'], 'venda', -(int) $i['qtd'], ['referencia' => "Pedido $pedidoId", 'pedido_id' => $pedidoId], $quando);
        }
        return count($itens);
    }

    /** Único ponto que muda o estoque acabado. O saldo pode ficar negativo (sinal de que precisa conferir o inventário). */
    private static function movimentarAcabado(int $produtoId, string $tipo, int $quantidade, array $extra = [], ?string $quando = null): int
    {
        return Banco::transacao(function () use ($produtoId, $tipo, $quantidade, $extra, $quando) {
            Banco::executar('INSERT IGNORE INTO estoque_acabado (produto_id, quantidade, minimo) VALUES (?, 0, 0)', [$produtoId]);
            $saldo = (int) Banco::valor('SELECT quantidade FROM estoque_acabado WHERE produto_id = ? FOR UPDATE', [$produtoId]) + $quantidade;
            Banco::executar('UPDATE estoque_acabado SET quantidade = ? WHERE produto_id = ?', [$saldo, $produtoId]);
            Banco::inserir('acabado_movimentos', array_filter([
                'produto_id' => $produtoId, 'tipo' => $tipo, 'quantidade' => $quantidade, 'saldo' => $saldo,
                'referencia' => $extra['referencia'] ?? null, 'ordem_id' => $extra['ordem_id'] ?? null, 'pedido_id' => $extra['pedido_id'] ?? null,
                'observacao' => isset($extra['observacao']) ? mb_substr((string) $extra['observacao'], 0, 255) : null,
                'usuario_id' => $extra['usuario_id'] ?? null, 'criado_em' => $quando,
            ], fn($v) => $v !== null && $v !== ''));
            return $saldo;
        });
    }

    // ------------------------------------------------------------------
    // Painel e alertas
    // ------------------------------------------------------------------

    /** Números rápidos para o One e a tela Início */
    public static function alertas(): array
    {
        $mps = self::materias();
        return [
            'mp_abaixo'    => count(array_filter($mps, fn($m) => $m['abaixo'])),
            'mp_acabando'  => count(array_filter($mps, fn($m) => !$m['abaixo'] && $m['acabando'])),
            'reabastecer'  => count(array_filter(self::reabastecer(), fn($a) => $a['sugestao'] > 0)),
            'planejadas'   => (int) Banco::valor("SELECT COUNT(*) FROM ordens_producao WHERE situacao = 'planejada'"),
        ];
    }

    public static function painel(): array
    {
        $mps = self::materias();
        $alerta = array_values(array_filter($mps, fn($m) => $m['abaixo'] || $m['acabando']));
        usort($alerta, fn($a, $b) => [(int) !$a['abaixo'], $a['dias_restantes'] ?? 9999] <=> [(int) !$b['abaixo'], $b['dias_restantes'] ?? 9999]);
        $mes = date('Y-m-01');
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("$mes -$i months"));
            $meses[$m] = 0;
        }
        foreach (Banco::todos("SELECT DATE_FORMAT(concluida_em, '%Y-%m') AS mes, SUM(quantidade) AS unidades FROM ordens_producao
                WHERE situacao = 'concluida' AND concluida_em >= ? GROUP BY mes", [array_key_first($meses) . '-01']) as $r) {
            $meses[$r['mes']] = (int) $r['unidades'];
        }
        return [
            'materias_alerta' => $alerta,
            'reabastecer'     => self::reabastecer(),
            'planejadas'      => Banco::todos("SELECT o.*, p.nome AS produto FROM ordens_producao o JOIN produtos p ON p.id = o.produto_id
                                   WHERE o.situacao = 'planejada' ORDER BY COALESCE(o.previsto_para, '9999-12-31'), o.id LIMIT 8"),
            'mes'             => Banco::um("SELECT COUNT(*) AS ordens, COALESCE(SUM(quantidade), 0) AS unidades, COALESCE(SUM(custo_total), 0) AS custo
                                   FROM ordens_producao WHERE situacao = 'concluida' AND concluida_em >= ?", [$mes]),
            'por_mes'         => $meses,
            'totais'          => ['materias' => count($mps), 'valor_estoque' => array_sum(array_map(fn($m) => $m['estoque'] * (float) $m['custo_unitario'], $mps)),
                                  'sem_ficha' => (int) Banco::valor('SELECT COUNT(*) FROM produtos p WHERE p.ativo = 1 AND NOT EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id)')],
            'ultimas'         => Banco::todos("SELECT o.id, o.quantidade, o.lote, o.concluida_em, p.nome AS produto FROM ordens_producao o JOIN produtos p ON p.id = o.produto_id
                                   WHERE o.situacao = 'concluida' ORDER BY o.concluida_em DESC LIMIT 5"),
        ];
    }
}
