<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;

/**
 * Modelos de pedido: listas prontas de produtos e quantidades para montar pedidos e orçamentos mais rápido.
 *  - "pessoal": cada vendedor(a) cria os seus (ex.: "Sugestão de primeiro pedido").
 *  - "campanha": a gestão cria para todos os vendedores ou só para um setor, com data para sair do ar.
 * Não guarda preço: na hora de usar, o preço vem da tabela do cliente. Produtos com a marca do cliente não entram.
 */
class ModeloPedidoService
{
    /** Pode criar modelos de campanha (para a equipe)? */
    public static function podeCampanha(): bool
    {
        return Auth::pode('pedidos.ver_todos') || Auth::pode('setor.gestao');
    }

    /** Modelos que a pessoa pode usar agora: os dela + campanhas válidas (de todos ou de um setor dela). Com os itens. */
    public static function disponiveis(int $usuarioId): array
    {
        $funcoes = array_map(fn($f) => (int) $f['id'], Auth::usuario()['funcoes'] ?? []);
        $todos = Auth::pode('pedidos.ver_todos');
        $modelos = Banco::todos("SELECT m.*, f.nome AS setor, u.nome AS autor FROM pedido_modelos m LEFT JOIN funcoes f ON f.id = m.funcao_id
            LEFT JOIN usuarios u ON u.id = m.criado_por
            WHERE m.ativo = 1 AND ((m.tipo = 'pessoal' AND m.dono_id = ?) OR (m.tipo = 'campanha' AND (m.validade_ate IS NULL OR m.validade_ate >= ?)))
            ORDER BY m.tipo = 'campanha' DESC, m.nome", [$usuarioId, date('Y-m-d')]);
        $modelos = array_values(array_filter($modelos, fn($m) => $m['tipo'] === 'pessoal' || !$m['funcao_id'] || $todos || in_array((int) $m['funcao_id'], $funcoes, true)));
        foreach ($modelos as &$m) {
            $m['itens'] = [];
            foreach (Banco::todos('SELECT i.produto_id, i.quantidade, p.nome FROM pedido_modelo_itens i JOIN produtos p ON p.id = i.produto_id
                WHERE i.modelo_id = ? AND p.ativo = 1 ORDER BY p.nome', [$m['id']]) as $i) {
                $m['itens'][(int) $i['produto_id']] = (int) $i['quantidade'];
                $m['nomes'][] = $i['quantidade'] . '× ' . $i['nome'];
            }
        }
        return $modelos;
    }

    /**
     * Salva um modelo novo com as quantidades da tela. Devolve [id, erros].
     * $d: nome, descricao, tipo (pessoal|campanha), funcao_id, validade_ate
     */
    public static function salvar(array $d, array $quantidades, int $usuarioId): array
    {
        $nome = mb_substr(trim((string) ($d['nome'] ?? '')), 0, 120);
        $tipo = ($d['tipo'] ?? '') === 'campanha' ? 'campanha' : 'pessoal';
        $itens = [];
        foreach ($quantidades as $chave => $q) {
            if (ctype_digit((string) $chave) && (int) $q > 0) {
                $itens[(int) $chave] = min(9999, (int) $q); // "t{id}" (marca do cliente) não entra
            }
        }
        $erros = [];
        if ($nome === '') {
            $erros[] = 'Dê um nome ao modelo (ex.: "Primeiro pedido — loja pequena").';
        }
        if (!$itens) {
            $erros[] = 'Coloque as quantidades dos produtos antes de salvar o modelo.';
        }
        if ($tipo === 'campanha' && !self::podeCampanha()) {
            $erros[] = 'Só a gestão cria modelos de campanha para a equipe.';
        }
        if ($erros) {
            return [null, $erros];
        }
        $validos = array_map('intval', array_column(Banco::todos('SELECT id FROM produtos WHERE ativo = 1 AND id IN (' . implode(',', array_keys($itens)) . ')'), 'id'));
        $funcao = $tipo === 'campanha' && ctype_digit((string) ($d['funcao_id'] ?? '')) ? (int) $d['funcao_id'] : null;
        $validade = $tipo === 'campanha' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['validade_ate'] ?? '')) ? $d['validade_ate'] : null;
        $id = Banco::transacao(function () use ($nome, $tipo, $funcao, $validade, $itens, $validos, $usuarioId, $d) {
            $id = Banco::inserir('pedido_modelos', array_filter(['nome' => $nome, 'descricao' => mb_substr(trim((string) ($d['descricao'] ?? '')), 0, 300) ?: null,
                'tipo' => $tipo, 'dono_id' => $tipo === 'pessoal' ? $usuarioId : null, 'funcao_id' => $funcao, 'validade_ate' => $validade,
                'criado_por' => $usuarioId], fn($v) => $v !== null));
            foreach ($itens as $produto => $q) {
                if (in_array($produto, $validos, true)) {
                    Banco::inserir('pedido_modelo_itens', ['modelo_id' => $id, 'produto_id' => $produto, 'quantidade' => $q]);
                }
            }
            Auditoria::registrar('pedido_modelo', $id, 'criar', null, ['nome' => $nome, 'tipo' => $tipo, 'itens' => count($itens)]);
            return $id;
        });
        return [$id, []];
    }

    /** Tira o modelo do ar (fica guardado). Pessoal: só o dono. Campanha: a gestão. */
    public static function desativar(int $id, int $usuarioId): ?string
    {
        $m = Banco::um('SELECT * FROM pedido_modelos WHERE id = ? AND ativo = 1', [$id]);
        if (!$m) {
            return 'Modelo não encontrado.';
        }
        if ($m['tipo'] === 'pessoal' ? (int) $m['dono_id'] !== $usuarioId : !self::podeCampanha()) {
            return 'Você não pode tirar este modelo.';
        }
        Banco::executar('UPDATE pedido_modelos SET ativo = 0 WHERE id = ?', [$id]);
        Auditoria::registrar('pedido_modelo', $id, 'desativar', ['ativo' => 1], ['ativo' => 0]);
        return null;
    }
}
