<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use RuntimeException;

/**
 * Fórmulas (V15.0) — área da Responsável Técnica.
 *  - calcular(): massa da porção, % de cada ingrediente, cápsulas, custo por porção/embalagem/lote e estoque para o lote piloto.
 *  - novaVersao() / comparar(): cada mudança grande vira uma versão (a anterior fica guardada).
 *  - aprovar(): a fórmula aprovada VIRA A FICHA TÉCNICA do produto (ProducaoService::salvarFicha), por unidade fabricada
 *    (= por embalagem). A ficha anterior fica guardada no histórico da fórmula.
 * As quantidades são por PORÇÃO (ou por embalagem, para pote, tampa, rótulo...). A conversão para a unidade da
 * matéria-prima (kg, g, L, un) é feita aqui.
 */
class FormulaService
{
    public const FORMAS = ['capsula' => 'Cápsula', 'comprimido' => 'Comprimido', 'po' => 'Pó', 'sache' => 'Sachê', 'liquido' => 'Líquido', 'cha' => 'Chá', 'outro' => 'Outro'];
    public const SITUACOES = ['rascunho' => ['Rascunho', 'cinza'], 'em_teste' => ['Em teste', 'laranja'], 'aprovada' => ['Aprovada', 'verde'], 'arquivada' => ['Arquivada', 'cinza']];
    public const UNIDADES = ['mcg' => 'mcg', 'mg' => 'mg', 'g' => 'g', 'ml' => 'ml', 'un' => 'un'];
    public const FUNCOES = ['ativo' => 'Ativo', 'excipiente' => 'Excipiente', 'aromatizante' => 'Aromatizante', 'edulcorante' => 'Edulcorante', 'corante' => 'Corante',
        'conservante' => 'Conservante', 'veiculo' => 'Veículo', 'capsula' => 'Cápsula vazia', 'embalagem' => 'Embalagem', 'outro' => 'Outro'];
    public const PORCAO_UNIDADES = ['cápsulas', 'comprimidos', 'g', 'ml', 'sachês', 'colheres', 'gotas', 'unidades'];

    /** Massa em kg de cada unidade de peso; volume em L */
    private const PESO_KG = ['mcg' => 1e-9, 'mg' => 1e-6, 'g' => 1e-3, 'kg' => 1.0];
    private const VOLUME_L = ['ml' => 1e-3, 'l' => 1.0];

    /** Fator para converter 1 [unidade do item] na unidade da matéria-prima (null = não dá para converter) */
    public static function fator(string $deItem, string $paraMp): ?float
    {
        $de = strtolower($deItem);
        $para = strtolower($paraMp);
        if (isset(self::PESO_KG[$de], self::PESO_KG[$para])) {
            return self::PESO_KG[$de] / self::PESO_KG[$para];
        }
        if (isset(self::VOLUME_L[$de], self::VOLUME_L[$para])) {
            return self::VOLUME_L[$de] / self::VOLUME_L[$para];
        }
        return $de === $para ? 1.0 : null;
    }

    /** Massa em mg (só unidades de peso) */
    private static function mg(float $qtd, string $unidade): ?float
    {
        $f = self::PESO_KG[strtolower($unidade)] ?? null;
        return $f === null ? null : $qtd * $f / 1e-6;
    }

