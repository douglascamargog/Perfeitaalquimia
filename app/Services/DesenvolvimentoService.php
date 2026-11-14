<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Desenvolvimento de Produto: do briefing ao lançamento, em etapas feitas por funções diferentes
 * (Técnica, Marketing, Qualidade, Produção). O projeto cria o produto logo no início (oculto: ativo = 0),
 * e cada área preenche nas telas de sempre (Produtos › Composição, Ficha técnica, Embalagens, Documentos).
 * Cada etapa tem uma checagem automática: só conclui quando o necessário está pronto.
 */
class DesenvolvimentoService
{
    public const CHECAGENS = [
        'ficha' => 'Ficha técnica com as matérias-primas', 'composicao' => 'Composição (ingredientes, porção e tabela nutricional)',
        'codigo' => 'EAN e NCM', 'arte' => 'Arte do rótulo enviada', 'compras' => 'Compras marcadas', 'grafica' => 'Pedido à gráfica',
        'aprovacao' => 'Prova aprovada', 'recebimento' => 'Rótulos recebidos', 'piloto' => 'Lote piloto', 'lancamento' => 'Lançamento',
    ];

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    public static function etapas(bool $soAtivas = true): array
    {
        return Banco::todos('SELECT e.*, f.nome AS funcao FROM dp_etapas e LEFT JOIN funcoes f ON f.slug = e.funcao_slug'
            . ($soAtivas ? ' WHERE e.ativa = 1' : '') . ' ORDER BY e.ordem, e.id');
    }

