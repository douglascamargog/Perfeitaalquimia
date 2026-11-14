<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Gestão › Carteiras: grupos de clientes de um setor, cada um com um(a) vendedor(a) — ou SEM vendedor(a)
 * (a pessoa saiu: os clientes ficam parados na carteira até alguém assumir).
 *
 * Regra: a CARTEIRA manda no responsável. Passar a carteira para outra pessoa muda o responsável de todos
 * os clientes dela (com evento na linha do tempo de cada um e registro na auditoria).
 * Quem usa: permissão "carteiras.gerenciar" (Admin e Gestor) ou a gestora do setor (só os setores dela).
 */
class CarteirasService
{
    // ------------------------------------------------------------------
    // Quem pode
    // ------------------------------------------------------------------

    /** Ids dos setores (funções) cujas carteiras a pessoa pode mexer; null = todos */
    public static function setoresPermitidos(): ?array
    {
        return Auth::pode('carteiras.gerenciar') ? null : Setores::gerenciados();
    }

    public static function podeUsar(): bool
    {
        return Auth::pode('carteiras.gerenciar') || Setores::gerenciados();
    }

    public static function podeMexer(array $carteira): bool
    {
        $setores = self::setoresPermitidos();
        return $setores === null || in_array((int) $carteira['funcao_id'], $setores, true);
    }

    /** Carteira que a pessoa pode mexer, ou erro */
    public static function daPessoa(int $id): array
    {
        $c = self::buscar($id);
        if (!$c || !self::podeMexer($c)) {
            throw new RuntimeException('Carteira não encontrada.');
        }
        return $c;
    }

