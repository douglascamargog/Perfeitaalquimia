<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Ligar os produtos que vieram SÓ do Nota10 (criados pela importação, inativos, slug "n10-...") aos produtos que já estavam no One.
 *
 * Ao ligar: o histórico do duplicado (itens de pedido e de orçamento, produção, lotes, documentos...) passa para o produto do One;
 * preços, estoque, parte técnica e nutricional só COMPLETAM o que o produto do One não tem (o do One sempre vale mais);
 * o código passa para o produto do One se ele ainda não tiver um. O duplicado fica guardado, inativo, com juntado_a_id apontando
 * para o produto do One — e as próximas importações do Nota10 já caem no produto do One. Tudo pode ser desfeito.
 */
class ProdutoNota10Service
{
    /** Tabelas com histórico (chave "id"): os registros MUDAM de produto e ficam anotados para desfazer */
    private const MOVER = ['pedido_itens', 'orcamento_itens', 'pedido_item_lotes', 'acabado_movimentos', 'ordens_producao', 'producao_perdas',
        'produto_documentos', 'dp_projetos', 'formulas', 'mkt_campanhas'];

    /** Tabelas de cadastro (uma linha por produto): só COPIA para o produto do One o que ele ainda não tem */
    private const COMPLETAR = ['produto_precos', 'produto_estoque', 'produto_nutricional', 'produto_tecnico', 'estoque_acabado',
        'campanha_produtos', 'ficha_tecnica', 'pedido_modelo_itens', 'terceirizacao_produtos'];

    /** Duplicado do Nota10 ainda não ligado */
    private const DUPLICADO = "p.slug LIKE 'n10-%' AND p.juntado_a_id IS NULL";

    /** Produto do One (não veio só do Nota10 e não foi juntado a outro) */
    private const DO_ONE = "p.slug NOT LIKE 'n10-%' AND p.juntado_a_id IS NULL";

