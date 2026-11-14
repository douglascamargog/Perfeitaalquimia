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
        if (!empty($f['rotulos'])) {
            $w[] = 'm.rotulo = 1';
        }
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
            'tipo'           => ($d['tipo'] ?? '') === 'embalagem' ? 'embalagem' : 'materia_prima', // embalagem: pote, tampa, rótulo, lacre...
            'rotulo'         => ($d['tipo'] ?? '') === 'embalagem' && !empty($d['rotulo']) ? 1 : 0,  // aparece em Produção › Rótulos
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

    // ------------------------------------------------------------------
    // Rótulos (nossos produtos + clientes de terceirização) num lugar só
    // ------------------------------------------------------------------

    /** Rótulos dos nossos produtos (embalagens marcadas como rótulo), com o que as ordens planejadas vão gastar */
    public static function rotulos(): array
    {
        $emOrdens = [];
        foreach (Banco::todos("SELECT ft.materia_prima_id, SUM(o.quantidade * ft.quantidade) AS qtd FROM ordens_producao o
            JOIN ficha_tecnica ft ON ft.produto_id = o.produto_id JOIN materias_primas m ON m.id = ft.materia_prima_id AND m.rotulo = 1
            WHERE o.situacao = 'planejada' GROUP BY ft.materia_prima_id") as $x) {
            $emOrdens[(int) $x['materia_prima_id']] = (float) $x['qtd'];
        }
        return array_map(function ($m) use ($emOrdens) {
            $m['em_ordens'] = $emOrdens[(int) $m['id']] ?? 0.0;
            $m['alerta'] = $m['abaixo'] || $m['estoque'] < $m['em_ordens'];
            return $m;
        }, self::materias(['rotulos' => 1]));
    }

    /** Últimos movimentos de rótulos: nossos (mp_movimentos) e dos clientes (terceirização), juntos */
    public static function movimentosRotulos(int $limite = 40): array
    {
        $limite = max(1, min(200, $limite));
        return Banco::todos("SELECT * FROM (
                SELECT mv.criado_em, m.nome, NULL AS cliente, mv.tipo, mv.quantidade, mv.saldo, mv.ordem_id, mv.observacao, u.nome AS usuario, mv.id
                  FROM mp_movimentos mv JOIN materias_primas m ON m.id = mv.materia_prima_id AND m.rotulo = 1 LEFT JOIN usuarios u ON u.id = mv.usuario_id
                UNION ALL
                SELECT tm.criado_em, tp.nome_rotulo, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social), tm.tipo, tm.quantidade, tm.saldo_apos,
                       tm.ordem_producao_id, tm.observacao, u.nome, tm.id
                  FROM terceirizacao_rotulos_mov tm JOIN terceirizacao_produtos tp ON tp.id = tm.terceirizacao_produto_id
                  JOIN clientes c ON c.id = tp.cliente_id LEFT JOIN usuarios u ON u.id = tm.usuario_id
            ) x ORDER BY x.criado_em DESC, x.id DESC LIMIT $limite");
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
            if ($quando === null && $quantidade < 0) {
                self::avisarSeCruzouMinimoMp($mpId, (float) $atual, $saldo);
            }
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
        return Banco::todos('SELECT ft.materia_prima_id, ft.quantidade, ft.perda_pct, m.nome, m.unidade, m.custo_unitario, m.estoque, m.ativo, m.tipo, m.rotulo,
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
    /**
     * Prévia do consumo de uma ordem: cada item da ficha (matérias-primas e embalagens) com a PERDA medida somada,
     * o que falta e o MÁXIMO de unidades que o estoque permite produzir (e qual item limita).
     * Perda: a medida neste produto; se ainda não foi medida, a média medida da matéria-prima em outros produtos; senão 0.
     */
    public static function previa(int $produtoId, int $quantidade): array
    {
        $linhas = [];
        $ok = true;
        $custo = 0.0;
        $medias = self::perdasPorMateria();
        $maximo = null;
        $limita = null;
        foreach (self::ficha($produtoId) as $l) {
            $mpId = (int) $l['materia_prima_id'];
            [$perda, $origem] = $l['perda_pct'] !== null ? [(float) $l['perda_pct'], 'produto'] : (isset($medias[$mpId]) ? [$medias[$mpId], 'materia'] : [0.0, null]);
            $porUnidade = (float) $l['quantidade'] * (1 + max(0, $perda) / 100);
            $teorico = round((float) $l['quantidade'] * $quantidade, 3);
            $precisa = self::arredondar($porUnidade * $quantidade, (string) $l['unidade']);
            $falta = max(0, round($precisa - (float) $l['estoque'], 3));
            $ok = $ok && $falta <= 0;
            $custo += $precisa * (float) $l['custo_unitario'];
            $daPara = $porUnidade > 0 ? (int) floor(((float) $l['estoque'] + 1e-9) / $porUnidade) : null;
            if ($daPara !== null && ($maximo === null || $daPara < $maximo)) {
                [$maximo, $limita] = [$daPara, $l['nome']];
            }
            $linhas[] = ['materia_prima_id' => $mpId, 'nome' => $l['nome'], 'unidade' => $l['unidade'],
                'tipo' => $l['tipo'] === 'embalagem' ? 'embalagem' : 'materia_prima', 'rotulo' => (bool) ($l['rotulo'] ?? false),
                'por_unidade' => (float) $l['quantidade'], 'teorico' => $teorico, 'perda_pct' => round($perda, 2), 'perda_origem' => $origem,
                'precisa' => $precisa, 'estoque' => (float) $l['estoque'], 'falta' => $falta, 'da_para' => $daPara];
        }
        // Matérias-primas primeiro, depois embalagens (pote, tampa, selo, rótulo...)
        usort($linhas, fn($a, $b) => [$a['tipo'] === 'embalagem', $a['nome']] <=> [$b['tipo'] === 'embalagem', $b['nome']]);
        $embalagens = array_filter($linhas, fn($l) => $l['tipo'] === 'embalagem');
        return ['linhas' => $linhas, 'ok' => $ok && $linhas !== [], 'custo' => round($custo, 2), 'sem_ficha' => $linhas === [],
            'maximo' => $maximo ?? 0, 'limita' => $limita,
            // Só o rótulo (ou nada) nas embalagens: provavelmente faltam pote, tampa, selo... no cadastro
            'sem_embalagem' => $linhas !== [] && !array_filter($embalagens, fn($l) => !$l['rotulo']),
            'slug' => (string) Banco::valor('SELECT slug FROM produtos WHERE id = ?', [$produtoId])];
    }

    /** Grava a medição e atualiza a perda do item NESTE produto (média das últimas 5 medições, nunca negativa) */
    private static function medirPerda(array $op, array $linha, int $unidades, float $usado, ?int $usuarioId): void
    {
        $perda = round(($usado - $linha['teorico']) / $linha['teorico'] * 100, 2);
        Banco::inserir('producao_perdas', ['ordem_id' => $op['id'], 'produto_id' => $op['produto_id'], 'materia_prima_id' => $linha['materia_prima_id'],
            'unidades' => $unidades, 'teorico' => $linha['teorico'], 'usado' => round($usado, 3), 'perda_pct' => max(-99, min(999, $perda)), 'usuario_id' => $usuarioId]);
        $media = (float) Banco::valor('SELECT AVG(GREATEST(x.perda_pct, 0)) FROM (SELECT perda_pct FROM producao_perdas WHERE produto_id = ? AND materia_prima_id = ?
            ORDER BY id DESC LIMIT 5) x', [$op['produto_id'], $linha['materia_prima_id']]);
        Banco::executar('UPDATE ficha_tecnica SET perda_pct = ? WHERE produto_id = ? AND materia_prima_id = ?', [round($media, 2), $op['produto_id'], $linha['materia_prima_id']]);
    }

    /** Unidade inteira ("un") arredonda para cima; o resto fica com 3 casas */
    private static function arredondar(float $valor, string $unidade): float
    {
        return $unidade === 'un' ? (float) ceil(round($valor, 6)) : round($valor, 3);
    }

    /** Perda média medida de cada matéria-prima (todas as medições, em qualquer produto) — usada quando o produto ainda não foi medido */
    public static function perdasPorMateria(): array
    {
        $r = [];
        foreach (Banco::todos('SELECT materia_prima_id, AVG(GREATEST(perda_pct, 0)) AS media FROM producao_perdas GROUP BY materia_prima_id') as $x) {
            $r[(int) $x['materia_prima_id']] = round((float) $x['media'], 2);
        }
        return $r;
    }

    /** Medições de perda de uma ordem (teórico × usado) */
    public static function perdasDaOrdem(int $ordemId): array
    {
        return Banco::todos('SELECT pp.*, m.nome, m.unidade FROM producao_perdas pp JOIN materias_primas m ON m.id = pp.materia_prima_id WHERE pp.ordem_id = ? ORDER BY m.nome', [$ordemId]);
    }

    public static function criarOrdem(array $d, ?int $usuarioId, string $origem = 'manual', ?string $quando = null): int
    {
        $produtoId = (int) ($d['produto_id'] ?? 0);
        $qtd = (int) ($d['quantidade'] ?? 0);
        // Produto ativo, ou produto novo em Desenvolvimento (lote piloto)
        if (!Banco::valor("SELECT 1 FROM produtos WHERE id = ? AND (ativo = 1 OR EXISTS (SELECT 1 FROM dp_projetos dp WHERE dp.produto_id = produtos.id AND dp.situacao = 'andamento'))", [$produtoId])) {
            throw new RuntimeException('Escolha o produto.');
        }
        if ($qtd < 1 || $qtd > 1000000) {
            throw new RuntimeException('Informe quantas unidades vão ser fabricadas.');
        }
        if (!Banco::valor('SELECT 1 FROM ficha_tecnica WHERE produto_id = ? LIMIT 1', [$produtoId])) {
            throw new RuntimeException('Este produto ainda não tem ficha técnica. Cadastre a ficha (o que vai em cada unidade) antes de produzir.');
        }
        // Terceirização: ordem para um cliente que faz este produto com a marca dele (usa os rótulos dele)
        $clienteId = (int) ($d['cliente_id'] ?? 0) ?: null;
        if ($clienteId && !Banco::valor('SELECT 1 FROM terceirizacao_produtos WHERE cliente_id = ? AND produto_id = ? AND ativo = 1', [$clienteId, $produtoId])) {
            throw new RuntimeException('Este cliente não tem esse produto cadastrado na Terceirização.');
        }
        $data = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $dados = array_filter([
            'produto_id' => $produtoId, 'quantidade' => $qtd, 'cliente_id' => $clienteId, 'origem' => $origem === 'reabastecimento' ? 'reabastecimento' : 'manual',
            'lote' => mb_substr(trim((string) ($d['lote'] ?? '')), 0, 40) ?: null, 'validade' => $data($d['validade'] ?? null),
            'previsto_para' => $data($d['previsto_para'] ?? null), 'observacoes' => trim((string) ($d['observacoes'] ?? '')) ?: null,
            'criado_por' => $usuarioId, 'criado_em' => $quando,
        ], fn($v) => $v !== null);
        $id = Banco::inserir('ordens_producao', $dados);
        if ($quando === null) {
            Auditoria::registrar('ordem_producao', $id, 'criar', null, $dados);
            $nome = (string) Banco::valor('SELECT nome FROM produtos WHERE id = ?', [$produtoId]);
            NotificacaoService::diretoria('op_criada', "Ordem de produção criada: $qtd × $nome", '/producao/ordens/' . $id,
                ['texto' => 'Ordem #' . $id . ($dados['previsto_para'] ?? null ? ' · prevista para ' . data_br($dados['previsto_para']) : '')], $usuarioId);
        }
        return $id;
    }

    /**
     * Conclui a ordem: confere o estoque, baixa cada matéria-prima (quantidade × ficha) e soma o produto acabado.
     * $quantidadeReal = unidades que realmente saíram (pode ser diferente do planejado). Tudo ou nada.
     */
    /**
     * Conclui a ordem. $usado = [materia_prima_id => quantidade usada de verdade] (opcional): o que for informado é baixado
     * como está e vira uma MEDIÇÃO de perda (usado × teórico); o que não for informado baixa a previsão (com a perda já conhecida).
     */
    public static function concluirOrdem(int $id, ?int $usuarioId, ?int $quantidadeReal = null, ?string $quando = null, array $usado = []): array
    {
        $r = self::concluirOrdem_($id, $usuarioId, $quantidadeReal, $quando, $usado);
        if ($quando === null) {
            $op = Banco::um('SELECT o.quantidade, p.nome FROM ordens_producao o JOIN produtos p ON p.id = o.produto_id WHERE o.id = ?', [$id]);
            $qtd = (int) ($op['quantidade'] ?? 0);
            NotificacaoService::diretoria('op_concluida', "Produção concluída: $qtd × " . ($op['nome'] ?? ''), '/producao/ordens/' . $id,
                ['texto' => 'Ordem #' . $id . ': os produtos entraram no estoque.'], $usuarioId);
        }
        return $r;
    }

    /** Aviso para a Diretoria quando a matéria-prima/embalagem/rótulo acabou de ficar abaixo do mínimo */
    private static function avisarSeCruzouMinimoMp(int $mpId, float $antes, float $depois): void
    {
        $m = Banco::um('SELECT nome, unidade, tipo, rotulo, estoque_minimo FROM materias_primas WHERE id = ?', [$mpId]);
        $minimo = (float) ($m['estoque_minimo'] ?? 0);
        if (!$m || $minimo <= 0 || $antes < $minimo || $depois >= $minimo) {
            return;
        }
        $o_que = $m['rotulo'] ? 'Rótulo' : ($m['tipo'] === 'embalagem' ? 'Embalagem' : 'Matéria-prima');
        NotificacaoService::diretoria('estoque', "$o_que abaixo do mínimo: {$m['nome']}", '/producao/materias/' . $mpId,
            ['texto' => 'Ficou com ' . self::qtd($depois, (string) $m['unidade']) . ' (mínimo ' . self::qtd($minimo, (string) $m['unidade']) . ')']);
    }

    private static function concluirOrdem_(int $id, ?int $usuarioId, ?int $quantidadeReal = null, ?string $quando = null, array $usado = []): array
    {
        return Banco::transacao(function () use ($id, $usuarioId, $quantidadeReal, $quando, $usado) {
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
            // Consumo informado (medido): substitui a previsão daquele item
            $medidos = [];
            foreach ($previa['linhas'] as &$l) {
                $valor = $usado[$l['materia_prima_id']] ?? '';
                if ($valor === '' || $valor === null) {
                    continue;
                }
                $real = self::numero((string) $valor);
                if ($real < 0) {
                    throw new RuntimeException('O consumo de ' . $l['nome'] . ' não pode ser negativo.');
                }
                $medidos[$l['materia_prima_id']] = $real;
                $l['precisa'] = round($real, 3);
                $l['falta'] = max(0, round($real - $l['estoque'], 3));
            }
            unset($l);
            if ($medidos) {
                $previa['ok'] = !array_filter($previa['linhas'], fn($l) => $l['falta'] > 0);
                $previa['custo'] = round(array_sum(array_map(fn($l) => $l['precisa'] * (float) Banco::valor('SELECT custo_unitario FROM materias_primas WHERE id = ?', [$l['materia_prima_id']]), $previa['linhas'])), 2);
            }
            if (!$previa['ok']) {
                $faltas = array_map(fn($l) => $l['nome'] . ': faltam ' . self::qtd($l['falta'], $l['unidade']), array_filter($previa['linhas'], fn($l) => $l['falta'] > 0));
                throw new RuntimeException('Estoque insuficiente para concluir. ' . implode(' · ', $faltas) . '.');
            }
            // Terceirização: precisa de 1 rótulo do cliente por unidade (sem rótulo, não conclui)
            $terc = \App\Services\TerceirizacaoService::daOrdem($op);
            if ($terc) {
                $saldo = (int) Banco::valor('SELECT rotulos_estoque FROM terceirizacao_produtos WHERE id = ? FOR UPDATE', [$terc['id']]);
                if ($saldo < $qtd) {
                    $cliente = (string) Banco::valor('SELECT COALESCE(NULLIF(nome_fantasia, ""), razao_social) FROM clientes WHERE id = ?', [$op['cliente_id']]);
                    throw new RuntimeException('Faltam ' . ($qtd - $saldo) . " rótulo(s) de \"{$terc['nome_rotulo']}\" do cliente $cliente (tem $saldo, precisa de $qtd). Registre a chegada dos rótulos na Terceirização.");
                }
                \App\Services\TerceirizacaoService::movimentarRotulos((int) $terc['id'], 'consumo', -$qtd, "OP $id", $usuarioId, $id, $quando);
            }
            foreach ($previa['linhas'] as $l) {
                self::movimentarMp($l['materia_prima_id'], 'consumo', -$l['precisa'], ['referencia' => "OP $id", 'ordem_id' => $id, 'usuario_id' => $usuarioId], $quando);
                if (isset($medidos[$l['materia_prima_id']]) && $l['teorico'] > 0) {
                    self::medirPerda($op, $l, $qtd, $medidos[$l['materia_prima_id']], $usuarioId);
                }
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
        return Banco::um('SELECT o.*, p.codigo, p.nome AS produto, p.embalagem, uc.nome AS criador, uf.nome AS concluidor,
                COALESCE(NULLIF(cl.nome_fantasia, ""), cl.razao_social) AS cliente_nome, tp.nome_rotulo, tp.rotulos_estoque
            FROM ordens_producao o
            JOIN produtos p ON p.id = o.produto_id LEFT JOIN usuarios uc ON uc.id = o.criado_por LEFT JOIN usuarios uf ON uf.id = o.concluida_por
            LEFT JOIN clientes cl ON cl.id = o.cliente_id LEFT JOIN terceirizacao_produtos tp ON tp.cliente_id = o.cliente_id AND tp.produto_id = o.produto_id
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
            if ($quando === null && $quantidade < 0) {
                $minimo = (int) Banco::valor('SELECT minimo FROM estoque_acabado WHERE produto_id = ?', [$produtoId]);
                if ($minimo > 0 && $saldo - $quantidade >= $minimo && $saldo < $minimo) { // acabou de ficar abaixo do mínimo
                    $nome = (string) Banco::valor('SELECT nome FROM produtos WHERE id = ?', [$produtoId]);
                    NotificacaoService::diretoria('estoque', "Produto acabando: $nome", '/producao/reabastecer',
                        ['texto' => "Ficou com $saldo un. (mínimo $minimo)"], $extra['usuario_id'] ?? null);
                }
            }
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