    /** Vendedores(as) que podem receber carteiras: todos ativos que vendem; a gestora, só a equipe dela */
    public static function vendedores(): array
    {
        return self::setoresPermitidos() === null ? \App\Models\Listas::vendedores() : Setores::equipeGerenciada();
    }

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT k.*, u.nome AS vendedor, u.ativo AS vendedor_ativo, f.nome AS setor
            FROM carteiras k LEFT JOIN usuarios u ON u.id = k.responsavel_id LEFT JOIN funcoes f ON f.id = k.funcao_id WHERE k.id = ?', [$id]);
    }

    /** Carteiras ativas (que a pessoa pode ver), com os números de cada uma */
    public static function lista(?int $setor = null): array
    {
        $w = ['k.ativa = 1'];
        $permitidos = self::setoresPermitidos();
        if ($permitidos !== null) {
            $w[] = $permitidos ? 'k.funcao_id IN (' . implode(',', $permitidos) . ')' : '1 = 0';
        }
        if ($setor) {
            $w[] = 'k.funcao_id = ' . (int) $setor;
        }
        return Banco::todos("SELECT k.*, u.nome AS vendedor, f.nome AS setor, f.ordem AS setor_ordem,
                COUNT(c.id) AS clientes, SUM(c.tipo = 'cliente') AS ativos, MAX(c.ultima_compra_em) AS ultima_compra,
                (SELECT COALESCE(SUM(p.total), 0) FROM pedidos p JOIN clientes c2 ON c2.id = p.cliente_id
                  WHERE c2.carteira_id = k.id AND p.situacao = 'faturado' AND p.faturado_em >= CURDATE() - INTERVAL 12 MONTH) AS faturado
            FROM carteiras k LEFT JOIN usuarios u ON u.id = k.responsavel_id LEFT JOIN funcoes f ON f.id = k.funcao_id
            LEFT JOIN clientes c ON c.carteira_id = k.id AND c.excluido_em IS NULL
            WHERE " . implode(' AND ', $w) . '
            GROUP BY k.id ORDER BY f.ordem, k.responsavel_id IS NOT NULL, k.nome');
    }

    /** Clientes de uma carteira (busca + paginação no servidor) */
    public static function clientes(int $carteiraId, string $busca = '', int $pagina = 1, int $porPagina = 50): array
    {
        $w = 'c.carteira_id = ? AND c.excluido_em IS NULL';
        $par = [$carteiraId];
        if ($busca !== '') {
            $digitos = preg_replace('/\D/', '', $busca);
            $w .= ' AND (c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR c.cidade LIKE ?' . ($digitos !== '' ? ' OR c.cnpj LIKE ?' : '') . ')';
            array_push($par, "%$busca%", "%$busca%", "%$busca%");
            if ($digitos !== '') {
                $par[] = "%$digitos%";
            }
        }
        $total = (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE $w", $par);
        $itens = Banco::todos("SELECT c.id, c.razao_social, c.nome_fantasia, c.cnpj, c.cidade, c.uf, c.tipo, c.ultima_compra_em, u.nome AS responsavel
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE $w ORDER BY c.razao_social
            LIMIT " . (int) $porPagina . ' OFFSET ' . (max(1, $pagina) - 1) * (int) $porPagina, $par);
        return ['itens' => $itens, 'total' => $total, 'paginas' => max(1, (int) ceil($total / $porPagina))];
    }

    // ------------------------------------------------------------------
    // Ações
    // ------------------------------------------------------------------

    /** Cria uma carteira (vendedor(a) opcional) */
    public static function criar(string $nome, int $funcaoId, ?int $vendedorId, ?int $usuarioId): int
    {
        $nome = mb_substr(trim($nome), 0, 120);
        if ($nome === '') {
            throw new RuntimeException('Dê um nome para a carteira.');
        }
        self::conferirSetor($funcaoId);
        $vendedorId = self::conferirVendedor($vendedorId);
        $id = Banco::inserir('carteiras', ['nome' => $nome, 'funcao_id' => $funcaoId, 'responsavel_id' => $vendedorId, 'criado_por' => $usuarioId]);
        Auditoria::registrar('carteira', $id, 'criar', null, ['nome' => $nome, 'setor' => $funcaoId, 'vendedor' => $vendedorId]);
        return $id;
    }

    /** Renomear e/ou mudar o setor (os clientes vão junto para o setor novo) */
    public static function editar(int $id, string $nome, int $funcaoId, ?int $usuarioId): void
    {
        $antes = self::daPessoa($id);
        $nome = mb_substr(trim($nome), 0, 120) ?: $antes['nome'];
        self::conferirSetor($funcaoId);
        Banco::transacao(function () use ($id, $nome, $funcaoId, $antes, $usuarioId) {
            Banco::atualizar('carteiras', $id, ['nome' => $nome, 'funcao_id' => $funcaoId]);
            if ((int) $antes['funcao_id'] !== $funcaoId) {
                Banco::executar('UPDATE clientes SET funcao_id = ? WHERE carteira_id = ?', [$funcaoId, $id]);
            }
            Auditoria::registrar('carteira', $id, 'editar', ['nome' => $antes['nome'], 'setor' => $antes['funcao_id']], ['nome' => $nome, 'setor' => $funcaoId]);
        });
    }

    /**
     * Passa a carteira inteira para outra pessoa (ou deixa SEM vendedor(a) com $vendedorId = null).
     * Muda o responsável de todos os clientes; cada um ganha um evento na linha do tempo. Devolve quantos clientes.
     */
    public static function passar(int $id, ?int $vendedorId, ?int $usuarioId, string $motivo = 'Carteira passada'): int
    {
        $k = self::daPessoa($id);
        $vendedorId = self::conferirVendedor($vendedorId);
        if ((int) $k['responsavel_id'] === (int) $vendedorId) {
            return 0;
        }
        return Banco::transacao(function () use ($k, $vendedorId, $usuarioId, $motivo) {
            Banco::atualizar('carteiras', (int) $k['id'], ['responsavel_id' => $vendedorId]);
            $n = self::aplicarNosClientes('c.carteira_id = ?', [(int) $k['id']], $vendedorId, (int) $k['funcao_id'], (int) $k['id'], $usuarioId,
                $motivo . ' (' . $k['nome'] . ')');
            Auditoria::registrar('carteira', (int) $k['id'], 'passar', ['vendedor' => $k['responsavel_id']], ['vendedor' => $vendedorId, 'clientes' => $n]);
            return $n;
        });
    }

    /** Move clientes escolhidos para outra carteira (recebem o(a) vendedor(a) e o setor dela). Devolve quantos. */
    public static function mover(array $clienteIds, int $destinoId, ?int $usuarioId): int
    {
        $destino = self::daPessoa($destinoId);
        $ids = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));
        if (!$ids) {
            throw new RuntimeException('Escolha os clientes que vão mudar de carteira.');
        }
        // Só clientes de carteiras que a pessoa pode mexer
        $permitidos = self::setoresPermitidos();
        $filtro = 'c.id IN (' . implode(',', $ids) . ') AND (c.carteira_id IS NULL OR c.carteira_id <> ' . (int) $destinoId . ')'
            . ($permitidos === null ? '' : ($permitidos ? ' AND c.funcao_id IN (' . implode(',', $permitidos) . ')' : ' AND 1 = 0'));
        return Banco::transacao(function () use ($filtro, $destino, $usuarioId) {
            $n = self::aplicarNosClientes($filtro, [], $destino['responsavel_id'] ? (int) $destino['responsavel_id'] : null, (int) $destino['funcao_id'],
                (int) $destino['id'], $usuarioId, 'Mudou para a carteira ' . $destino['nome']);
            Auditoria::registrar('carteira', (int) $destino['id'], 'receber_clientes', null, ['clientes' => $n]);
            return $n;
        });
    }

    /** Junta duas carteiras: todos os clientes da origem vão para o destino; a origem fica inativa (nunca é apagada) */
    public static function juntar(int $origemId, int $destinoId, ?int $usuarioId): int
    {
        if ($origemId === $destinoId) {
            throw new RuntimeException('Escolha outra carteira para juntar.');
        }
        $origem = self::daPessoa($origemId);
        $destino = self::daPessoa($destinoId);
        return Banco::transacao(function () use ($origem, $destino, $usuarioId) {
            $n = self::aplicarNosClientes('c.carteira_id = ?', [(int) $origem['id']], $destino['responsavel_id'] ? (int) $destino['responsavel_id'] : null,
                (int) $destino['funcao_id'], (int) $destino['id'], $usuarioId, 'Carteira ' . $origem['nome'] . ' juntada com ' . $destino['nome']);
            Banco::atualizar('carteiras', (int) $origem['id'], ['ativa' => 0]);
            Auditoria::registrar('carteira', (int) $origem['id'], 'juntar', null, ['destino' => $destino['id'], 'clientes' => $n]);
            return $n;
        });
    }

    /** Coloca clientes numa carteira sem conferir quem está fazendo (uso interno: importação já autorizada) */
    public static function colocarNaCarteira(array $clienteIds, int $carteiraId, ?int $usuarioId, string $texto): int
    {
        $k = self::buscar($carteiraId);
        $ids = implode(',', array_map('intval', $clienteIds)) ?: '0';
        return $k ? self::aplicarNosClientes("c.id IN ($ids)", [], $k['responsavel_id'] ? (int) $k['responsavel_id'] : null, (int) $k['funcao_id'], $carteiraId, $usuarioId, $texto) : 0;
    }

    /** Usuário desativado: as carteiras dele ficam SEM vendedor(a) (clientes parados até alguém assumir) */
    public static function liberarDoUsuario(int $usuarioId, ?int $quem): int
    {
        $total = 0;
        foreach (Banco::todos('SELECT id, nome, funcao_id FROM carteiras WHERE responsavel_id = ? AND ativa = 1', [$usuarioId]) as $k) {
            Banco::atualizar('carteiras', (int) $k['id'], ['responsavel_id' => null]);
            $total += self::aplicarNosClientes('c.carteira_id = ?', [(int) $k['id']], null, (int) $k['funcao_id'], (int) $k['id'], $quem,
                'Vendedor(a) saiu: carteira ' . $k['nome'] . ' ficou sem vendedor(a)');
        }
        if ($total) {
            Auditoria::registrar('carteira', null, 'liberar_usuario', ['vendedor' => $usuarioId], ['clientes' => $total]);
        }
        return $total;
    }

    /**
     * Grava o novo responsável/setor/carteira nos clientes do filtro, com um evento por cliente (em lote)
     * e UM aviso para quem recebeu. Devolve quantos clientes mudaram.
     */
    private static function aplicarNosClientes(string $filtro, array $par, ?int $vendedorId, int $funcaoId, int $carteiraId, ?int $usuarioId, string $texto): int
    {
        $ids = array_map('intval', array_column(Banco::todos("SELECT c.id FROM clientes c WHERE $filtro AND c.excluido_em IS NULL", $par), 'id'));
        if (!$ids) {
            return 0;
        }
        $lista = implode(',', $ids);
        $nome = $vendedorId ? (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$vendedorId]) : 'sem vendedor(a)';
        // Evento na linha do tempo de cada cliente (de quem → para quem)
        Banco::executar("INSERT INTO cliente_eventos (cliente_id, usuario_id, tipo, descricao, dados_json)
            SELECT c.id, ?, 'responsavel', CONCAT(?, ': ', COALESCE(u.nome, 'sem vendedor(a)'), ' → ', ?), JSON_OBJECT('de', c.responsavel_id, 'para', ?, 'carteira', ?)
              FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE c.id IN ($lista)",
            [$usuarioId, mb_substr($texto, 0, 180), $nome, $vendedorId, $carteiraId]);
        Banco::executar("UPDATE clientes SET responsavel_id = ?, funcao_id = ?, carteira_id = ? WHERE id IN ($lista)", [$vendedorId, $funcaoId, $carteiraId]);
        // Retornos em aberto desses clientes passam para a nova pessoa (sem vendedor(a): ficam como estão)
        if ($vendedorId) {
            Banco::executar("UPDATE tarefas SET usuario_id = ? WHERE concluida_em IS NULL AND cliente_id IN ($lista)", [$vendedorId]);
            if ($vendedorId !== $usuarioId) {
                NotificacaoService::avisar([$vendedorId], 'cliente_recebido', count($ids) . ' cliente(s) entraram na sua carteira', '/carteira', ['texto' => $texto], $usuarioId);
            }
        }
        PendenciasService::limpar();
        return count($ids);
    }

    private static function conferirSetor(int $funcaoId): void
    {
        $permitidos = self::setoresPermitidos();
        if (!Banco::valor('SELECT 1 FROM funcoes WHERE id = ?', [$funcaoId]) || ($permitidos !== null && !in_array($funcaoId, $permitidos, true))) {
            throw new RuntimeException('Escolha um setor que você gerencia.');
        }
    }

    private static function conferirVendedor(?int $vendedorId): ?int
    {
        if (!$vendedorId) {
            return null;
        }
        if (!in_array($vendedorId, array_map('intval', array_column(self::vendedores(), 'id')), true)) {
            throw new RuntimeException('Escolha uma pessoa da lista.');
        }
        return $vendedorId;
    }

    // ------------------------------------------------------------------
    // Encaixe automático (cada cliente na carteira do seu responsável e setor)
    // ------------------------------------------------------------------

    /** Carteira ativa daquele(a) vendedor(a) naquele setor; cria se ainda não existir */
    public static function carteiraDe(?int $responsavelId, ?int $funcaoId): int
    {
        $id = Banco::valor('SELECT id FROM carteiras WHERE ativa = 1 AND responsavel_id <=> ? AND funcao_id <=> ? ORDER BY id LIMIT 1', [$responsavelId, $funcaoId]);
        if ($id) {
            return (int) $id;
        }
        $setor = $funcaoId ? (string) Banco::valor('SELECT nome FROM funcoes WHERE id = ?', [$funcaoId]) : 'Sem setor';
        $pessoa = $responsavelId ? (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$responsavelId]) : 'Sem vendedor(a)';
        return Banco::inserir('carteiras', ['nome' => mb_substr("$setor · $pessoa", 0, 120), 'funcao_id' => $funcaoId, 'responsavel_id' => $responsavelId]);
    }

    /**
     * Coloca na carteira certa os clientes sem carteira ou cuja carteira não bate com o responsável/setor
     * (depois de transferência na ficha, fila de reativação, cadastro...). Devolve quantos mudaram.
     */
    public static function encaixar(?array $clienteIds = null): int
    {
        $filtro = $clienteIds !== null ? ' AND c.id IN (' . (implode(',', array_map('intval', $clienteIds)) ?: '0') . ')' : '';
        $errado = '(k.id IS NULL OR k.ativa = 0 OR NOT (k.responsavel_id <=> c.responsavel_id) OR NOT (k.funcao_id <=> c.funcao_id))';
        $pares = Banco::todos("SELECT DISTINCT c.responsavel_id, c.funcao_id FROM clientes c LEFT JOIN carteiras k ON k.id = c.carteira_id WHERE $errado$filtro");
        $n = 0;
        foreach ($pares as $g) {
            $resp = $g['responsavel_id'] !== null ? (int) $g['responsavel_id'] : null;
            $funcao = $g['funcao_id'] !== null ? (int) $g['funcao_id'] : null;
            $carteira = self::carteiraDe($resp, $funcao);
            $n += Banco::executar("UPDATE clientes c LEFT JOIN carteiras k ON k.id = c.carteira_id SET c.carteira_id = ?
                WHERE $errado AND c.responsavel_id <=> ? AND c.funcao_id <=> ?$filtro", [$carteira, $resp, $funcao]);
        }
        return $n;
    }
}