    /** Projetos (com etapa, produto e cliente). $f: tipo (nosso|terceirizacao), situacao, atrasados */
    public static function projetos(array $f = []): array
    {
        $w = ["p.situacao IN ('andamento','pausado')"];
        if (($f['situacao'] ?? '') === 'todos') {
            $w = ['1 = 1'];
        } elseif (($f['situacao'] ?? '') === 'lancado') {
            $w = ["p.situacao = 'lancado'"];
        }
        if (($f['tipo'] ?? '') === 'nosso') {
            $w[] = 'p.cliente_id IS NULL';
        } elseif (($f['tipo'] ?? '') === 'terceirizacao') {
            $w[] = 'p.cliente_id IS NOT NULL';
        }
        if (!empty($f['atrasados'])) {
            $w[] = 'p.prazo < CURDATE()';
        }
        return Banco::todos('SELECT p.*, e.nome AS etapa, e.ordem AS etapa_ordem, e.funcao_slug, f.nome AS funcao, pr.slug AS produto_slug,
                COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente, u.nome AS criado_por_nome
            FROM dp_projetos p LEFT JOIN dp_etapas e ON e.id = p.etapa_id LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN produtos pr ON pr.id = p.produto_id LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios u ON u.id = p.criado_por
            WHERE ' . implode(' AND ', $w) . ' ORDER BY p.prazo IS NULL, p.prazo, p.id');
    }

    public static function buscar(int $id): ?array
    {
        $p = Banco::um('SELECT p.*, e.nome AS etapa, e.ordem AS etapa_ordem, e.funcao_slug, e.checagem, e.aprovadores, f.nome AS funcao,
                pr.slug AS produto_slug, pr.nome AS produto_nome, pr.codigo AS produto_codigo, cat.nome AS categoria,
                COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente
            FROM dp_projetos p LEFT JOIN dp_etapas e ON e.id = p.etapa_id LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN produtos pr ON pr.id = p.produto_id LEFT JOIN categorias cat ON cat.id = p.categoria_id LEFT JOIN clientes c ON c.id = p.cliente_id
            WHERE p.id = ?', [$id]);
        if ($p) {
            $p['compras_ok'] = json_decode((string) $p['compras_ok'], true) ?: [];
        }
        return $p;
    }

    /** Linha das etapas do projeto: cada etapa com situação, quem concluiu e quando */
    public static function linhaDoProjeto(int $id): array
    {
        return Banco::todos('SELECT e.id, e.nome, e.ordem, e.funcao_slug, e.checagem, f.nome AS funcao, pe.situacao, pe.concluida_em, pe.observacao, u.nome AS concluida_por
            FROM dp_etapas e LEFT JOIN dp_projeto_etapas pe ON pe.etapa_id = e.id AND pe.projeto_id = ? LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN usuarios u ON u.id = pe.concluida_por WHERE e.ativa = 1 ORDER BY e.ordem, e.id', [$id]);
    }

    public static function historico(int $id): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario FROM dp_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.projeto_id = ? ORDER BY h.id DESC LIMIT 200', [$id]);
    }

    /** Projetos esperando uma etapa das funções da pessoa (ou a aprovação dela) */
    public static function minhas(?int $usuarioId = null): array
    {
        $funcoes = self::funcoesDe($usuarioId ?? (int) Auth::id());
        if (!$funcoes) {
            return [];
        }
        $lista = "'" . implode("','", array_map(fn($f) => preg_replace('/[^a-z0-9_]/', '', $f), $funcoes)) . "'";
        return array_values(array_filter(self::projetos(), fn($p) => $p['situacao'] === 'andamento'
            && (in_array((string) $p['funcao_slug'], $funcoes, true) || self::aguardaAprovacaoDe($p, $funcoes))));
    }

    private static function funcoesDe(int $usuarioId): array
    {
        return array_column(Banco::todos('SELECT f.slug FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ?', [$usuarioId]), 'slug');
    }

    private static function aguardaAprovacaoDe(array $p, array $funcoes): bool
    {
        $e = Banco::um('SELECT * FROM dp_etapas WHERE id = ?', [(int) $p['etapa_id']]);
        if (!$e || !$e['aprovadores']) {
            return false;
        }
        $faltam = array_diff(explode(',', $e['aprovadores']), array_column(self::aprovacoesDaRodada((int) $p['id'], (int) $e['id']), 'funcao_slug'));
        return (bool) array_intersect($faltam, $funcoes);
    }

    // ------------------------------------------------------------------
    // Criar
    // ------------------------------------------------------------------

    public static function criar(array $d, ?int $usuarioId): int
    {
        $nome = mb_substr(trim((string) ($d['nome'] ?? '')), 0, 160);
        if ($nome === '') {
            throw new RuntimeException('Dê um nome para o produto novo.');
        }
        $clienteId = (int) ($d['cliente_id'] ?? 0) ?: null;
        if ($clienteId && !Banco::valor('SELECT 1 FROM clientes WHERE id = ? AND excluido_em IS NULL', [$clienteId])) {
            throw new RuntimeException('Cliente de terceirização não encontrado.');
        }
        $etapas = self::etapas();
        if (!$etapas) {
            throw new RuntimeException('Cadastre as etapas do desenvolvimento em Configurações.');
        }
        $prazo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['prazo'] ?? '')) ? $d['prazo'] : null;
        $categoria = (int) ($d['categoria_id'] ?? 0) ?: null;
        $embalagem = mb_substr(trim((string) ($d['embalagem'] ?? '')), 0, 120) ?: null;
        return Banco::transacao(function () use ($nome, $d, $clienteId, $etapas, $prazo, $categoria, $embalagem, $usuarioId) {
            // O produto nasce oculto (fora do catálogo e da lista de Produtos) e vai sendo preenchido pelas áreas
            $produto = Banco::inserir('produtos', ['slug' => 'dev-' . bin2hex(random_bytes(5)), 'nome' => $nome, 'embalagem' => $embalagem,
                'categoria_id' => $categoria, 'ativo' => 0, 'visivel_catalogo' => 0, 'ordem' => 9999, 'ficha_json' => '{}']);
            $id = Banco::inserir('dp_projetos', ['nome' => $nome, 'briefing' => mb_substr(trim((string) ($d['briefing'] ?? '')), 0, 5000) ?: null,
                'categoria_id' => $categoria, 'embalagem' => $embalagem, 'cliente_id' => $clienteId, 'produto_id' => $produto,
                'etapa_id' => (int) $etapas[0]['id'], 'prazo' => $prazo, 'criado_por' => $usuarioId]);
            foreach ($etapas as $i => $e) {
                Banco::inserir('dp_projeto_etapas', ['projeto_id' => $id, 'etapa_id' => (int) $e['id'], 'situacao' => $i === 0 ? 'andamento' : 'pendente']);
            }
            self::registrar($id, $usuarioId, 'sistema', 'Projeto criado' . ($clienteId ? ' (terceirização)' : '') . '.');
            Auditoria::registrar('desenvolvimento', $id, 'criar', null, ['nome' => $nome, 'cliente' => $clienteId]);
            return $id;
        });
    }

