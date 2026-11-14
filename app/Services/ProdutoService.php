<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use RuntimeException;

/**
 * Produtos (tudo sobre o produto, para a equipe).
 *
 * Um produto só, usado em todo o sistema:
 *  - o CONTEÚDO (descrição, benefícios, modo de uso, perguntas) fica em produtos.ficha_json e aparece na Academia e no catálogo;
 *  - a COMPOSIÇÃO (ingredientes, porção, tabela nutricional) também fica no ficha_json — só a Responsável Técnica edita;
 *  - a FICHA TÉCNICA é a mesma da Produção (tabela ficha_tecnica);
 *  - as ESPECIFICAÇÕES (EAN, NCM, pesos, validade...) ficam em produto_tecnico;
 *  - os DOCUMENTOS ficam em produto_documentos, com o arquivo fora da pasta pública.
 */
class ProdutoService
{
    public const TIPOS_DOCUMENTO = [
        'laudo'        => 'Laudo de análise',
        'fispq'        => 'FISPQ / ficha de segurança',
        'anvisa'       => 'Registro ou notificação (Anvisa)',
        'certificado'  => 'Certificado',
        'especificacao' => 'Especificação técnica',
        'rotulo'       => 'Arte do rótulo',
        'prova'        => 'Prova da gráfica',
        'outro'        => 'Outro',
    ];

    /** Arquivos aceitos (conferidos pelo conteúdo, não pelo nome) */
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    public const TAMANHO_MAXIMO = 15 * 1024 * 1024;

    public const SITUACOES = [
        'sem_ficha'   => 'Sem ficha técnica',
        'sem_tecnico' => 'Sem especificações',
        'vencendo'    => 'Documento vencido ou vencendo',
        'oculto'      => 'Oculto no catálogo',
        'sem_preco'   => 'Sem preço',
    ];

    /** Pasta dos documentos (fora de /public: só sai pelo sistema, conferindo o acesso) */
    public static function pasta(): string
    {
        return RAIZ . '/storage/documentos/produtos';
    }

    /** Dias antes do vencimento para avisar (Configurações) */
    public static function diasAviso(): int
    {
        return max(1, (int) Config::get('produtos.dias_aviso_documento', 30));
    }

    /** Quem vê os documentos marcados como "interno" */
    public static function vePrivado(): bool
    {
        return Auth::pode('produtos.tecnico') || Auth::pode('producao.ver') || Auth::pode('producao.gerenciar');
    }

    /** Quem vê o custo de matéria-prima */
    public static function veCusto(): bool
    {
        return Auth::pode('producao.ver') || Auth::pode('producao.gerenciar') || Auth::pode('produtos.tecnico');
    }

    // ------------------------------------------------------------------
    // Lista (cartões)
    // ------------------------------------------------------------------