    public static function buscar(int $id): ?array
    {
        $f = Banco::um('SELECT f.*, p.nome AS produto, p.slug AS produto_slug, u.nome AS responsavel, a.nome AS aprovador, dp.nome AS projeto
            FROM formulas f LEFT JOIN produtos p ON p.id = f.produto_id LEFT JOIN usuarios u ON u.id = f.responsavel_id
            LEFT JOIN usuarios a ON a.id = f.aprovado_por LEFT JOIN dp_projetos dp ON dp.id = f.dp_projeto_id WHERE f.id = ?', [$id]);
        if (!$f) {
            return null;
        }
        $f['itens'] = self::itens($id);
        return $f;
    }

    public static function itens(int $id): array
    {
        return Banco::todos('SELECT i.*, m.nome AS mp_nome, m.codigo AS mp_codigo, m.unidade AS mp_unidade, m.custo_unitario, m.estoque, m.fornecedor, m.categoria, m.cas_ins
            FROM formula_itens i LEFT JOIN materias_primas m ON m.id = i.materia_prima_id WHERE i.formula_id = ? ORDER BY i.ordem, i.id', [$id]);
    }

    /** Lista com o resumo de cada fórmula (só a última versão de cada código, a não ser que peça todas) */
    public static function lista(array $filtros = []): array
    {
        $w = ['1'];
        $par = [];
        if (!empty($filtros['situacao']) && isset(self::SITUACOES[$filtros['situacao']])) {
            $w[] = 'f.situacao = ?';
            $par[] = $filtros['situacao'];
        }
        if (!empty($filtros['forma']) && isset(self::FORMAS[$filtros['forma']])) {
            $w[] = 'f.forma = ?';
            $par[] = $filtros['forma'];
        }
        if (!empty($filtros['projeto'])) {
            $w[] = 'f.dp_projeto_id IS NOT NULL';
        }
        if (!empty($filtros['busca'])) {
            $w[] = '(f.nome LIKE ? OR f.codigo LIKE ?)';
            array_push($par, '%' . $filtros['busca'] . '%', '%' . $filtros['busca'] . '%');
        }
        if (empty($filtros['todas_versoes'])) {
            $w[] = 'f.versao = (SELECT MAX(v.versao) FROM formulas v WHERE v.codigo = f.codigo)';
        }
        $lista = Banco::todos('SELECT f.*, p.nome AS produto, u.nome AS responsavel FROM formulas f LEFT JOIN produtos p ON p.id = f.produto_id
            LEFT JOIN usuarios u ON u.id = f.responsavel_id WHERE ' . implode(' AND ', $w) . ' ORDER BY f.atualizado_em DESC LIMIT 200', $par);
        foreach ($lista as &$f) {
            $f['calculo'] = self::calcular($f, self::itens((int) $f['id']));
        }
        return $lista;
    }

    /**
     * Contas da fórmula. Devolve: itens (com % e custo), massa_porcao_mg, mg_por_unidade_dose, capsulas_necessarias, cabe,
     * custo_porcao, custo_embalagem, custo_lote, faltas (estoque para o lote piloto), a_cadastrar, sem_conversao, sem_custo.
     */
    public static function calcular(array $f, array $itens): array
    {
        $porcoes = max(1, (int) $f['porcoes_embalagem']);
        $lote = max(1, (int) ($f['lote_piloto'] ?? 100));
        $massa = 0.0;
        foreach ($itens as $i) {
            if ($i['base'] === 'porcao' && !in_array($i['funcao'], ['capsula', 'embalagem'], true)) {
                $massa += self::mg((float) $i['quantidade'], $i['unidade']) ?? 0;
            }
        }
        $r = ['itens' => [], 'massa_porcao_mg' => round($massa, 3), 'custo_porcao' => 0.0, 'custo_embalagem' => 0.0, 'faltas' => [], 'a_cadastrar' => [], 'sem_conversao' => [], 'sem_custo' => []];
        foreach ($itens as $i) {
            $nome = $i['mp_nome'] ?? $i['nome_livre'] ?? 'Ingrediente';
            $mgItem = $i['base'] === 'porcao' && !in_array($i['funcao'], ['capsula', 'embalagem'], true) ? self::mg((float) $i['quantidade'], $i['unidade']) : null;
            $linha = $i + ['nome' => $nome, 'pct' => $mgItem !== null && $massa > 0 ? $mgItem / $massa * 100 : null, 'por_embalagem_mp' => null, 'custo_embalagem' => null];
            if (!$i['materia_prima_id']) {
                $r['a_cadastrar'][] = $nome;
            } else {
                $fator = self::fator($i['unidade'], (string) $i['mp_unidade']);
                if ($fator === null) {
                    $r['sem_conversao'][] = $nome . ' (' . $i['unidade'] . ' → ' . $i['mp_unidade'] . ')';
                } else {
                    $porEmb = (float) $i['quantidade'] * $fator * ($i['base'] === 'porcao' ? $porcoes : 1);
                    $linha['por_embalagem_mp'] = $porEmb;
                    $custo = $porEmb * (float) $i['custo_unitario'];
                    $linha['custo_embalagem'] = $custo;
                    $r['custo_embalagem'] += $custo;
                    if ((float) $i['custo_unitario'] <= 0) {
                        $r['sem_custo'][] = $nome;
                    }
                    $precisa = $porEmb * $lote;
                    if ($precisa > (float) $i['estoque']) {
                        $r['faltas'][] = ['nome' => $nome, 'precisa' => $precisa, 'tem' => (float) $i['estoque'], 'unidade' => $i['mp_unidade']];
                    }
                }
            }
            $r['itens'][] = $linha;
        }
        $r['custo_porcao'] = $r['custo_embalagem'] / $porcoes;
        $r['custo_lote'] = $r['custo_embalagem'] * $lote;
        // Cápsula / comprimido: quanto vai em cada unidade de dose
        $doses = in_array($f['porcao_unidade'], ['cápsulas', 'comprimidos', 'sachês', 'unidades'], true) ? max(0.001, (float) $f['porcao_qtd']) : null;
        $r['mg_por_unidade_dose'] = $doses ? $massa / $doses : null;
        $cap = (float) ($f['capacidade_capsula_mg'] ?? 0);
        $r['capsulas_necessarias'] = $f['forma'] === 'capsula' && $cap > 0 ? (int) ceil($massa / $cap) : null;
        $r['cabe'] = $f['forma'] === 'capsula' && $cap > 0 && $doses ? ($massa / $doses) <= $cap : null;
        $r['ocupacao_capsula_pct'] = $f['forma'] === 'capsula' && $cap > 0 && $doses ? $massa / $doses / $cap * 100 : null;
        return $r;
    }

    /** Cria uma fórmula nova (rascunho) */
    public static function criar(array $d, int $usuarioId): int
    {
        $proximo = (int) Banco::valor("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 3) AS UNSIGNED)), 0) + 1 FROM formulas");
        $dados = self::cabecalho($d) + ['codigo' => sprintf('F-%04d', $proximo), 'versao' => 1, 'situacao' => 'rascunho',
            'responsavel_id' => $usuarioId, 'criado_por' => $usuarioId];
        if (!empty($d['dp_projeto_id']) && ($proj = Banco::um('SELECT id, produto_id FROM dp_projetos WHERE id = ?', [(int) $d['dp_projeto_id']]))) {
            $dados['dp_projeto_id'] = (int) $proj['id'];
            $dados['produto_id'] ??= $proj['produto_id'] ? (int) $proj['produto_id'] : null;
        }
        $id = Banco::inserir('formulas', $dados);
        self::historico($id, $usuarioId, 'Fórmula criada (' . $dados['codigo'] . ', versão 1).');
        Auditoria::registrar('formula', $id, 'criar', null, $dados);
        return $id;
    }

    /** Campos do cabeçalho vindos do formulário (validados) */
    private static function cabecalho(array $d): array
    {
        $num = fn($v) => (float) str_replace(',', '.', (string) $v);
        $produto = (int) ($d['produto_id'] ?? 0);
        return array_filter([
            'nome' => mb_substr(trim((string) ($d['nome'] ?? '')), 0, 150) ?: 'Fórmula sem nome',
            'forma' => isset(self::FORMAS[$d['forma'] ?? '']) ? $d['forma'] : 'capsula',
            'porcao_qtd' => max(0.001, $num($d['porcao_qtd'] ?? 1)),
            'porcao_unidade' => in_array($d['porcao_unidade'] ?? '', self::PORCAO_UNIDADES, true) ? $d['porcao_unidade'] : 'cápsulas',
            'porcoes_embalagem' => max(1, (int) ($d['porcoes_embalagem'] ?? 30)),
            'capacidade_capsula_mg' => $num($d['capacidade_capsula_mg'] ?? 0) > 0 ? $num($d['capacidade_capsula_mg']) : null,
            'lote_piloto' => max(1, (int) ($d['lote_piloto'] ?? 100)),
            'objetivo' => mb_substr(trim((string) ($d['objetivo'] ?? '')), 0, 2000),
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 4000),
            'produto_id' => $produto > 0 && Banco::valor('SELECT 1 FROM produtos WHERE id = ?', [$produto]) ? $produto : null,
        ], fn($v) => $v !== null) + ['produto_id' => null, 'capacidade_capsula_mg' => null];
    }

    /** Salva cabeçalho e itens (só rascunho ou em teste) */
    public static function salvar(int $id, array $d, int $usuarioId): void
    {
        $f = self::buscar($id);
        if (!$f) {
            throw new RuntimeException('Fórmula não encontrada.');
        }
        if (!in_array($f['situacao'], ['rascunho', 'em_teste'], true)) {
            throw new RuntimeException('Fórmula aprovada ou arquivada não muda: crie uma nova versão.');
        }
        $itens = [];
        foreach ((array) ($d['itens'] ?? []) as $i => $it) {
            $mp = (int) ($it['materia_prima_id'] ?? 0);
            $livre = mb_substr(trim((string) ($it['nome_livre'] ?? '')), 0, 150);
            $qtd = (float) str_replace(',', '.', (string) ($it['quantidade'] ?? 0));
            if ((!$mp && $livre === '') || $qtd <= 0) {
                continue; // linha vazia
            }
            if ($mp && !Banco::valor('SELECT 1 FROM materias_primas WHERE id = ?', [$mp])) {
                throw new RuntimeException('Uma matéria-prima não existe mais. Recarregue a tela.');
            }
            $itens[] = ['materia_prima_id' => $mp ?: null, 'nome_livre' => $mp ? null : $livre, 'quantidade' => round($qtd, 6),
                'unidade' => isset(self::UNIDADES[$it['unidade'] ?? '']) ? $it['unidade'] : 'mg',
                'base' => ($it['base'] ?? '') === 'embalagem' ? 'embalagem' : 'porcao',
                'funcao' => isset(self::FUNCOES[$it['funcao'] ?? '']) ? $it['funcao'] : 'ativo',
                'observacao' => mb_substr(trim((string) ($it['observacao'] ?? '')), 0, 255) ?: null, 'ordem' => count($itens) + 1];
        }
        $cab = self::cabecalho($d + ['nome' => $f['nome']]);
        Banco::transacao(function () use ($id, $cab, $itens, $f, $usuarioId) {
            Banco::atualizar('formulas', $id, $cab);
            Banco::executar('DELETE FROM formula_itens WHERE formula_id = ?', [$id]);
            foreach ($itens as $it) {
                Banco::inserir('formula_itens', ['formula_id' => $id] + $it);
            }
            Auditoria::registrar('formula', $id, 'salvar', ['itens' => count($f['itens'])], ['itens' => count($itens)] + $cab);
        });
    }

    /** Muda a situação (rascunho ↔ em teste, arquivar) */
    public static function mudarSituacao(int $id, string $situacao, int $usuarioId): void
    {
        $f = self::buscar($id);
        if (!$f || !in_array($situacao, ['rascunho', 'em_teste', 'arquivada'], true)) {
            throw new RuntimeException('Situação inválida.');
        }
        if ($f['situacao'] === 'aprovada' && $situacao !== 'arquivada') {
            throw new RuntimeException('Fórmula aprovada só pode ser arquivada (para mudar, crie uma nova versão).');
        }
        Banco::atualizar('formulas', $id, ['situacao' => $situacao]);
        self::historico($id, $usuarioId, 'Situação: ' . self::SITUACOES[$situacao][0] . '.');
    }

    /** Copia a fórmula como nova versão (rascunho); a original fica guardada como está */
    public static function novaVersao(int $id, int $usuarioId): int
    {
        $f = self::buscar($id);
        if (!$f) {
            throw new RuntimeException('Fórmula não encontrada.');
        }
        $versao = (int) Banco::valor('SELECT MAX(versao) FROM formulas WHERE codigo = ?', [$f['codigo']]) + 1;
        return Banco::transacao(function () use ($f, $versao, $usuarioId, $id) {
            $copia = array_intersect_key($f, array_flip(['codigo', 'nome', 'forma', 'porcao_qtd', 'porcao_unidade', 'porcoes_embalagem', 'capacidade_capsula_mg',
                'lote_piloto', 'objetivo', 'produto_id', 'dp_projeto_id', 'observacoes']));
            $novo = Banco::inserir('formulas', $copia + ['versao' => $versao, 'formula_origem_id' => $id, 'situacao' => 'rascunho',
                'responsavel_id' => $usuarioId, 'criado_por' => $usuarioId]);
            foreach ($f['itens'] as $it) {
                Banco::inserir('formula_itens', ['formula_id' => $novo] + array_intersect_key($it, array_flip(['materia_prima_id', 'nome_livre', 'quantidade', 'unidade', 'base', 'funcao', 'ordem', 'observacao'])));
            }
            self::historico($novo, $usuarioId, "Versão $versao criada a partir da versão {$f['versao']}.");
            return $novo;
        });
    }

    /** O que mudou entre duas versões: [cabeçalho => [campo, antes, depois]], itens adicionados/retirados/alterados */
    public static function comparar(int $id, int $outroId): array
    {
        $a = self::buscar($outroId);
        $b = self::buscar($id);
        if (!$a || !$b) {
            throw new RuntimeException('Versão não encontrada.');
        }
        $campos = ['nome' => 'Nome', 'forma' => 'Forma', 'porcao_qtd' => 'Porção', 'porcao_unidade' => 'Unidade da porção', 'porcoes_embalagem' => 'Porções por embalagem',
            'capacidade_capsula_mg' => 'Capacidade da cápsula (mg)'];
        $cab = [];
        foreach ($campos as $c => $nome) {
            if ((string) $a[$c] !== (string) $b[$c]) {
                $cab[] = [$nome, $a[$c], $b[$c]];
            }
        }
        $chave = fn($i) => $i['materia_prima_id'] ? 'm' . $i['materia_prima_id'] : 'l' . mb_strtolower((string) $i['nome_livre']);
        $ia = array_column(array_map(fn($i) => $i + ['k' => $chave($i)], $a['itens']), null, 'k');
        $ib = array_column(array_map(fn($i) => $i + ['k' => $chave($i)], $b['itens']), null, 'k');
        $nome = fn($i) => $i['mp_nome'] ?? $i['nome_livre'];
        $qtd = fn($i) => rtrim(rtrim(number_format((float) $i['quantidade'], 6, ',', '.'), '0'), ',') . ' ' . $i['unidade'] . ($i['base'] === 'embalagem' ? '/emb.' : '');
        $r = ['cabecalho' => $cab, 'entrou' => [], 'saiu' => [], 'mudou' => []];
        foreach ($ib as $k => $i) {
            if (!isset($ia[$k])) {
                $r['entrou'][] = $nome($i) . ': ' . $qtd($i);
            } elseif ($qtd($ia[$k]) !== $qtd($i) || $ia[$k]['funcao'] !== $i['funcao']) {
                $r['mudou'][] = [$nome($i), $qtd($ia[$k]), $qtd($i)];
            }
        }
        foreach ($ia as $k => $i) {
            if (!isset($ib[$k])) {
                $r['saiu'][] = $nome($i) . ': ' . $qtd($i);
            }
        }
        $calcA = self::calcular($a, $a['itens']);
        $calcB = self::calcular($b, $b['itens']);
        $r['custo'] = [$calcA['custo_embalagem'], $calcB['custo_embalagem']];
        $r['massa'] = [$calcA['massa_porcao_mg'], $calcB['massa_porcao_mg']];
        $r['versoes'] = [$a['versao'], $b['versao']];
        return $r;
    }

    /** O que impede aprovar (lista vazia = pode) */
    public static function pendenciasAprovacao(array $f): array
    {
        $c = self::calcular($f, $f['itens']);
        $p = [];
        if (!$f['produto_id']) {
            $p[] = 'Ligue a fórmula a um produto (a ficha técnica é do produto).';
        }
        if (!$f['itens']) {
            $p[] = 'A fórmula não tem ingredientes.';
        }
        if ($c['a_cadastrar']) {
            $p[] = 'Cadastre as matérias-primas: ' . implode(', ', $c['a_cadastrar']) . ' (Produção › Matérias-primas) e escolha na fórmula.';
        }
        if ($c['sem_conversao']) {
            $p[] = 'Unidade que não converte para a unidade da matéria-prima: ' . implode(', ', $c['sem_conversao']) . '.';
        }
        if ($f['situacao'] === 'aprovada' || $f['situacao'] === 'arquivada') {
            $p[] = 'Esta versão já está ' . mb_strtolower(self::SITUACOES[$f['situacao']][0]) . '.';
        }
        return $p;
    }

    /**
     * Aprova: a fórmula vira a FICHA TÉCNICA do produto (quantidade por unidade fabricada, na unidade da matéria-prima).
     * A ficha anterior fica no histórico; outras versões aprovadas do mesmo código são arquivadas; a Produção é avisada.
     */
    public static function aprovar(int $id, int $usuarioId): array
    {
        $f = self::buscar($id);
        if (!$f) {
            throw new RuntimeException('Fórmula não encontrada.');
        }
        if ($pend = self::pendenciasAprovacao($f)) {
            throw new RuntimeException(implode(' ', $pend));
        }
        $c = self::calcular($f, $f['itens']);
        $linhas = [];
        foreach ($c['itens'] as $i) {
            $linhas[(int) $i['materia_prima_id']] = ($linhas[(int) $i['materia_prima_id']] ?? 0) + (float) $i['por_embalagem_mp'];
        }
        $antes = ProducaoService::ficha((int) $f['produto_id']);
        Banco::transacao(function () use ($f, $id, $usuarioId, $linhas, $antes) {
            ProducaoService::salvarFicha((int) $f['produto_id'], $linhas);
            Banco::atualizar('formulas', $id, ['situacao' => 'aprovada', 'aprovado_por' => $usuarioId, 'aprovado_em' => date('Y-m-d H:i:s')]);
            Banco::executar("UPDATE formulas SET situacao = 'arquivada' WHERE codigo = ? AND id <> ? AND situacao = 'aprovada'", [$f['codigo'], $id]);
            self::historico($id, $usuarioId, 'Aprovada: virou a ficha técnica de ' . $f['produto'] . ' (' . count($linhas) . ' matéria(s)-prima(s) por unidade).'
                . ($antes ? ' A ficha anterior ficou guardada aqui.' : ''), $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null);
            if ($f['dp_projeto_id']) {
                Banco::inserir('dp_historico', ['projeto_id' => (int) $f['dp_projeto_id'], 'usuario_id' => $usuarioId, 'tipo' => 'sistema',
                    'texto' => 'Fórmula ' . $f['codigo'] . ' v' . $f['versao'] . ' aprovada: a ficha técnica do produto foi preenchida.']);
            }
            Auditoria::registrar('formula', $id, 'aprovar', ['ficha_antes' => array_column($antes, 'quantidade', 'materia_prima_id')], ['ficha' => $linhas]);
        });
        NotificacaoService::avisar(NotificacaoService::destinatarios('producao.gerenciar', 'producao'), 'formula_aprovada',
            'Fórmula aprovada: ' . $f['nome'] . ' — a ficha técnica de ' . $f['produto'] . ' mudou', '/formulas/' . $id, ['formula_id' => $id], $usuarioId);
        return $linhas;
    }

    public static function registrarTeste(int $id, array $d, int $usuarioId): void
    {
        $resultado = in_array($d['resultado'] ?? '', ['aprovado', 'reprovado', 'ajustar'], true) ? $d['resultado'] : 'ajustar';
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['data'] ?? '')) ? $d['data'] : date('Y-m-d');
        Banco::inserir('formula_testes', ['formula_id' => $id, 'data' => $data, 'lote_tamanho' => (int) ($d['lote_tamanho'] ?? 0) ?: null, 'resultado' => $resultado,
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 4000), 'criado_por' => $usuarioId]);
        if (Banco::valor('SELECT situacao FROM formulas WHERE id = ?', [$id]) === 'rascunho') {
            Banco::atualizar('formulas', $id, ['situacao' => 'em_teste']);
        }
        self::historico($id, $usuarioId, 'Teste registrado (' . data_br($data) . '): ' . ['aprovado' => 'aprovado', 'reprovado' => 'reprovado', 'ajustar' => 'precisa ajustar'][$resultado] . '.');
    }

    public static function historico(int $id, ?int $usuarioId, string $texto, ?string $dados = null): void
    {
        Banco::inserir('formula_historico', ['formula_id' => $id, 'usuario_id' => $usuarioId, 'texto' => mb_substr($texto, 0, 500), 'dados_json' => $dados]);
    }

    /** Resumo para o especialista em Formulação (IA): tudo da fórmula com as contas */
    public static function paraEspecialista(int $id, ?int $lote = null): array
    {
        $f = self::buscar($id);
        if (!$f) {
            return ['erro' => 'Fórmula não encontrada.'];
        }
        if ($lote) {
            $f['lote_piloto'] = $lote;
        }
        $c = self::calcular($f, $f['itens']);
        $n = fn($v, $casas = 2) => $v === null ? null : rtrim(rtrim(number_format((float) $v, $casas, ',', '.'), '0'), ',');
        return [
            'formula' => $f['codigo'] . ' v' . $f['versao'] . ' — ' . $f['nome'], 'situacao' => self::SITUACOES[$f['situacao']][0],
            'forma' => self::FORMAS[$f['forma']] ?? $f['forma'], 'porcao' => $n($f['porcao_qtd'], 3) . ' ' . $f['porcao_unidade'], 'porcoes_por_embalagem' => (int) $f['porcoes_embalagem'],
            'capacidade_capsula_mg' => $f['capacidade_capsula_mg'] ? (float) $f['capacidade_capsula_mg'] : null, 'objetivo' => $f['objetivo'], 'produto' => $f['produto'],
            'ingredientes' => array_map(fn($i) => ['nome' => $i['nome'], 'funcao' => self::FUNCOES[$i['funcao']] ?? $i['funcao'],
                'quantidade' => $n($i['quantidade'], 6) . ' ' . $i['unidade'] . ($i['base'] === 'embalagem' ? ' por embalagem' : ' por porção'),
                'pct_da_porcao' => $i['pct'] !== null ? $n($i['pct'], 2) . '%' : null, 'materia_prima_cadastrada' => (bool) $i['materia_prima_id'],
                'custo_por_embalagem' => $i['custo_embalagem'] !== null ? dinheiro((float) $i['custo_embalagem']) : null,
                'estoque' => $i['materia_prima_id'] ? $n($i['estoque'], 3) . ' ' . $i['mp_unidade'] : null, 'fornecedor' => $i['fornecedor'] ?? null], $c['itens']),
            'massa_da_porcao_mg' => $n($c['massa_porcao_mg'], 1), 'mg_por_capsula_ou_unidade' => $n($c['mg_por_unidade_dose'], 1),
            'ocupacao_da_capsula_pct' => $n($c['ocupacao_capsula_pct'], 1), 'capsulas_necessarias_por_porcao' => $c['capsulas_necessarias'],
            'custo_por_porcao' => dinheiro($c['custo_porcao']), 'custo_por_embalagem' => dinheiro($c['custo_embalagem']),
            'lote_piloto' => (int) $f['lote_piloto'] . ' unidades: ' . dinheiro($c['custo_lote']),
            'falta_estoque_para_o_lote' => array_map(fn($x) => $x['nome'] . ': precisa ' . $n($x['precisa'], 3) . ' ' . $x['unidade'] . ', tem ' . $n($x['tem'], 3), $c['faltas']),
            'ingredientes_a_cadastrar' => $c['a_cadastrar'], 'sem_custo_cadastrado' => $c['sem_custo'], 'link' => '/formulas/' . $id,
        ];
    }
}