    // ------------------------------------------------------------------
    // Etapas: o que falta, quem pode, concluir
    // ------------------------------------------------------------------

    /** O que ainda falta na etapa atual (lista de textos; vazia = pode concluir) */
    public static function pendencias(array $p): array
    {
        $prod = (int) $p['produto_id'];
        $f = $prod ? json_decode((string) Banco::valor('SELECT ficha_json FROM produtos WHERE id = ?', [$prod]), true) ?: [] : [];
        $t = $prod ? (Banco::um('SELECT * FROM produto_tecnico WHERE produto_id = ?', [$prod]) ?: []) : [];
        $falta = [];
        switch ((string) ($p['checagem'] ?? '')) {
            case 'ficha':
                if (!Banco::valor('SELECT 1 FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id WHERE ft.produto_id = ? AND m.tipo = \'materia_prima\' LIMIT 1', [$prod])) {
                    $falta[] = 'Cadastrar a ficha técnica (matérias-primas por unidade) na aba Ficha técnica do produto.';
                }
                break;
            case 'composicao':
                if (empty($f['ingredients'])) { $falta[] = 'Ingredientes (aba Composição).'; }
                if (empty($f['serving'])) { $falta[] = 'Porção (aba Composição).'; }
                if (empty($f['nutrition'])) { $falta[] = 'Tabela nutricional (aba Composição).'; }
                break;
            case 'codigo':
                if (empty($t['ean'])) { $falta[] = 'Código de barras (EAN).'; }
                if (empty($t['ncm'])) { $falta[] = 'NCM.'; }
                break;
            case 'arte':
                if (!self::temDocumento($prod, 'rotulo')) { $falta[] = 'Enviar a arte do rótulo.'; }
                break;
            case 'compras':
                $itens = array_column(Banco::todos('SELECT materia_prima_id FROM ficha_tecnica WHERE produto_id = ?', [$prod]), 'materia_prima_id');
                $faltam = array_diff(array_map('intval', $itens), array_map('intval', $p['compras_ok']));
                if (!$itens) { $falta[] = 'A ficha técnica ainda está vazia.'; }
                if ($faltam) { $falta[] = count($faltam) . ' item(ns) da ficha ainda sem compra marcada.'; }
                break;
            case 'grafica':
                if (empty($p['grafica']) || empty($p['grafica_pedido_em'])) { $falta[] = 'Informar a gráfica e a data do pedido.'; }
                break;
            case 'aprovacao':
                if (!self::temDocumento($prod, 'prova')) { $falta[] = 'Anexar a prova que a gráfica mandou.'; }
                $feitas = array_column(self::aprovacoesDaRodada((int) $p['id'], (int) $p['etapa_id']), 'funcao_slug');
                foreach (array_diff(array_filter(explode(',', (string) $p['aprovadores'])), $feitas) as $quem) {
                    $falta[] = 'Aprovação de ' . (Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$quem]) ?: $quem) . '.';
                }
                break;
            case 'recebimento':
                if ((int) $p['rotulos_recebidos'] <= 0) { $falta[] = 'Registrar a quantidade de rótulos recebidos.'; }
                break;
            case 'piloto':
                $ordem = $p['ordem_id'] ? Banco::valor('SELECT situacao FROM ordens_producao WHERE id = ?', [$p['ordem_id']]) : null;
                if ($ordem !== 'concluida') { $falta[] = $ordem ? 'Concluir a ordem do lote piloto em Produção › Ordens.' : 'Criar a ordem do lote piloto.'; }
                break;
        }
        return $falta;
    }

    private static function temDocumento(int $produtoId, string $tipo): bool
    {
        return (bool) Banco::valor('SELECT 1 FROM produto_documentos WHERE produto_id = ? AND tipo = ? AND arquivado_em IS NULL LIMIT 1', [$produtoId, $tipo]);
    }

    /** A pessoa pode concluir a etapa atual? (função da etapa; etapa sem função: quem gerencia; Admin sempre) */
    public static function podeConcluir(array $p): bool
    {
        if (Auth::pode('*')) { // Admin
            return true;
        }
        $funcao = (string) $p['funcao_slug'];
        return $funcao === '' ? Auth::pode('desenvolvimento.gerenciar') : in_array($funcao, self::funcoesDe((int) Auth::id()), true);
    }

    /** Conclui a etapa atual e passa para a próxima. Observação vai para o histórico. */
    public static function concluir(int $id, ?int $usuarioId, string $observacao = '', bool $forcarPiloto = false): void
    {
        $p = self::buscar($id);
        if (!$p || $p['situacao'] !== 'andamento') {
            throw new RuntimeException('Projeto não está em andamento.');
        }
        if (!self::podeConcluir($p)) {
            throw new RuntimeException('Esta etapa é feita por ' . ($p['funcao'] ?: 'quem gerencia o desenvolvimento') . '.');
        }
        $falta = self::pendencias($p);
        // Lote piloto: pode concluir sem ordem, explicando (ex.: teste feito fora do sistema)
        if ($p['checagem'] === 'piloto' && $forcarPiloto && trim($observacao) !== '') {
            $falta = [];
        }
        if ($falta) {
            throw new RuntimeException('Ainda falta: ' . implode(' ', $falta));
        }
        Banco::transacao(function () use ($p, $usuarioId, $observacao) {
            if ($p['checagem'] === 'lancamento') {
                self::lancar($p, $usuarioId);
            }
            self::avancar($p, $usuarioId, $observacao);
        });
    }

    private static function avancar(array $p, ?int $usuarioId, string $observacao): void
    {
        $id = (int) $p['id'];
        Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'concluida', concluida_por = ?, concluida_em = NOW(), observacao = ? WHERE projeto_id = ? AND etapa_id = ?",
            [$usuarioId, mb_substr($observacao, 0, 500) ?: null, $id, $p['etapa_id']]);
        self::registrar($id, $usuarioId, 'etapa', 'Etapa concluída: ' . $p['etapa'] . ($observacao !== '' ? ' — ' . $observacao : ''));
        $proxima = Banco::um('SELECT * FROM dp_etapas WHERE ativa = 1 AND (ordem > ? OR (ordem = ? AND id > ?)) ORDER BY ordem, id LIMIT 1',
            [$p['etapa_ordem'], $p['etapa_ordem'], $p['etapa_id']]);
        if (!$proxima) {
            Banco::executar("UPDATE dp_projetos SET situacao = 'lancado', lancado_em = NOW() WHERE id = ?", [$id]);
            self::registrar($id, $usuarioId, 'sistema', 'Produto lançado. 🎉');
            Auditoria::registrar('desenvolvimento', $id, 'lancar');
            return;
        }
        Banco::executar('UPDATE dp_projetos SET etapa_id = ? WHERE id = ?', [$proxima['id'], $id]);
        Banco::executar("INSERT INTO dp_projeto_etapas (projeto_id, etapa_id, situacao) VALUES (?, ?, 'andamento') ON DUPLICATE KEY UPDATE situacao = 'andamento'",
            [$id, $proxima['id']]);
        Auditoria::registrar('desenvolvimento', $id, 'etapa', ['etapa' => $p['etapa']], ['etapa' => $proxima['nome']]);
        self::avisarFuncao($proxima['funcao_slug'] ?: null, $p, 'Desenvolvimento: "' . $p['nome'] . '" chegou na etapa ' . $proxima['nome'], $usuarioId);
        if ($proxima['aprovadores']) {
            foreach (explode(',', $proxima['aprovadores']) as $f) {
                self::avisarFuncao($f, $p, 'Desenvolvimento: aprovar a prova de "' . $p['nome'] . '"', $usuarioId);
            }
        }
    }

    /** Volta uma etapa (quem gerencia) */
    public static function voltar(int $id, ?int $usuarioId, string $motivo): void
    {
        $p = self::buscar($id);
        $anterior = $p ? Banco::um('SELECT * FROM dp_etapas WHERE ativa = 1 AND (ordem < ? OR (ordem = ? AND id < ?)) ORDER BY ordem DESC, id DESC LIMIT 1',
            [$p['etapa_ordem'], $p['etapa_ordem'], $p['etapa_id']]) : null;
        if (!$anterior) {
            throw new RuntimeException('Já está na primeira etapa.');
        }
        self::irPara($p, $anterior, $usuarioId, 'Voltou para ' . $anterior['nome'] . ($motivo !== '' ? ' — ' . $motivo : ''));
    }

    private static function irPara(array $p, array $etapa, ?int $usuarioId, string $texto): void
    {
        Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'pendente' WHERE projeto_id = ? AND etapa_id = ?", [$p['id'], $p['etapa_id']]);
        Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'andamento', concluida_em = NULL, concluida_por = NULL WHERE projeto_id = ? AND etapa_id = ?", [$p['id'], $etapa['id']]);
        Banco::executar('UPDATE dp_projetos SET etapa_id = ? WHERE id = ?', [$etapa['id'], $p['id']]);
        self::registrar((int) $p['id'], $usuarioId, 'etapa', $texto);
        Auditoria::registrar('desenvolvimento', (int) $p['id'], 'voltar', ['etapa' => $p['etapa']], ['etapa' => $etapa['nome']]);
        self::avisarFuncao($etapa['funcao_slug'] ?: null, $p, 'Desenvolvimento: "' . $p['nome'] . '" voltou para ' . $etapa['nome'], $usuarioId);
    }

    public static function situacao(int $id, string $situacao, ?int $usuarioId): void
    {
        if (!in_array($situacao, ['andamento', 'pausado', 'cancelado'], true)) {
            return;
        }
        Banco::executar("UPDATE dp_projetos SET situacao = ? WHERE id = ? AND situacao <> 'lancado'", [$situacao, $id]);
        self::registrar($id, $usuarioId, 'sistema', ['andamento' => 'Projeto retomado.', 'pausado' => 'Projeto pausado.', 'cancelado' => 'Projeto cancelado.'][$situacao]);
        Auditoria::registrar('desenvolvimento', $id, $situacao);
    }

    // ------------------------------------------------------------------
    // Ações das etapas
    // ------------------------------------------------------------------

    /** Código de barras e NCM (Qualidade), sem mexer nas outras especificações do produto */
    public static function salvarCodigo(int $id, string $ean, string $ncm, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $ean = preg_replace('/\D/', '', $ean);
        $ncm = preg_replace('/\D/', '', $ncm);
        if (!in_array(strlen($ean), [8, 12, 13, 14], true)) {
            throw new RuntimeException('O EAN (código de barras) deve ter 8, 12, 13 ou 14 números.');
        }
        if (strlen($ncm) !== 8) {
            throw new RuntimeException('O NCM deve ter 8 números (ex.: 2106.90.30).');
        }
        $ncmF = substr($ncm, 0, 4) . '.' . substr($ncm, 4, 2) . '.' . substr($ncm, 6, 2);
        Banco::executar('INSERT INTO produto_tecnico (produto_id, ean, ncm, atualizado_por) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ean = VALUES(ean), ncm = VALUES(ncm), atualizado_por = VALUES(atualizado_por)', [$p['produto_id'], $ean, $ncmF, $usuarioId]);
        Auditoria::registrar('produto_tecnico', (int) $p['produto_id'], 'editar', null, ['ean' => $ean, 'ncm' => $ncmF]);
        self::registrar($id, $usuarioId, 'sistema', "Código de barras $ean e NCM $ncmF cadastrados.");
    }

    /** Arte do rótulo (Marketing) ou prova da gráfica (Qualidade): vai para os Documentos do produto */
    public static function enviarArquivo(int $id, string $tipo, array $arquivo, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $titulo = $tipo === 'prova' ? 'Prova da gráfica' : 'Arte do rótulo';
        ProdutoService::enviarDocumento((int) $p['produto_id'], $arquivo, ['tipo' => $tipo, 'titulo' => $titulo . ' · ' . date('d/m/Y')], $usuarioId);
        self::registrar($id, $usuarioId, 'sistema', "$titulo enviada.");
    }

    /** Compras: marca/desmarca um item da ficha como comprado */
    public static function marcarCompra(int $id, int $mpId, bool $comprado, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $lista = array_values(array_unique(array_map('intval', $p['compras_ok'])));
        $lista = $comprado ? array_values(array_unique([...$lista, $mpId])) : array_values(array_diff($lista, [$mpId]));
        Banco::executar('UPDATE dp_projetos SET compras_ok = ? WHERE id = ?', [json_encode($lista), $id]);
        $nome = Banco::valor('SELECT nome FROM materias_primas WHERE id = ?', [$mpId]);
        self::registrar($id, $usuarioId, 'sistema', ($comprado ? 'Compra feita: ' : 'Compra desmarcada: ') . $nome);
    }

    public static function grafica(int $id, string $grafica, string $data, ?int $usuarioId): void
    {
        $grafica = mb_substr(trim($grafica), 0, 160);
        if ($grafica === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new RuntimeException('Informe a gráfica e a data do pedido.');
        }
        Banco::executar('UPDATE dp_projetos SET grafica = ?, grafica_pedido_em = ? WHERE id = ?', [$grafica, $data, $id]);
        self::registrar($id, $usuarioId, 'sistema', "Pedido do rótulo enviado à gráfica $grafica em " . date('d/m/Y', strtotime($data)) . '.');
    }

    /** Aprovação da prova da gráfica: cada função aprovadora decide uma vez por rodada; "Ajustar" volta para a criação do rótulo */
    public static function aprovar(int $id, string $decisao, string $comentario, ?int $usuarioId): string
    {
        $p = self::buscar($id);
        if (!$p || $p['checagem'] !== 'aprovacao') {
            throw new RuntimeException('O projeto não está na aprovação da prova.');
        }
        $rodada = self::rodada($id, (int) $p['etapa_id']);
        $feitas = self::aprovacoesDaRodada($id, (int) $p['etapa_id']);
        if (in_array((int) $usuarioId, array_map('intval', array_column($feitas, 'usuario_id')), true)) {
            throw new RuntimeException('Você já respondeu esta prova. Falta a outra área.');
        }
        $minhas = self::funcoesDe((int) $usuarioId);
        $pendentes = array_diff(array_filter(explode(',', (string) $p['aprovadores'])), array_column($feitas, 'funcao_slug'));
        $funcao = current(array_intersect($pendentes, $minhas)) ?: (Auth::pode('*') ? current($pendentes) : false);
        if (!$funcao) {
            throw new RuntimeException('Esta prova é aprovada por ' . implode(' e ', array_map(fn($s) => Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$s]) ?: $s, $pendentes)) . '.');
        }
        if ($decisao === 'ajustar' && trim($comentario) === '') {
            throw new RuntimeException('Escreva o que precisa ajustar.');
        }
        Banco::inserir('dp_aprovacoes', ['projeto_id' => $id, 'etapa_id' => $p['etapa_id'], 'funcao_slug' => $funcao, 'usuario_id' => $usuarioId,
            'decisao' => $decisao === 'ajustar' ? 'ajustar' : 'aprovado', 'comentario' => mb_substr(trim($comentario), 0, 500) ?: null, 'rodada' => $rodada]);
        $nomeFuncao = Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$funcao]) ?: $funcao;
        if ($decisao === 'ajustar') {
            self::registrar($id, $usuarioId, 'aprovacao', "$nomeFuncao pediu ajuste na prova: " . trim($comentario));
            $arte = Banco::um("SELECT * FROM dp_etapas WHERE ativa = 1 AND checagem = 'arte' ORDER BY ordem LIMIT 1");
            if ($arte) {
                self::irPara($p, $arte, $usuarioId, 'Voltou para ' . $arte['nome'] . ' para ajustar o rótulo.');
            }
            return 'Ajuste pedido: o projeto voltou para a criação do rótulo.';
        }
        self::registrar($id, $usuarioId, 'aprovacao', "$nomeFuncao aprovou a prova da gráfica.");
        if (!self::pendencias(self::buscar($id))) {
            self::avancar(self::buscar($id), $usuarioId, 'Prova aprovada por todos');
            return 'Prova aprovada por todas as áreas: o projeto seguiu para a próxima etapa.';
        }
        return 'Aprovação registrada. Falta a outra área aprovar.';
    }

    private static function rodada(int $id, int $etapaId): int
    {
        return 1 + (int) Banco::valor("SELECT COUNT(*) FROM dp_aprovacoes WHERE projeto_id = ? AND etapa_id = ? AND decisao = 'ajustar'", [$id, $etapaId]);
    }

    public static function aprovacoesDaRodada(int $id, int $etapaId): array
    {
        return Banco::todos("SELECT a.*, u.nome AS usuario FROM dp_aprovacoes a LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.projeto_id = ? AND a.etapa_id = ? AND a.rodada = ? AND a.decisao = 'aprovado'", [$id, $etapaId, self::rodada($id, $etapaId)]);
    }

    /**
     * Rótulos recebidos da gráfica. Produto nosso: entrada no estoque do item "Rótulo {produto}" (embalagem, criado
     * sozinho e colocado na ficha técnica). Terceirização: fica no projeto e vai para os rótulos do cliente no lançamento.
     */
    public static function receberRotulos(int $id, int $quantidade, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        if ($quantidade <= 0) {
            throw new RuntimeException('Informe quantos rótulos chegaram.');
        }
        Banco::transacao(function () use ($p, $quantidade, $usuarioId) {
            if (!$p['cliente_id']) {
                $nome = mb_substr('Rótulo ' . $p['nome'], 0, 160);
                $mp = Banco::valor('SELECT id FROM materias_primas WHERE nome = ?', [$nome]);
                if (!$mp) {
                    $mp = ProducaoService::salvarMateria(null, ['nome' => $nome, 'unidade' => 'un', 'tipo' => 'embalagem', 'estoque_minimo' => '0', 'custo_unitario' => '0', 'ativo' => 1], $usuarioId);
                    Banco::executar('INSERT IGNORE INTO ficha_tecnica (produto_id, materia_prima_id, quantidade) VALUES (?, ?, 1)', [$p['produto_id'], $mp]);
                }
                ProducaoService::entrada((int) $mp, $quantidade, 0, $p['grafica'] ?: 'Gráfica', 'Rótulos do produto novo (Desenvolvimento)', $usuarioId);
            }
            Banco::executar('UPDATE dp_projetos SET rotulos_recebidos = rotulos_recebidos + ? WHERE id = ?', [$quantidade, $p['id']]);
            self::registrar((int) $p['id'], $usuarioId, 'sistema', number_format($quantidade, 0, ',', '.') . ' rótulo(s) recebido(s)' . ($p['cliente_id'] ? '.' : ' e lançados no estoque.'));
        });
    }

    /** Lote piloto: cria a ordem de produção (Produção conclui em Produção › Ordens) */
    public static function criarPiloto(int $id, int $quantidade, ?int $usuarioId): int
    {
        $p = self::buscar($id);
        $ordem = ProducaoService::criarOrdem(['produto_id' => $p['produto_id'], 'quantidade' => $quantidade, 'lote' => 'PILOTO-' . $p['id'],
            'observacoes' => 'Lote piloto do produto novo (Desenvolvimento).'] + ($p['cliente_id'] ? [] : []), $usuarioId);
        Banco::executar('UPDATE dp_projetos SET ordem_id = ? WHERE id = ?', [$ordem, $id]);
        self::registrar($id, $usuarioId, 'sistema', "Ordem do lote piloto criada (OP $ordem, $quantidade un).");
        return $ordem;
    }

    /** Lançamento: produto nosso entra no catálogo; de terceirização vira produto daquele cliente */
    private static function lancar(array $p, ?int $usuarioId): void
    {
        if ($p['cliente_id']) {
            Banco::executar('UPDATE produtos SET ativo = 1, visivel_catalogo = 0, cliente_exclusivo_id = ?, codigo = COALESCE(codigo, ?) WHERE id = ?',
                [$p['cliente_id'], 'EX-' . $p['produto_id'], $p['produto_id']]);
            if (!Banco::valor('SELECT 1 FROM terceirizacao_produtos WHERE cliente_id = ? AND produto_id = ?', [$p['cliente_id'], $p['produto_id']])) {
                Banco::inserir('terceirizacao_produtos', ['cliente_id' => $p['cliente_id'], 'produto_id' => $p['produto_id'], 'nome_rotulo' => $p['nome'],
                    'rotulos_estoque' => (int) $p['rotulos_recebidos']]);
            }
        } else {
            Banco::executar('UPDATE produtos SET ativo = 1, visivel_catalogo = 1, ordem = 0 WHERE id = ?', [$p['produto_id']]);
        }
        Auditoria::registrar('produto', (int) $p['produto_id'], 'lancado', null, ['projeto' => $p['id']]);
    }

    public static function comentar(int $id, string $texto, ?int $usuarioId): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new RuntimeException('Escreva o comentário.');
        }
        self::registrar($id, $usuarioId, 'comentario', mb_substr($texto, 0, 1000));
    }

    // ------------------------------------------------------------------

    private static function registrar(int $id, ?int $usuarioId, string $tipo, string $texto): void
    {
        Banco::inserir('dp_historico', ['projeto_id' => $id, 'usuario_id' => $usuarioId, 'tipo' => $tipo, 'texto' => mb_substr($texto, 0, 1000)]);
    }

    /** Aviso no sino para quem é da função (sem função: quem gerencia o desenvolvimento) */
    private static function avisarFuncao(?string $funcao, array $p, string $titulo, ?int $usuarioId): void
    {
        $pessoas = $funcao
            ? array_column(Banco::todos('SELECT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id
                WHERE u.ativo = 1 AND f.slug = ?', [$funcao]), 'id')
            : array_column(Banco::todos("SELECT DISTINCT u.id FROM usuarios u JOIN perfil_permissoes pp ON pp.perfil_id = u.perfil_id
                WHERE u.ativo = 1 AND pp.permissao IN ('desenvolvimento.gerenciar', '*')"), 'id');
        if ($pessoas) {
            NotificacaoService::avisar($pessoas, 'desenvolvimento', $titulo, '/desenvolvimento/' . (int) $p['id'], [], $usuarioId);
        }
    }

    /** Quantos projetos esperam a pessoa (número do menu e One) */
    public static function qtdMinhas(int $usuarioId): int
    {
        return count(self::minhas($usuarioId));
    }
}