    /**
     * Produtos ativos com os selos de situação. $f: q, categoria (id), situacao (chave de SITUACOES).
     * Produtos exclusivos de um cliente de terceirização ficam de fora (eles aparecem na Terceirização).
     */
    public static function lista(array $f = []): array
    {
        $dias = self::diasAviso();
        $interno = self::vePrivado() ? '' : ' AND d.interno = 0';
        $w = ['p.ativo = 1', 'p.cliente_exclusivo_id IS NULL'];
        $par = [];
        if (($f['q'] ?? '') !== '') {
            $w[] = '(p.nome LIKE ? OR p.codigo LIKE ?)';
            array_push($par, '%' . $f['q'] . '%', $f['q'] . '%');
        }
        if ((int) ($f['categoria'] ?? 0) > 0) {
            $w[] = 'p.categoria_id = ?';
            $par[] = (int) $f['categoria'];
        }
        $w[] = match ($f['situacao'] ?? '') {
            'sem_ficha'   => 'ft.itens IS NULL',
            'sem_tecnico' => 'pt.produto_id IS NULL',
            'vencendo'    => 'COALESCE(doc.vencendo, 0) > 0',
            'oculto'      => 'p.visivel_catalogo = 0',
            'sem_preco'   => 'pr.precos IS NULL',
            default       => '1 = 1',
        };
        return Banco::todos("SELECT p.id, p.slug, p.codigo, p.nome, p.embalagem, p.visivel_catalogo, c.nome AS categoria, m.arquivo AS imagem,
                COALESCE(ft.itens, 0) AS itens_ficha, COALESCE(doc.total, 0) AS documentos, COALESCE(doc.vencendo, 0) AS vencendo,
                COALESCE(pr.precos, 0) AS precos, pt.produto_id IS NOT NULL AS tem_tecnico
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN midias m ON m.id = p.imagem_id
            LEFT JOIN produto_tecnico pt ON pt.produto_id = p.id
            LEFT JOIN (SELECT produto_id, COUNT(*) AS itens FROM ficha_tecnica GROUP BY produto_id) ft ON ft.produto_id = p.id
            LEFT JOIN (SELECT produto_id, COUNT(*) AS precos FROM produto_precos WHERE preco > 0 GROUP BY produto_id) pr ON pr.produto_id = p.id
            LEFT JOIN (SELECT d.produto_id, COUNT(*) AS total,
                              SUM(d.validade IS NOT NULL AND d.validade <= CURDATE() + INTERVAL $dias DAY) AS vencendo
                         FROM produto_documentos d WHERE d.arquivado_em IS NULL$interno GROUP BY d.produto_id) doc ON doc.produto_id = p.id
            WHERE " . implode(' AND ', $w) . ' ORDER BY p.ordem, p.nome', $par);
    }

    /** Quantos produtos em cada situação (para os botões de filtro) */
    public static function contagens(): array
    {
        $todos = self::lista();
        return [
            'sem_ficha'   => count(array_filter($todos, fn($p) => !$p['itens_ficha'])),
            'sem_tecnico' => count(array_filter($todos, fn($p) => !$p['tem_tecnico'])),
            'vencendo'    => count(array_filter($todos, fn($p) => $p['vencendo'] > 0)),
            'oculto'      => count(array_filter($todos, fn($p) => !$p['visivel_catalogo'])),
            'sem_preco'   => count(array_filter($todos, fn($p) => !$p['precos'])),
        ];
    }

    // ------------------------------------------------------------------
    // Página do produto
    // ------------------------------------------------------------------

    public static function porSlug(string $slug): ?array
    {
        $p = Banco::um('SELECT p.*, c.nome AS categoria, m.arquivo AS imagem, e.quantidade AS estoque_erp,
                   ea.quantidade AS estoque_fabrica, ea.minimo AS minimo_fabrica
              FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
              LEFT JOIN produto_estoque e ON e.produto_id = p.id LEFT JOIN estoque_acabado ea ON ea.produto_id = p.id
             WHERE p.slug = ? AND (p.ativo = 1 OR EXISTS (SELECT 1 FROM dp_projetos dp WHERE dp.produto_id = p.id))', [$slug]);
        // (ativo = 0 com projeto: produto em Desenvolvimento, preenchido pelas áreas antes do lançamento)
        if (!$p) {
            return null;
        }
        $p['ficha'] = json_decode($p['ficha_json'] ?: '{}', true) ?: [];
        return $p;
    }

    /** Preço em cada tabela ativa: [ [nome, preco|null], ... ] */
    public static function precos(int $produtoId): array
    {
        return Banco::todos('SELECT t.nome, pp.preco FROM tabelas_preco t
            LEFT JOIN produto_precos pp ON pp.tabela_preco_id = t.id AND pp.produto_id = ? WHERE t.ativa = 1 ORDER BY t.id', [$produtoId]);
    }

    public static function tecnico(int $produtoId): array
    {
        return Banco::um('SELECT pt.*, u.nome AS atualizado_por_nome FROM produto_tecnico pt LEFT JOIN usuarios u ON u.id = pt.atualizado_por
            WHERE pt.produto_id = ?', [$produtoId]) ?: [];
    }

    /** Ficha técnica separada em matérias-primas e embalagens */
    public static function fichaSeparada(int $produtoId): array
    {
        $linhas = Banco::todos('SELECT ft.materia_prima_id, ft.quantidade, m.nome, m.codigo, m.unidade, m.tipo, m.custo_unitario, m.estoque, m.estoque_minimo, m.ativo,
                ROUND(ft.quantidade * m.custo_unitario, 4) AS custo
            FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id WHERE ft.produto_id = ? ORDER BY m.nome', [$produtoId]);
        $r = ['materia_prima' => [], 'embalagem' => []];
        foreach ($linhas as $l) {
            // Quantas unidades dá para fabricar com o estoque desta matéria-prima
            $l['rende'] = (float) $l['quantidade'] > 0 ? (int) floor((float) $l['estoque'] / (float) $l['quantidade']) : null;
            $r[$l['tipo'] === 'embalagem' ? 'embalagem' : 'materia_prima'][] = $l;
        }
        return $r;
    }

    /** Matérias-primas ativas para o editor da ficha */
    public static function materiasParaFicha(): array
    {
        return Banco::todos('SELECT id, nome, codigo, unidade, tipo, custo_unitario FROM materias_primas WHERE ativo = 1 ORDER BY tipo DESC, nome');
    }

    // ------------------------------------------------------------------
    // Salvar (cada aba)
    // ------------------------------------------------------------------

    /** Divide um texto em linhas não vazias */
    private static function linhas(string $texto, int $max = 40): array
    {
        $r = array_values(array_filter(array_map('trim', preg_split('/\R/', $texto)), fn($l) => $l !== ''));
        return array_slice(array_map(fn($l) => mb_substr($l, 0, 500), $r), 0, $max);
    }

    /** Divide em parágrafos (separados por linha em branco) */
    private static function paragrafos(string $texto): array
    {
        $r = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', trim($texto))), fn($l) => $l !== ''));
        return array_slice(array_map(fn($l) => mb_substr(preg_replace('/\s*\R\s*/', ' ', $l), 0, 2000), $r), 0, 12);
    }

    private static function gravarFicha(array $produto, array $novaFicha, string $acao): void
    {
        $antes = $produto['ficha'];
        Banco::executar('UPDATE produtos SET ficha_json = ? WHERE id = ?', [json_encode($novaFicha, JSON_UNESCAPED_UNICODE), $produto['id']]);
        $mudou = [];
        foreach ($novaFicha as $k => $v) {
            if (($antes[$k] ?? null) !== $v) {
                $mudou[$k] = true;
            }
        }
        if ($mudou) {
            Auditoria::registrar('produto', (int) $produto['id'], $acao,
                array_intersect_key($antes, $mudou), array_intersect_key($novaFicha, $mudou));
        }
    }

    /**
     * Conteúdo (Marketing): o que a Academia e o catálogo mostram.
     * Perguntas frequentes: uma por linha no formato "Pergunta? | Resposta".
     */
    public static function salvarConteudo(array $produto, array $d): void
    {
        $ficha = $produto['ficha'];
        $ficha['descriptionParagraphs'] = self::paragrafos((string) ($d['descricao'] ?? ''));
        if ($ficha['descriptionParagraphs']) {
            $ficha['explain'] = $ficha['descriptionParagraphs'][0];
        }
        $ficha['benefits'] = self::linhas((string) ($d['beneficios'] ?? ''));
        $ficha['use'] = mb_substr(trim((string) ($d['uso'] ?? '')), 0, 1000);
        $ficha['care'] = mb_substr(trim((string) ($d['cuidados'] ?? '')), 0, 1500);
        $faq = [];
        foreach (self::linhas((string) ($d['faq'] ?? ''), 30) as $l) {
            [$pergunta, $resposta] = array_map('trim', explode('|', $l, 2)) + [1 => ''];
            if ($pergunta !== '' && $resposta !== '') {
                $faq[] = [$pergunta, $resposta];
            }
        }
        $ficha['faq'] = $faq;
        $vendas = is_array($ficha['sales'] ?? null) ? $ficha['sales'] : [];
        $vendas['pitch'] = mb_substr(trim((string) ($d['argumento'] ?? '')), 0, 1500);
        $vendas['highlights'] = self::linhas((string) ($d['destaques'] ?? ''), 12);
        $ficha['sales'] = $vendas;
        self::gravarFicha($produto, $ficha, 'conteudo');

        $chamada = mb_substr(trim((string) ($d['chamada'] ?? '')), 0, 255) ?: null;
        if ($chamada !== $produto['chamada']) {
            Banco::executar('UPDATE produtos SET chamada = ? WHERE id = ?', [$chamada, $produto['id']]);
            Auditoria::registrar('produto', (int) $produto['id'], 'conteudo', ['chamada' => $produto['chamada']], ['chamada' => $chamada]);
        }
    }

    /**
     * Composição (Responsável Técnica). Tabela nutricional: listas paralelas nutriente[], quantidade[], vd[].
     * Alergênicos e avisos ficam nas especificações (produto_tecnico).
     */
    public static function salvarComposicao(array $produto, array $d, ?int $usuarioId): void
    {
        $ficha = $produto['ficha'];
        $ficha['ingredients'] = mb_substr(trim((string) ($d['ingredientes'] ?? '')), 0, 3000);
        $ficha['capsule'] = mb_substr(trim((string) ($d['capsula'] ?? '')), 0, 500);
        $ficha['serving'] = mb_substr(trim((string) ($d['porcao'] ?? '')), 0, 120);
        $ficha['servings'] = mb_substr(trim((string) ($d['porcoes'] ?? '')), 0, 120);
        $ficha['storage'] = mb_substr(trim((string) ($d['conservacao'] ?? '')), 0, 1000);
        $ficha['tableNote'] = mb_substr(trim((string) ($d['nota_tabela'] ?? '')), 0, 1000);
        $ficha['features'] = self::linhas((string) ($d['selos'] ?? ''), 12);
        $tabela = [];
        $nutrientes = (array) ($d['nutriente'] ?? []);
        foreach ($nutrientes as $i => $n) {
            $n = mb_substr(trim((string) $n), 0, 80);
            if ($n !== '') {
                $tabela[] = [$n, mb_substr(trim((string) ($d['quantidade'][$i] ?? '')), 0, 40), mb_substr(trim((string) ($d['vd'][$i] ?? '')), 0, 20)];
            }
        }
        $ficha['nutrition'] = array_slice($tabela, 0, 40);
        self::gravarFicha($produto, $ficha, 'composicao');
        self::gravarTecnico((int) $produto['id'], [
            'alergenicos' => mb_substr(trim((string) ($d['alergenicos'] ?? '')), 0, 500) ?: null,
            'avisos'      => mb_substr(trim((string) ($d['avisos'] ?? '')), 0, 1000) ?: null,
        ], $usuarioId);
    }

    /** Especificações de embalagem e logística (Responsável Técnica) */
    public static function salvarTecnico(int $produtoId, array $d, ?int $usuarioId): void
    {
        $texto = fn(string $k, int $max) => mb_substr(trim((string) ($d[$k] ?? '')), 0, $max) ?: null;
        $inteiro = fn(string $k) => ctype_digit(trim((string) ($d[$k] ?? ''))) ? min(65000, (int) $d[$k]) : null;
        $ean = preg_replace('/\D/', '', (string) ($d['ean'] ?? ''));
        if ($ean !== '' && !in_array(strlen($ean), [8, 12, 13, 14], true)) {
            throw new RuntimeException('O EAN (código de barras) deve ter 8, 12, 13 ou 14 números.');
        }
        $ncm = preg_replace('/\D/', '', (string) ($d['ncm'] ?? ''));
        if ($ncm !== '' && strlen($ncm) !== 8) {
            throw new RuntimeException('O NCM deve ter 8 números (ex.: 2106.90.30).');
        }
        self::gravarTecnico($produtoId, [
            'ean' => $ean ?: null, 'ncm' => $ncm ? substr($ncm, 0, 4) . '.' . substr($ncm, 4, 2) . '.' . substr($ncm, 6, 2) : null,
            'registro_anvisa' => $texto('registro_anvisa', 80), 'validade_meses' => $inteiro('validade_meses'),
            'peso_liquido' => $texto('peso_liquido', 40), 'peso_bruto' => $texto('peso_bruto', 40), 'dimensoes' => $texto('dimensoes', 60),
            'unidades_caixa' => $inteiro('unidades_caixa'), 'armazenamento' => $texto('armazenamento', 500), 'observacoes' => $texto('observacoes', 3000),
        ], $usuarioId);
    }

    private static function gravarTecnico(int $produtoId, array $campos, ?int $usuarioId): void
    {
        $antes = self::tecnico($produtoId);
        if (!$antes) {
            Banco::inserir('produto_tecnico', ['produto_id' => $produtoId, 'atualizado_por' => $usuarioId] + $campos);
        } else {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($campos)));
            Banco::executar("UPDATE produto_tecnico SET $sets, atualizado_por = ? WHERE produto_id = ?", [...array_values($campos), $usuarioId, $produtoId]);
        }
        Auditoria::registrar('produto_tecnico', $produtoId, 'editar', array_intersect_key($antes, $campos) ?: array_fill_keys(array_keys($campos), null), $campos);
    }

    // ------------------------------------------------------------------
    // Documentos
    // ------------------------------------------------------------------

    public static function documentos(int $produtoId): array
    {
        $dias = self::diasAviso();
        return Banco::todos("SELECT d.*, u.nome AS enviado_por_nome,
                CASE WHEN d.validade IS NULL THEN 'ok' WHEN d.validade < CURDATE() THEN 'vencido'
                     WHEN d.validade <= CURDATE() + INTERVAL $dias DAY THEN 'vencendo' ELSE 'ok' END AS situacao
            FROM produto_documentos d LEFT JOIN usuarios u ON u.id = d.enviado_por
            WHERE d.produto_id = ? AND d.arquivado_em IS NULL" . (self::vePrivado() ? '' : ' AND d.interno = 0') . '
            ORDER BY FIELD(d.tipo, "anvisa", "laudo", "fispq", "certificado", "especificacao", "rotulo", "outro"), d.criado_em DESC', [$produtoId]);
    }

    /**
     * Guarda o arquivo enviado. $arquivo = item de $_FILES. Confere o tipo pelo conteúdo e o tamanho.
     * O nome no disco é aleatório (o nome original fica só no banco).
     */
    public static function enviarDocumento(int $produtoId, array $arquivo, array $d, ?int $usuarioId): int
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Escolha o arquivo do documento.');
        }
        if ($arquivo['error'] === UPLOAD_ERR_INI_SIZE || $arquivo['error'] === UPLOAD_ERR_FORM_SIZE || (int) $arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new RuntimeException('O arquivo passa de 15 MB.');
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new RuntimeException('Não foi possível receber o arquivo. Tente de novo.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset(self::MIMES[$mime])) {
            throw new RuntimeException('Envie o documento em PDF, JPG ou PNG.');
        }
        $titulo = mb_substr(trim((string) ($d['titulo'] ?? '')), 0, 160);
        $tipo = isset(self::TIPOS_DOCUMENTO[$d['tipo'] ?? '']) ? $d['tipo'] : 'outro';
        if ($titulo === '') {
            $titulo = self::TIPOS_DOCUMENTO[$tipo];
        }
        $validade = null;
        if (trim((string) ($d['validade'] ?? '')) !== '') {
            $dt = \DateTime::createFromFormat('!Y-m-d', trim((string) $d['validade']));
            if (!$dt || $dt->format('Y-m-d') !== trim((string) $d['validade'])) {
                throw new RuntimeException('Validade inválida.');
            }
            $validade = $dt->format('Y-m-d');
        }
        if (!is_dir(self::pasta()) && !mkdir(self::pasta(), 0750, true)) {
            throw new RuntimeException('A pasta dos documentos não pôde ser criada.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], self::pasta() . '/' . $nome)) {
            throw new RuntimeException('Não foi possível guardar o arquivo.');
        }
        $id = Banco::inserir('produto_documentos', [
            'produto_id' => $produtoId, 'tipo' => $tipo, 'titulo' => $titulo, 'arquivo' => $nome,
            'nome_original' => mb_substr(basename((string) $arquivo['name']), 0, 200), 'mime' => $mime, 'tamanho' => (int) $arquivo['size'],
            'validade' => $validade, 'interno' => !empty($d['interno']) ? 1 : 0, 'enviado_por' => $usuarioId,
        ]);
        Auditoria::registrar('produto_documento', $produtoId, 'enviar', null, ['documento' => $titulo, 'tipo' => $tipo, 'validade' => $validade, 'interno' => !empty($d['interno'])]);
        return $id;
    }

    /** Documento para baixar, se a pessoa pode ver (interno só para técnica, produção e admin) */
    public static function documento(int $id): ?array
    {
        $doc = Banco::um('SELECT d.*, p.slug FROM produto_documentos d JOIN produtos p ON p.id = d.produto_id WHERE d.id = ? AND d.arquivado_em IS NULL', [$id]);
        if (!$doc || ($doc['interno'] && !self::vePrivado())) {
            return null;
        }
        $caminho = self::pasta() . '/' . basename($doc['arquivo']);
        return is_file($caminho) ? $doc + ['caminho' => $caminho] : null;
    }

    /** "Remover" só arquiva: o arquivo continua guardado e o histórico mostra quem tirou */
    public static function arquivarDocumento(int $id): ?array
    {
        $doc = Banco::um('SELECT d.id, d.produto_id, d.titulo, p.slug FROM produto_documentos d JOIN produtos p ON p.id = d.produto_id WHERE d.id = ? AND d.arquivado_em IS NULL', [$id]);
        if ($doc) {
            Banco::executar('UPDATE produto_documentos SET arquivado_em = NOW() WHERE id = ?', [$id]);
            Auditoria::registrar('produto_documento', (int) $doc['produto_id'], 'remover', ['documento' => $doc['titulo']], null);
        }
        return $doc;
    }

    /** Documentos vencidos ou vencendo (para o Início e o sino de quem cuida da parte técnica) */
    public static function documentosVencendo(int $limite = 20): array
    {
        $dias = self::diasAviso();
        return Banco::todos("SELECT d.id, d.titulo, d.validade, p.nome AS produto, p.slug
            FROM produto_documentos d JOIN produtos p ON p.id = d.produto_id
            WHERE d.arquivado_em IS NULL AND d.validade IS NOT NULL AND d.validade <= CURDATE() + INTERVAL $dias DAY
            ORDER BY d.validade LIMIT " . (int) $limite);
    }

    // ------------------------------------------------------------------
    // Histórico
    // ------------------------------------------------------------------

    /** O que mudou no produto (conteúdo, composição, especificações, ficha e documentos) */
    public static function historico(int $produtoId, int $limite = 60): array
    {
        return Banco::todos("SELECT a.entidade, a.acao, a.antes_json, a.depois_json, a.criado_em, u.nome AS usuario
            FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.entidade_id = ? AND a.entidade IN ('produto', 'produto_tecnico', 'ficha_tecnica', 'produto_documento')
            ORDER BY a.id DESC LIMIT " . (int) $limite, [$produtoId]);
    }

    /** Nome amigável do que mudou, para o Histórico */
    public static function descreverMudanca(array $h): string
    {
        return match ($h['entidade'] . ':' . $h['acao']) {
            'produto:conteudo'          => 'Conteúdo (Academia e catálogo)',
            'produto:composicao'        => 'Composição',
            'produto_tecnico:editar'    => 'Especificações técnicas',
            'ficha_tecnica:salvar'      => 'Ficha técnica (matérias-primas e embalagens)',
            'produto_documento:enviar'  => 'Documento enviado',
            'produto_documento:remover' => 'Documento removido',
            default                     => 'Catálogo',
        };
    }
}
