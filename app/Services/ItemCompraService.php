<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use RuntimeException;

/**
 * Itens comprados (tabela materias_primas): matéria-prima, embalagem, rótulo e uso e consumo.
 *  - Fornecedores: vários por item, com último preço, prazo e o principal.
 *  - Documentos: laudo, ficha técnica, FISPQ, certificado e NF (storage/documentos/itens, fora da web).
 *  - Números da página do item: recorrência (compra a cada X dias), dura em média, próxima compra, gasto em 12 meses.
 */
class ItemCompraService
{
    public const TIPOS = ['materia_prima' => 'Matéria-prima', 'embalagem' => 'Embalagem', 'consumo' => 'Uso e consumo'];
    public const TIPOS_DOCUMENTO = ['laudo' => 'Laudo', 'ficha_tecnica' => 'Ficha técnica', 'fispq' => 'FISPQ', 'certificado' => 'Certificado', 'nf' => 'Nota fiscal', 'outro' => 'Outro'];
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'text/xml' => 'xml', 'application/xml' => 'xml'];
    private const TAMANHO_MAXIMO = 15 * 1024 * 1024;

    public static function pasta(): string
    {
        return RAIZ . '/storage/documentos/itens';
    }

    public static function categorias(): array
    {
        return array_values(array_filter(array_map('trim', (array) Config::get('compras.categorias', ['Escritório', 'Limpeza', 'Outros']))));
    }

    /** Nome do tipo do item (rótulo é uma embalagem marcada) */
    public static function tipoDe(array $m): string
    {
        return !empty($m['rotulo']) ? 'Rótulo' : (self::TIPOS[$m['tipo']] ?? $m['tipo']);
    }

    // ------------------------------------------------------------------
    // Fornecedores
    // ------------------------------------------------------------------

    public static function fornecedores(int $itemId, bool $soAtivos = true): array
    {
        return Banco::todos('SELECT * FROM mp_fornecedores WHERE materia_prima_id = ?' . ($soAtivos ? ' AND ativo = 1' : '') . ' ORDER BY principal DESC, fornecedor', [$itemId]);
    }

    /** Fornecedor do item pelo nome (cria se não existir). Devolve o id */
    public static function fornecedorPorNome(int $itemId, string $nome, ?string $cnpj = null): int
    {
        $nome = mb_substr(trim($nome), 0, 160);
        if ($nome === '') {
            throw new RuntimeException('Informe o fornecedor.');
        }
        $id = (int) Banco::valor('SELECT id FROM mp_fornecedores WHERE materia_prima_id = ? AND fornecedor = ?', [$itemId, $nome]);
        if ($id) {
            Banco::executar('UPDATE mp_fornecedores SET ativo = 1, cnpj = COALESCE(cnpj, ?) WHERE id = ?', [$cnpj ?: null, $id]);
            return $id;
        }
        $principal = !Banco::valor('SELECT 1 FROM mp_fornecedores WHERE materia_prima_id = ? AND ativo = 1 AND principal = 1', [$itemId]);
        $id = Banco::inserir('mp_fornecedores', array_filter(['materia_prima_id' => $itemId, 'fornecedor' => $nome, 'cnpj' => $cnpj ?: null, 'principal' => $principal ? 1 : 0], fn($v) => $v !== null));
        if ($principal) {
            Banco::executar('UPDATE materias_primas SET fornecedor = ? WHERE id = ?', [$nome, $itemId]);
        }
        return $id;
    }

    /** Cria ou edita um fornecedor do item. $d: fornecedor, cnpj, contato, telefone, email, codigo_no_fornecedor, ultimo_preco, prazo_dias, observacoes */
    public static function salvarFornecedor(int $itemId, ?int $id, array $d, ?int $usuarioId): int
    {
        $dados = [
            'fornecedor' => mb_substr(trim((string) ($d['fornecedor'] ?? '')), 0, 160),
            'cnpj' => mb_substr(preg_replace('/[^\d.\/-]/', '', (string) ($d['cnpj'] ?? '')), 0, 18) ?: null,
            'contato' => mb_substr(trim((string) ($d['contato'] ?? '')), 0, 120) ?: null,
            'telefone' => mb_substr(trim((string) ($d['telefone'] ?? '')), 0, 30) ?: null,
            'email' => mb_substr(trim((string) ($d['email'] ?? '')), 0, 160) ?: null,
            'codigo_no_fornecedor' => mb_substr(trim((string) ($d['codigo_no_fornecedor'] ?? '')), 0, 60) ?: null,
            'ultimo_preco' => trim((string) ($d['ultimo_preco'] ?? '')) !== '' ? max(0, ProducaoService::numero((string) $d['ultimo_preco'])) : null,
            'prazo_dias' => ctype_digit((string) ($d['prazo_dias'] ?? '')) ? (int) $d['prazo_dias'] : null,
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 500) ?: null,
        ];
        if ($dados['fornecedor'] === '') {
            throw new RuntimeException('Informe o nome do fornecedor.');
        }
        if ($id) {
            $antes = Banco::um('SELECT * FROM mp_fornecedores WHERE id = ? AND materia_prima_id = ?', [$id, $itemId]);
            if (!$antes) {
                throw new RuntimeException('Fornecedor não encontrado.');
            }
            Banco::atualizar('mp_fornecedores', $id, $dados);
            Auditoria::registrar('mp_fornecedor', $id, 'editar', $antes, $dados);
            return $id;
        }
        if (Banco::valor('SELECT 1 FROM mp_fornecedores WHERE materia_prima_id = ? AND fornecedor = ? AND ativo = 1', [$itemId, $dados['fornecedor']])) {
            throw new RuntimeException('Este fornecedor já está na lista do item.');
        }
        $novo = self::fornecedorPorNome($itemId, $dados['fornecedor']);
        Banco::atualizar('mp_fornecedores', $novo, $dados);
        Auditoria::registrar('mp_fornecedor', $novo, 'criar', null, $dados + ['materia_prima_id' => $itemId]);
        return $novo;
    }

    public static function principal(int $itemId, int $id): void
    {
        $f = Banco::um('SELECT * FROM mp_fornecedores WHERE id = ? AND materia_prima_id = ? AND ativo = 1', [$id, $itemId]);
        if (!$f) {
            throw new RuntimeException('Fornecedor não encontrado.');
        }
        Banco::executar('UPDATE mp_fornecedores SET principal = (id = ?) WHERE materia_prima_id = ?', [$id, $itemId]);
        Banco::executar('UPDATE materias_primas SET fornecedor = ? WHERE id = ?', [$f['fornecedor'], $itemId]);
        Auditoria::registrar('mp_fornecedor', $id, 'principal', null, ['materia_prima_id' => $itemId]);
    }

    public static function removerFornecedor(int $itemId, int $id): void
    {
        Banco::executar('UPDATE mp_fornecedores SET ativo = 0, principal = 0 WHERE id = ? AND materia_prima_id = ?', [$id, $itemId]);
        Auditoria::registrar('mp_fornecedor', $id, 'desativar', null, ['materia_prima_id' => $itemId]);
    }

    // ------------------------------------------------------------------
    // Documentos
    // ------------------------------------------------------------------

    public static function documentos(int $itemId): array
    {
        return Banco::todos('SELECT d.*, f.fornecedor, u.nome AS enviado_por_nome FROM mp_documentos d LEFT JOIN mp_fornecedores f ON f.id = d.fornecedor_id
            LEFT JOIN usuarios u ON u.id = d.enviado_por WHERE d.materia_prima_id = ? AND d.arquivado_em IS NULL
            ORDER BY d.tipo = \'nf\', d.criado_em DESC', [$itemId]);
    }

    public static function documento(int $id): ?array
    {
        return Banco::um('SELECT * FROM mp_documentos WHERE id = ?', [$id]);
    }

    /** Situação da validade: ok | vencendo (até 30 dias) | vencido | sem */
    public static function situacaoValidade(?string $validade): string
    {
        if (!$validade) {
            return 'sem';
        }
        $dias = (int) floor((strtotime($validade) - strtotime(date('Y-m-d'))) / 86400);
        return $dias < 0 ? 'vencido' : ($dias <= (int) Config::get('produtos.dias_alerta_documento', 30) ? 'vencendo' : 'ok');
    }

    /** Guarda o arquivo enviado e cria o documento. $d: tipo, titulo, validade, lote, fornecedor_id */
    public static function enviarDocumento(int $itemId, array $arquivo, array $d, ?int $usuarioId): int
    {
        $g = self::guardar($arquivo);
        $tipo = array_key_exists($d['tipo'] ?? '', self::TIPOS_DOCUMENTO) ? $d['tipo'] : 'outro';
        $id = Banco::inserir('mp_documentos', array_filter([
            'materia_prima_id' => $itemId, 'tipo' => $tipo, 'titulo' => mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 160) ?: self::TIPOS_DOCUMENTO[$tipo],
            'arquivo' => $g['arquivo'], 'nome_original' => $g['nome'], 'mime' => $g['mime'], 'tamanho' => $g['tamanho'],
            'validade' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['validade'] ?? '')) ? $d['validade'] : null,
            'lote' => mb_substr(trim((string) ($d['lote'] ?? '')), 0, 40) ?: null,
            'fornecedor_id' => (int) ($d['fornecedor_id'] ?? 0) ?: null, 'lancamento_id' => (int) ($d['lancamento_id'] ?? 0) ?: null,
            'enviado_por' => $usuarioId,
        ], fn($v) => $v !== null));
        Auditoria::registrar('mp_documento', $id, 'enviar', null, ['materia_prima_id' => $itemId, 'tipo' => $tipo]);
        return $id;
    }

    /** NF já guardada (lançamento de compra): ligada a vários itens sem copiar o arquivo */
    public static function anexarNf(int $itemId, array $nf, int $lancamentoId, ?int $fornecedorId, ?int $usuarioId, string $titulo): void
    {
        Banco::inserir('mp_documentos', array_filter(['materia_prima_id' => $itemId, 'tipo' => 'nf', 'titulo' => mb_substr($titulo, 0, 160),
            'arquivo' => $nf['arquivo'], 'nome_original' => $nf['nome'], 'mime' => $nf['mime'], 'tamanho' => $nf['tamanho'] ?? 0,
            'fornecedor_id' => $fornecedorId, 'lancamento_id' => $lancamentoId, 'enviado_por' => $usuarioId], fn($v) => $v !== null));
    }

    public static function arquivarDocumento(int $itemId, int $id): void
    {
        Banco::executar('UPDATE mp_documentos SET arquivado_em = NOW() WHERE id = ? AND materia_prima_id = ?', [$id, $itemId]);
        Auditoria::registrar('mp_documento', $id, 'arquivar', null, ['materia_prima_id' => $itemId]);
    }

    /** Documentos de itens vencidos ou vencendo (alertas da Produção e da Qualidade) */
    public static function documentosVencendo(int $limite = 20): array
    {
        $dias = (int) Config::get('produtos.dias_alerta_documento', 30);
        return Banco::todos("SELECT d.id, d.titulo, d.tipo, d.validade, m.id AS item_id, m.nome AS item FROM mp_documentos d JOIN materias_primas m ON m.id = d.materia_prima_id
            WHERE d.arquivado_em IS NULL AND d.validade IS NOT NULL AND d.validade <= CURDATE() + INTERVAL ? DAY AND m.ativo = 1
            ORDER BY d.validade LIMIT " . (int) $limite, [$dias]);
    }

    /**
     * Guarda um arquivo enviado (PDF, imagem ou XML da NF) fora da pasta pública.
     * Devolve [arquivo, nome, mime, tamanho]. Lança RuntimeException com a mensagem para a pessoa.
     */
    public static function guardar(array $arquivo): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Escolha o arquivo.');
        }
        if ($arquivo['error'] === UPLOAD_ERR_INI_SIZE || $arquivo['error'] === UPLOAD_ERR_FORM_SIZE || (int) $arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new RuntimeException('O arquivo passa de 15 MB.');
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new RuntimeException('Não foi possível receber o arquivo. Tente de novo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::MIMES[$mime])) {
            throw new RuntimeException('Envie PDF, imagem (JPG, PNG) ou o XML da nota.');
        }
        if (!is_dir(self::pasta()) && !mkdir(self::pasta(), 0750, true)) {
            throw new RuntimeException('A pasta dos documentos não pôde ser criada.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], self::pasta() . '/' . $nome)) {
            throw new RuntimeException('Não foi possível guardar o arquivo.');
        }
        return ['arquivo' => $nome, 'nome' => mb_substr(basename((string) $arquivo['name']), 0, 200) ?: 'documento.' . self::MIMES[$mime], 'mime' => $mime, 'tamanho' => (int) $arquivo['size']];
    }

    // ------------------------------------------------------------------
    // Números da página do item
    // ------------------------------------------------------------------

    /** Compras do item (linhas de NF/lançamento), mais novas primeiro */
    public static function compras(int $itemId, int $limite = 60): array
    {
        return Banco::todos("SELECT i.*, l.emissao, l.numero_nf, l.fornecedor, l.origem, l.id AS lancamento, l.arquivo
            FROM compras_lancadas_itens i JOIN compras_lancadas l ON l.id = i.lancamento_id
            WHERE i.materia_prima_id = ? AND i.lancado = 1 ORDER BY l.emissao DESC, l.id DESC LIMIT " . (int) $limite, [$itemId]);
    }

    /**
     * Recorrência e duração: intervalo médio entre as compras (= quanto uma compra costuma durar),
     * próxima compra prevista, preço médio e gasto em 12 meses, compras por mês (12 meses).
     */
    public static function numeros(array $m): array
    {
        $datas = array_column(Banco::todos("SELECT DISTINCT l.emissao FROM compras_lancadas_itens i JOIN compras_lancadas l ON l.id = i.lancamento_id
            WHERE i.materia_prima_id = ? AND i.lancado = 1 ORDER BY l.emissao", [$m['id']]), 'emissao');
        $intervalos = [];
        for ($i = 1; $i < count($datas); $i++) {
            $intervalos[] = (strtotime($datas[$i]) - strtotime($datas[$i - 1])) / 86400;
        }
        $intervalo = $intervalos ? (int) round(array_sum($intervalos) / count($intervalos)) : null;
        $ultima = $datas ? end($datas) : null;
        $r = Banco::um("SELECT COALESCE(SUM(i.total), 0) AS gasto, COALESCE(SUM(i.quantidade), 0) AS qtd, COUNT(DISTINCT l.id) AS compras
            FROM compras_lancadas_itens i JOIN compras_lancadas l ON l.id = i.lancamento_id
            WHERE i.materia_prima_id = ? AND i.lancado = 1 AND l.emissao >= CURDATE() - INTERVAL 12 MONTH", [$m['id']]);
        $porMes = [];
        foreach (Banco::todos("SELECT DATE_FORMAT(l.emissao, '%Y-%m') AS mes, SUM(i.total) AS total FROM compras_lancadas_itens i JOIN compras_lancadas l ON l.id = i.lancamento_id
            WHERE i.materia_prima_id = ? AND i.lancado = 1 AND l.emissao >= CURDATE() - INTERVAL 12 MONTH GROUP BY DATE_FORMAT(l.emissao, '%Y-%m')", [$m['id']]) as $x) {
            $porMes[$x['mes']] = (float) $x['total'];
        }
        // "Dura em média": pelo consumo registrado (matéria-prima) ou pelo intervalo entre as compras (uso e consumo)
        $dura = $m['dias_restantes'] ?? null;
        return [
            'compras_12m' => (int) $r['compras'], 'gasto_12m' => (float) $r['gasto'],
            'preco_medio' => (float) $r['qtd'] > 0 ? (float) $r['gasto'] / (float) $r['qtd'] : null,
            'intervalo' => $intervalo, 'ultima_compra' => $ultima,
            'proxima' => $ultima && $intervalo ? date('Y-m-d', strtotime("$ultima +$intervalo days")) : null,
            'dura_estoque' => $dura, 'por_mes' => $porMes,
        ];
    }

    /** Catálogo do Financeiro › Compras: todos os itens, com fornecedor principal e números de compra */
    public static function catalogo(array $f): array
    {
        $itens = ProducaoService::materias(['todos' => true, 'tipo' => $f['tipo'] ?? '', 'categoria' => $f['categoria'] ?? '', 'q' => $f['q'] ?? '']);
        if (!$itens) {
            return [];
        }
        $ids = implode(',', array_map(fn($m) => (int) $m['id'], $itens));
        $compras = [];
        foreach (Banco::todos("SELECT i.materia_prima_id AS id, COUNT(DISTINCT l.id) AS compras, MIN(l.emissao) AS primeira, MAX(l.emissao) AS ultima, SUM(i.total) AS gasto
            FROM compras_lancadas_itens i JOIN compras_lancadas l ON l.id = i.lancamento_id
            WHERE i.lancado = 1 AND i.materia_prima_id IN ($ids) AND l.emissao >= CURDATE() - INTERVAL 12 MONTH GROUP BY i.materia_prima_id") as $c) {
            $compras[(int) $c['id']] = $c;
        }
        foreach ($itens as &$m) {
            $c = $compras[(int) $m['id']] ?? null;
            $m['compras_12m'] = (int) ($c['compras'] ?? 0);
            $m['gasto_12m'] = (float) ($c['gasto'] ?? 0);
            $m['ultima_compra'] = $c['ultima'] ?? null;
            $m['intervalo'] = $c && $c['compras'] > 1 ? (int) round((strtotime($c['ultima']) - strtotime($c['primeira'])) / 86400 / ($c['compras'] - 1)) : null;
            $m['proxima'] = $m['intervalo'] ? date('Y-m-d', strtotime($m['ultima_compra'] . ' +' . $m['intervalo'] . ' days')) : null;
        }
        return $itens;
    }
}
