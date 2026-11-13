<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Notificações (sino do topo): avisos para UMA pessoa sobre algo que aconteceu.
 *
 *  - Pedido novo para aprovar → quem aprova pedidos.
 *  - Pedido aprovado, recusado ou faturado → o vendedor. Pedido feito pelo cliente no catálogo → o vendedor.
 *  - Lead novo do site/anúncio → o responsável. Cliente que passou para você → o novo responsável.
 *  - Atualização no seu cliente feita por OUTRA pessoa → o responsável (no máximo uma por cliente por hora).
 *  - Post para aprovar → quem aprova posts; ajuste pedido → quem criou o post.
 *
 * Nunca avisa a própria pessoa do que ela mesma fez. Guarda só nome da loja, nº do pedido e link (LGPD).
 */
class NotificacaoService
{
    /** Tipos de evento da linha do tempo que contam como "atualização no cliente" */
    private const EVENTOS_CLIENTE = ['contato', 'ligacao', 'whatsapp', 'visita', 'email', 'nota', 'etapa', 'cobranca', 'promessa', 'busca_contato'];

    /**
     * Cria a notificação para cada pessoa da lista (menos $exceto, que é quem fez a ação).
     * $extra: cliente_id, pedido_id, texto
     */
    public static function avisar(array $usuarios, string $tipo, string $titulo, string $link, array $extra = [], ?int $exceto = null): int
    {
        $n = 0;
        foreach (array_unique(array_map('intval', array_filter($usuarios))) as $u) {
            if ($u === $exceto || !Banco::valor('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$u])) {
                continue;
            }
            Banco::inserir('notificacoes', array_filter([
                'usuario_id' => $u, 'tipo' => $tipo, 'titulo' => mb_substr($titulo, 0, 160), 'link' => mb_substr($link, 0, 255),
                'texto' => isset($extra['texto']) ? mb_substr((string) $extra['texto'], 0, 300) : null,
                'cliente_id' => $extra['cliente_id'] ?? null, 'pedido_id' => $extra['pedido_id'] ?? null,
                'criado_em' => $extra['criado_em'] ?? null,
            ], fn($v) => $v !== null && $v !== ''));
            $n++;
        }
        return $n;
    }

    /**
     * Pessoas ativas que TÊM a permissão (perfil + exceções; o Admin tem todas) E ACOMPANHAM a área (foco).
     * Ex.: "pedido para aprovar" vai para quem aprova pedidos e acompanha Aprovação — não para o admin do Marketing.
     */
    public static function destinatarios(string $permissao, string ...$areas): array
    {
        $ids = [];
        foreach (Banco::todos('SELECT id, perfil_id FROM usuarios WHERE ativo = 1') as $u) {
            $perms = \App\Models\Usuario::permissoes((int) $u['perfil_id'], (int) $u['id']);
            if ((in_array('*', $perms, true) || in_array($permissao, $perms, true)) && array_intersect($areas, Foco::areasDe((int) $u['id']))) {
                $ids[] = (int) $u['id'];
            }
        }
        return $ids;
    }

    private static function nomeCliente(int $clienteId): string
    {
        return (string) Banco::valor('SELECT COALESCE(NULLIF(nome_fantasia, \'\'), razao_social) FROM clientes WHERE id = ?', [$clienteId]);
    }

    private static function linkCliente(int $clienteId): string
    {
        return '/carteira/lista?abrir=' . $clienteId;
    }

    // ------------------------------------------------------------------
    // Ganchos chamados pelas regras do sistema
    // ------------------------------------------------------------------

    /** Pedido criado: Financeiro aprova; se veio do catálogo, o vendedor também fica sabendo */
    public static function pedidoCriado(int $pedidoId, ?int $quemFez = null): void
    {
        $p = Banco::um('SELECT id, cliente_id, vendedor_id, total, origem FROM pedidos WHERE id = ?', [$pedidoId]);
        if (!$p) {
            return;
        }
        $loja = self::nomeCliente((int) $p['cliente_id']);
        $extra = ['pedido_id' => $pedidoId, 'cliente_id' => (int) $p['cliente_id']];
        self::avisar(self::destinatarios('pedidos.aprovar', 'aprovacao'), 'pedido_aprovar', "Novo pedido #$pedidoId para aprovar", '/aprovacao',
            $extra + ['texto' => $loja . ' · ' . dinheiro((float) $p['total'])], $quemFez);
        if ($p['origem'] === 'catalogo' && $p['vendedor_id']) {
            self::avisar([(int) $p['vendedor_id']], 'pedido_catalogo', "$loja fez um pedido pelo catálogo", '/pedidos/' . $pedidoId,
                $extra + ['texto' => 'Pedido #' . $pedidoId . ' · ' . dinheiro((float) $p['total'])]);
        }
    }

    /** Aprovado / recusado / faturado: avisa o vendedor e encerra os avisos de "para aprovar" desse pedido */
    public static function pedidoMudou(int $pedidoId, string $situacao, ?int $quemFez, string $detalhe = ''): void
    {
        $p = Banco::um('SELECT id, cliente_id, vendedor_id FROM pedidos WHERE id = ?', [$pedidoId]);
        if (!$p) {
            return;
        }
        Banco::executar("UPDATE notificacoes SET lida_em = NOW() WHERE pedido_id = ? AND tipo = 'pedido_aprovar' AND lida_em IS NULL", [$pedidoId]);
        $titulos = ['aprovado' => "Pedido #$pedidoId aprovado", 'recusado' => "Pedido #$pedidoId recusado", 'faturado' => "Pedido #$pedidoId faturado"];
        if (!isset($titulos[$situacao]) || !$p['vendedor_id']) {
            return;
        }
        self::avisar([(int) $p['vendedor_id']], 'pedido_' . $situacao, $titulos[$situacao], '/pedidos/' . $pedidoId,
            ['pedido_id' => $pedidoId, 'cliente_id' => (int) $p['cliente_id'], 'texto' => trim(self::nomeCliente((int) $p['cliente_id']) . ($detalhe !== '' ? ' · ' . $detalhe : ''))], $quemFez);
    }

    /** Lead do site/anúncio: avisa o responsável (novo ou que voltou a entrar em contato) */
    public static function leadRecebido(int $clienteId, bool $novo): void
    {
        $resp = (int) Banco::valor('SELECT responsavel_id FROM clientes WHERE id = ?', [$clienteId]);
        $loja = self::nomeCliente($clienteId);
        self::avisar([$resp], 'lead_novo', $novo ? "Lead novo do site: $loja" : "$loja preencheu o formulário de novo", self::linkCliente($clienteId),
            ['cliente_id' => $clienteId, 'texto' => 'Responda hoje: lead respondido no mesmo dia vende mais.']);
    }

    /** Cliente passou para outra pessoa (transferência, reativação, prospecção enviada pelo gestor) */
    public static function clienteRecebido(int $clienteId, int $novoResponsavel, string $motivo, ?int $quemFez): void
    {
        self::avisar([$novoResponsavel], 'cliente_recebido', self::nomeCliente($clienteId) . ' agora está na sua carteira', self::linkCliente($clienteId),
            ['cliente_id' => $clienteId, 'texto' => $motivo], $quemFez);
    }

    /**
     * Atualização na linha do tempo do cliente (gancho de ClienteEvento::registrar):
     * avisa o responsável quando OUTRA pessoa mexeu — no máximo um aviso por cliente por hora.
     */
    public static function clienteAtualizado(int $clienteId, string $tipo, ?string $descricao, ?int $quemFez, ?string $ocorridoEm): void
    {
        if (!$quemFez || !in_array($tipo, self::EVENTOS_CLIENTE, true) || ($ocorridoEm && strtotime($ocorridoEm) < strtotime('-1 day'))) {
            return; // ação do sistema, tipo que não interessa ou evento antigo (importação de histórico)
        }
        $resp = (int) Banco::valor('SELECT responsavel_id FROM clientes WHERE id = ?', [$clienteId]);
        if (!$resp || $resp === $quemFez) {
            return;
        }
        if (Banco::valor("SELECT 1 FROM notificacoes WHERE usuario_id = ? AND cliente_id = ? AND tipo = 'cliente_atualizado' AND lida_em IS NULL
                AND criado_em >= NOW() - INTERVAL 1 HOUR", [$resp, $clienteId])) {
            return;
        }
        $quem = explode(' ', (string) Banco::valor('SELECT COALESCE(NULLIF(apelido, \'\'), nome) FROM usuarios WHERE id = ?', [$quemFez]))[0];
        $oque = \App\Models\ClienteEvento::TIPOS[$tipo] ?? 'Atualização';
        self::avisar([$resp], 'cliente_atualizado', self::nomeCliente($clienteId) . ": $oque por $quem", self::linkCliente($clienteId),
            ['cliente_id' => $clienteId, 'texto' => $descricao ? mb_strimwidth($descricao, 0, 140, '…') : null]);
    }

    /** Post do mural: foi para aprovação → quem aprova; pediram ajuste → quem criou */
    public static function postParaAprovar(array $post, ?int $quemFez): void
    {
        self::avisar(self::destinatarios('marketing.aprovar', 'marketing'), 'post_aprovar', 'Post para aprovar: ' . $post['titulo'], '/marketing?post=' . (int) $post['id'], [], $quemFez);
    }

    public static function postAjuste(array $post, string $motivo, ?int $quemFez): void
    {
        self::avisar([(int) ($post['criado_por'] ?? 0)], 'post_ajuste', 'Ajuste pedido no post: ' . $post['titulo'], '/marketing?post=' . (int) $post['id'],
            ['texto' => mb_strimwidth($motivo, 0, 140, '…')], $quemFez);
    }

    // ------------------------------------------------------------------
    // Leitura
    // ------------------------------------------------------------------

    public static function naoLidas(int $usuarioId): int
    {
        return (int) Banco::valor('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL', [$usuarioId]);
    }

    public static function ultimas(int $usuarioId, int $limite = 15, int $depoisDe = 0): array
    {
        return Banco::todos('SELECT id, tipo, titulo, texto, link, cliente_id, pedido_id, lida_em, criado_em FROM notificacoes
            WHERE usuario_id = ? AND id > ? ORDER BY id DESC LIMIT ' . (int) $limite, [$usuarioId, $depoisDe]);
    }

    /** Clientes com novidade não lida para a pessoa (marca "novidade" na Carteira, Prospecção...) */
    public static function clientesComNovidade(int $usuarioId): array
    {
        return array_flip(array_map('intval', array_column(Banco::todos('SELECT DISTINCT cliente_id FROM notificacoes
            WHERE usuario_id = ? AND lida_em IS NULL AND cliente_id IS NOT NULL', [$usuarioId]), 'cliente_id')));
    }

    /** Pedidos com aviso não lido (etiqueta "novo" na Aprovação e em Pedidos) */
    public static function pedidosComNovidade(int $usuarioId): array
    {
        return array_flip(array_map('intval', array_column(Banco::todos('SELECT DISTINCT pedido_id FROM notificacoes
            WHERE usuario_id = ? AND lida_em IS NULL AND pedido_id IS NOT NULL', [$usuarioId]), 'pedido_id')));
    }

    private static ?array $clientesMarcados = null;
    private static ?array $pedidosMarcados = null;

    /** Etiqueta "novidade" para colocar ao lado do nome do cliente (vazia se não há aviso não lido) */
    public static function marca(int $clienteId): string
    {
        self::$clientesMarcados ??= self::clientesComNovidade((int) \App\Core\Auth::id());
        return isset(self::$clientesMarcados[$clienteId]) ? ' <span class="novidade" title="Tem novidade para você">novidade</span>' : '';
    }

    /** Etiqueta "novo" para pedidos com aviso não lido */
    public static function marcaPedido(int $pedidoId): string
    {
        self::$pedidosMarcados ??= self::pedidosComNovidade((int) \App\Core\Auth::id());
        return isset(self::$pedidosMarcados[$pedidoId]) ? ' <span class="novidade" title="Novo para você">novo</span>' : '';
    }

    /** Abre uma notificação: marca como lida e devolve o link (null se não for dessa pessoa) */
    public static function abrir(int $id, int $usuarioId): ?string
    {
        $n = Banco::um('SELECT link FROM notificacoes WHERE id = ? AND usuario_id = ?', [$id, $usuarioId]);
        if (!$n) {
            return null;
        }
        Banco::executar('UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE id = ?', [$id]);
        return $n['link'];
    }

    public static function marcarTodas(int $usuarioId): int
    {
        return Banco::executar('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL', [$usuarioId]);
    }

    /** Abrir a ficha do cliente marca como lidas as notificações daquele cliente */
    public static function marcarDoCliente(int $usuarioId, int $clienteId): void
    {
        Banco::executar('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND cliente_id = ? AND lida_em IS NULL', [$usuarioId, $clienteId]);
    }
}