    /** Produtos que vieram só do Nota10, com o uso no histórico e a sugestão de produto do One (pelo código, código de barras ou nome) */
    public static function pendentes(): array
    {
        $lista = Banco::todos("SELECT p.id, p.nome, p.nota10_codigo, p.codigo, p.grupo_erp, t.ean,
                (SELECT COUNT(*) FROM pedido_itens i WHERE i.produto_id = p.id) AS itens,
                (SELECT MAX(pe.criado_em) FROM pedido_itens i JOIN pedidos pe ON pe.id = i.pedido_id WHERE i.produto_id = p.id) AS ultima_venda
            FROM produtos p LEFT JOIN produto_tecnico t ON t.produto_id = p.id
            WHERE " . self::DUPLICADO . ' ORDER BY itens DESC, p.nome');
        foreach ($lista as &$d) {
            $d['sugestao'] = self::sugestao($d);
        }
        return $lista;
    }

    /** Melhor palpite: ['id', 'nome', 'codigo', 'por' => 'codigo'|'ean'|'nome'] ou null */
    public static function sugestao(array $d): ?array
    {
        $codigo = mb_strtoupper(trim((string) ($d['nota10_codigo'] ?? '')));
        if ($codigo !== '' && ($p = Banco::um('SELECT p.id, p.nome, p.codigo FROM produtos p WHERE ' . self::DO_ONE . ' AND UPPER(TRIM(p.codigo)) = ? LIMIT 1', [$codigo]))) {
            return $p + ['por' => 'codigo'];
        }
        $ean = preg_replace('/\D/', '', (string) ($d['ean'] ?? ''));
        if (strlen($ean) >= 8 && ($p = Banco::um('SELECT p.id, p.nome, p.codigo FROM produtos p JOIN produto_tecnico t ON t.produto_id = p.id
                WHERE ' . self::DO_ONE . ' AND t.ean = ? LIMIT 1', [$ean]))) {
            return $p + ['por' => 'ean'];
        }
        $nome = self::nome((string) $d['nome']);
        if ($nome !== '') {
            foreach (self::doOne() as $p) {
                if (self::nome((string) $p['nome']) === $nome) {
                    return $p + ['por' => 'nome'];
                }
            }
        }
        return null;
    }

    /** Produtos do One para escolher (todos, ativos primeiro) */
    public static function doOne(): array
    {
        static $cache = null;
        return $cache ??= Banco::todos('SELECT p.id, p.nome, p.codigo, p.ativo FROM produtos p WHERE ' . self::DO_ONE . ' ORDER BY p.ativo DESC, p.nome');
    }

    /** Ligações já feitas (as mais novas primeiro) */
    public static function ligados(int $limite = 200): array
    {
        return Banco::todos("SELECT j.*, o.nome AS origem_nome, o.nota10_codigo, d.nome AS destino_nome, d.codigo AS destino_codigo, u.nome AS usuario
            FROM produto_juncoes j JOIN produtos o ON o.id = j.origem_id JOIN produtos d ON d.id = j.destino_id LEFT JOIN usuarios u ON u.id = j.usuario_id
            WHERE j.desfeito_em IS NULL ORDER BY j.id DESC LIMIT " . (int) $limite);
    }

    /** Liga o duplicado do Nota10 ($origemId) ao produto do One ($destinoId). Devolve o id da ligação. */
    public static function juntar(int $origemId, int $destinoId, ?int $usuarioId): int
    {
        $origem = Banco::um('SELECT p.* FROM produtos p WHERE p.id = ? AND ' . self::DUPLICADO, [$origemId]);
        $destino = Banco::um('SELECT p.* FROM produtos p WHERE p.id = ? AND ' . self::DO_ONE, [$destinoId]);
        if (!$origem) {
            throw new RuntimeException('Esse produto do Nota10 não existe ou já foi ligado.');
        }
        if (!$destino) {
            throw new RuntimeException('Escolha um produto do One.');
        }
        return Banco::transacao(function () use ($origem, $destino, $usuarioId) {
            $o = (int) $origem['id'];
            $d = (int) $destino['id'];
            $movidos = [];
            foreach (self::MOVER as $t) {
                if (!self::temTabela($t)) {
                    continue;
                }
                $ids = array_map('intval', array_column(Banco::todos("SELECT id FROM `$t` WHERE produto_id = ?", [$o]), 'id'));
                if ($ids) {
                    Banco::executar("UPDATE `$t` SET produto_id = ? WHERE produto_id = ?", [$d, $o]);
                    $movidos[$t] = $ids;
                }
            }
            foreach (self::COMPLETAR as $t) {
                self::completar($t, $o, $d);
            }
            // parte técnica: o que estiver vazio no produto do One
            Banco::executar('UPDATE produto_tecnico dt JOIN produto_tecnico ot ON ot.produto_id = ? SET dt.ean = COALESCE(NULLIF(dt.ean, \'\'), ot.ean),
                dt.ncm = COALESCE(NULLIF(dt.ncm, \'\'), ot.ncm), dt.peso_liquido = COALESCE(dt.peso_liquido, ot.peso_liquido), dt.peso_bruto = COALESCE(dt.peso_bruto, ot.peso_bruto)
                WHERE dt.produto_id = ?', [$o, $d]);
            // cadastro: o que estiver vazio no produto do One; o código passa se o do One não tiver
            $completa = [];
            foreach (['unidade', 'cest', 'custo', 'grupo_erp', 'cadastrado_erp_em'] as $c) {
                if (($destino[$c] ?? null) === null || $destino[$c] === '') {
                    if (($origem[$c] ?? null) !== null && $origem[$c] !== '') {
                        $completa[$c] = $origem[$c];
                    }
                }
            }
            $codigoMovido = null;
            if (trim((string) $destino['codigo']) === '' && trim((string) $origem['codigo']) !== '') {
                $codigoMovido = (string) $origem['codigo'];
                Banco::executar('UPDATE produtos SET codigo = NULL WHERE id = ?', [$o]); // o código é único
                $completa['codigo'] = $codigoMovido;
            }
            if ($completa) {
                Banco::atualizar('produtos', $d, $completa);
            }
            Banco::executar('UPDATE produtos SET juntado_a_id = ?, juntado_em = NOW(), ativo = 0, visivel_catalogo = 0 WHERE id = ?', [$d, $o]);
            $id = Banco::inserir('produto_juncoes', ['origem_id' => $o, 'destino_id' => $d, 'movidos' => json_encode($movidos),
                'codigo_movido' => $codigoMovido, 'usuario_id' => $usuarioId]);
            Auditoria::registrar('produto', $d, 'ligar_produto_nota10', null, ['nota10' => $origem['nota10_codigo'] . ' · ' . $origem['nome'],
                'itens_de_pedido' => count($movidos['pedido_itens'] ?? [])]);
            return $id;
        });
    }

    /** Liga todos os que têm sugestão pelo CÓDIGO (o mais seguro). Devolve quantos ligou. */
    public static function juntarPorCodigo(?int $usuarioId): int
    {
        $n = 0;
        foreach (self::pendentes() as $d) {
            if (($d['sugestao']['por'] ?? '') === 'codigo') {
                self::juntar((int) $d['id'], (int) $d['sugestao']['id'], $usuarioId);
                $n++;
            }
        }
        return $n;
    }

    /** Desfaz uma ligação: o histórico volta para o produto do Nota10 (o que foi só copiado para o do One fica lá) */
    public static function desfazer(int $juncaoId, ?int $usuarioId): void
    {
        $j = Banco::um('SELECT * FROM produto_juncoes WHERE id = ? AND desfeito_em IS NULL', [$juncaoId]);
        if (!$j) {
            throw new RuntimeException('Essa ligação já foi desfeita.');
        }
        Banco::transacao(function () use ($j, $usuarioId) {
            $o = (int) $j['origem_id'];
            $d = (int) $j['destino_id'];
            foreach ((array) json_decode((string) $j['movidos'], true) as $t => $ids) {
                $ids = array_map('intval', (array) $ids);
                if ($ids && in_array($t, self::MOVER, true)) {
                    Banco::executar("UPDATE `$t` SET produto_id = ? WHERE produto_id = ? AND id IN (" . implode(',', $ids) . ')', [$o, $d]);
                }
            }
            if ($j['codigo_movido'] && Banco::valor('SELECT 1 FROM produtos WHERE id = ? AND codigo = ?', [$d, $j['codigo_movido']])) {
                Banco::executar('UPDATE produtos SET codigo = NULL WHERE id = ?', [$d]);
                Banco::executar('UPDATE produtos SET codigo = ? WHERE id = ?', [$j['codigo_movido'], $o]);
            }
            Banco::executar('UPDATE produtos SET juntado_a_id = NULL, juntado_em = NULL WHERE id = ?', [$o]);
            Banco::executar('UPDATE produto_juncoes SET desfeito_em = NOW() WHERE id = ?', [(int) $j['id']]);
            Auditoria::registrar('produto', $d, 'desfazer_ligacao_nota10', null, ['nota10_produto_id' => $o]);
        });
    }

    /** Copia as linhas do duplicado para o produto do One, só onde o do One ainda não tem (INSERT IGNORE pela chave única) */
    private static function completar(string $tabela, int $origem, int $destino): void
    {
        if (!self::temTabela($tabela)) {
            return;
        }
        $colunas = array_column(Banco::todos("SELECT column_name AS c, extra AS e FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?
            ORDER BY ordinal_position", [$tabela]), 'e', 'c');
        $copiar = array_keys(array_filter($colunas, fn($extra, $c) => $c !== 'produto_id' && !str_contains((string) $extra, 'auto_increment')
            && !str_contains((string) $extra, 'GENERATED'), ARRAY_FILTER_USE_BOTH));
        $lista = implode(', ', array_map(fn($c) => "`$c`", $copiar));
        Banco::executar("INSERT IGNORE INTO `$tabela` (produto_id" . ($lista ? ", $lista" : '') . ") SELECT ?" . ($lista ? ", $lista" : '') . " FROM `$tabela` WHERE produto_id = ?", [$destino, $origem]);
    }

    /** Nome para comparar: sem acento, minúsculo, só letras e números */
    private static function nome(string $nome): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Normalizar::semAcento($nome)));
    }

    private static function temTabela(string $tabela): bool
    {
        static $tabelas = null;
        $tabelas ??= array_column(Banco::todos('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'), 't');
        return in_array($tabela, $tabelas, true);
    }
}
