<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;
use App\Models\Cidade;
use App\Models\Cliente;
use App\Models\ClienteEvento;
use App\Models\Listas;
use App\Models\Tarefa;

/**
 * Regras de negócio da Carteira.
 *
 * - Todo cliente tem um responsável. Quando ninguém é indicado, o cliente vai para a FUNÇÃO configurada
 *   (carteira.funcao_novos_leads) e o responsável é a pessoa dessa função com menos clientes.
 * - Sem compra há X dias (carteira.dias_inatividade) → vai para a fila da função de reativação.
 * - Reativado → volta para um vendedor.
 * - Toda mudança importante vira evento na linha do tempo e registro na auditoria.
 */
class CarteiraService
{
    /** Campos que a ficha/formulário pode alterar diretamente */
    public const CAMPOS_CADASTRO = ['razao_social', 'nome_fantasia', 'cnpj', 'endereco', 'bairro', 'cep', 'cidade', 'uf', 'segmento_id',
        'telefone', 'whatsapp', 'email', 'instagram', 'facebook', 'site', 'mix_produtos', 'tabela_preco_id', 'origem_id', 'observacoes'];

    // ------------------------------------------------------------------
    // Responsável e funções
    // ------------------------------------------------------------------

    /** Id da função pelo slug (ex.: 'prospeccao_reativacao') */
    public static function funcaoId(string $slug): ?int
    {
        $id = Banco::valor('SELECT id FROM funcoes WHERE slug = ? AND ativa = 1', [$slug]);
        return $id ? (int) $id : null;
    }

    /**
     * Escolhe a pessoa da função que tem MENOS clientes (distribui a carga).
     * Se a função não tiver ninguém, usa um gestor/admin para o cliente nunca ficar sem responsável.
     */
    public static function responsavelDaFuncao(int $funcaoId): ?int
    {
        $id = Banco::valor(
            'SELECT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id AND uf.funcao_id = ?
              WHERE u.ativo = 1
              ORDER BY (SELECT COUNT(*) FROM clientes c WHERE c.responsavel_id = u.id AND c.excluido_em IS NULL), u.id LIMIT 1',
            [$funcaoId]
        );
        if (!$id) {
            $id = Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.ativo = 1 AND p.slug IN ('gestor','admin') ORDER BY p.slug = 'admin', u.id LIMIT 1");
        }
        return $id ? (int) $id : null;
    }

    /** Função principal de uma pessoa (a primeira, ignorando a de reativação) */
    public static function funcaoPrincipal(int $usuarioId): ?int
    {
        $reativacao = self::funcaoId((string) Config::get('carteira.funcao_reativacao', 'prospeccao_reativacao'));
        $id = Banco::valor(
            'SELECT f.id FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ? ORDER BY f.id = ?, f.ordem LIMIT 1',
            [$usuarioId, $reativacao ?? 0]
        );
        return $id ? (int) $id : null;
    }

    // ------------------------------------------------------------------
    // Cadastro
    // ------------------------------------------------------------------

    /**
     * Cria um cliente/lead. Aceita nomes no lugar de ids: 'segmento', 'etapa', 'origem' (texto).
     * Devolve o id criado.
     */
    public static function criar(array $d, ?int $usuarioId, string $descricaoEvento = 'Cadastro criado'): int
    {
        $dados = array_intersect_key($d, array_flip([...self::CAMPOS_CADASTRO, 'tipo', 'segmento_original', 'fonte_url', 'validacao',
            'prioridade', 'contatado', 'ultimo_contato_em', 'origem_detalhe', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
            'utm_content', 'ultima_compra_em', 'intervalo_medio_dias', 'lat', 'lng', 'responsavel_id', 'funcao_id', 'etapa_id', 'criado_em']));

        $dados['uf'] = strtoupper((string) ($dados['uf'] ?? '')) ?: null;
        $dados['segmento_id'] ??= Listas::idPorNome(Listas::segmentos(), $d['segmento'] ?? 'Outros') ?? Listas::idPorNome(Listas::segmentos(), 'Outros');
        $dados['etapa_id'] ??= Listas::idPorNome(Listas::etapas(), $d['etapa'] ?? '') ?? (int) Listas::etapas()[0]['id'];
        $dados['origem_id'] ??= Listas::idPorNome(Listas::origens(), $d['origem'] ?? '');
        $dados['tabela_preco_id'] ??= (int) (Listas::tabelasPreco()[0]['id'] ?? 0) ?: null;
        $dados['prioridade'] = Normalizar::prioridade($dados['prioridade'] ?? '', Listas::prioridades());
        $dados['cidade_id'] = Cidade::idOuCriar($dados['cidade'] ?? null, $dados['uf']);
        $dados['regiao_id'] = Listas::regiaoDaUf($dados['uf']);
        $dados['criado_por'] = $usuarioId;

        // Regra: todo cliente tem responsável (pessoa) e função (fila)
        if (empty($dados['responsavel_id'])) {
            $dados['funcao_id'] ??= self::funcaoId((string) Config::get('carteira.funcao_novos_leads', 'prospeccao_reativacao'));
            $dados['responsavel_id'] = $dados['funcao_id'] ? self::responsavelDaFuncao($dados['funcao_id']) : $usuarioId;
        }
        $dados['funcao_id'] ??= self::funcaoPrincipal((int) $dados['responsavel_id']);

        $id = Banco::inserir('clientes', $dados);
        CarteirasService::encaixar([$id]); // entra na carteira do responsável naquele setor
        ClienteEvento::registrar($id, 'importacao', $descricaoEvento, $usuarioId, null, null, $dados['criado_em'] ?? null);
        return $id;
    }

    /**
     * Salva a ficha. $esperado = valor de atualizado_em quando a ficha foi aberta:
     * se outra pessoa salvou nesse meio-tempo, lança ConflitoException (como a Carteira V4 fazia).
     */
    public static function salvar(int $id, array $novos, ?string $esperado, int $usuarioId, bool $podeTransferir): array
    {
        return Banco::transacao(function () use ($id, $novos, $esperado, $usuarioId, $podeTransferir) {
            $antes = Banco::um('SELECT * FROM clientes WHERE id = ? FOR UPDATE', [$id]);
            if ($esperado !== null && $esperado !== '' && $antes['atualizado_em'] !== $esperado) {
                throw new ConflitoException('Este cliente foi alterado por outra pessoa enquanto você editava. Os dados mais recentes foram carregados.');
            }

            $dados = array_intersect_key($novos, array_flip([...self::CAMPOS_CADASTRO, 'prioridade', 'etapa_id']));
            if (isset($dados['uf']) || isset($dados['cidade'])) {
                $dados['uf'] = strtoupper((string) ($dados['uf'] ?? $antes['uf']));
                $dados['cidade_id'] = Cidade::idOuCriar($dados['cidade'] ?? $antes['cidade'], $dados['uf']);
                $dados['regiao_id'] = Listas::regiaoDaUf($dados['uf']);
            }
            if (isset($dados['prioridade'])) {
                $dados['prioridade'] = Normalizar::prioridade($dados['prioridade'], Listas::prioridades());
            }

            // Mudança de etapa → evento na linha do tempo
            if (isset($dados['etapa_id']) && (int) $dados['etapa_id'] !== (int) $antes['etapa_id']) {
                self::eventoEtapa($id, (int) $antes['etapa_id'], (int) $dados['etapa_id'], $usuarioId);
            }
            // Troca de responsável: só quem tem permissão
            if ($podeTransferir && !empty($novos['responsavel_id']) && (int) $novos['responsavel_id'] !== (int) $antes['responsavel_id']) {
                $dados['responsavel_id'] = (int) $novos['responsavel_id'];
                $dados['funcao_id'] = self::funcaoPrincipal($dados['responsavel_id']) ?? $antes['funcao_id'];
                self::eventoResponsavel($id, (int) $antes['responsavel_id'], $dados['responsavel_id'], $usuarioId, 'Transferido manualmente');
            }
            if ($dados) {
                Banco::atualizar('clientes', $id, $dados);
                if (isset($dados['responsavel_id'])) {
                    CarteirasService::encaixar([$id]); // transferido: vai para a carteira do novo responsável
                }
            }
            // Próximo contato (cria/atualiza a tarefa de retorno do responsável)
            if (array_key_exists('proximo_contato', $novos)) {
                $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $novos['proximo_contato']) ? $novos['proximo_contato'] : null;
                Tarefa::definirRetorno($id, (int) ($dados['responsavel_id'] ?? $antes['responsavel_id']), $data);
            }
            Auditoria::registrar('cliente', $id, 'editar', $antes, array_intersect_key(Banco::um('SELECT * FROM clientes WHERE id = ?', [$id]), $dados));
            return Cliente::buscar($id);
        });
    }

    /** Muda só a etapa (usado no arrastar do funil) */
    public static function mudarEtapa(int $id, int $etapaId, int $usuarioId): void
    {
        $antes = Banco::um('SELECT etapa_id FROM clientes WHERE id = ?', [$id]);
        if ((int) $antes['etapa_id'] === $etapaId) {
            return;
        }
        Banco::atualizar('clientes', $id, ['etapa_id' => $etapaId]);
        self::eventoEtapa($id, (int) $antes['etapa_id'], $etapaId, $usuarioId);
        Auditoria::registrar('cliente', $id, 'mudar_etapa', ['etapa_id' => $antes['etapa_id']], ['etapa_id' => $etapaId]);
    }

    // ------------------------------------------------------------------
    // Contatos
    // ------------------------------------------------------------------

    /** Canais de contato aceitos e o tipo de evento de cada um */
    public const CANAIS = ['WhatsApp' => 'whatsapp', 'Ligação' => 'ligacao', 'Visita presencial' => 'visita', 'E-mail' => 'email', 'Outro' => 'contato'];

    /**
     * Registra um contato: entra na linha do tempo, marca como contatado e, se o lead ainda estava
     * nas etapas iniciais, avança para a etapa configurada (carteira.etapa_apos_contato).
     */
    public static function registrarContato(int $id, string $data, string $canal, string $resumo, int $usuarioId): void
    {
        $tipo = self::CANAIS[$canal] ?? 'contato';
        ClienteEvento::registrar($id, $tipo, $resumo !== '' ? $resumo : null, $usuarioId, $canal, null, $data . ' ' . date('H:i:s'));
        self::marcarContatado($id, $data, $usuarioId);
    }

    /** Ligação com 1 clique (plano B do Ligue Talk) */
    public static function registrarLigacao(int $id, string $resultado, int $usuarioId): void
    {
        Integracoes::telefonia()->registrarLigacao($id, $usuarioId, $resultado);
        self::marcarContatado($id, date('Y-m-d'), $usuarioId);
    }

    private static function marcarContatado(int $id, string $data, int $usuarioId): void
    {
        $c = Banco::um('SELECT c.etapa_id, c.ultimo_contato_em, e.ordem FROM clientes c LEFT JOIN funil_etapas e ON e.id = c.etapa_id WHERE c.id = ?', [$id]);
        $dados = ['contatado' => 1];
        if (!$c['ultimo_contato_em'] || $data > $c['ultimo_contato_em']) {
            $dados['ultimo_contato_em'] = $data;
        }
        $etapaContato = Banco::um('SELECT id, ordem FROM funil_etapas WHERE nome = ?', [Config::get('carteira.etapa_apos_contato', 'Contato realizado')]);
        if ($etapaContato && (int) $c['ordem'] < (int) $etapaContato['ordem']) {
            $dados['etapa_id'] = (int) $etapaContato['id'];
            self::eventoEtapa($id, (int) $c['etapa_id'], (int) $etapaContato['id'], $usuarioId);
        }
        Banco::atualizar('clientes', $id, $dados);
    }

    // ------------------------------------------------------------------
    // Reativação
    // ------------------------------------------------------------------

    /**
     * Regra de inatividade: clientes que compraram, mas estão sem comprar há mais de X dias,
     * passam para a fila da função de reativação. Devolve quantos foram movidos.
     * Roda pelo cron (cron/fila_reativacao.php) ou pelo botão "processar agora".
     */
    public static function processarInatividade(?int $usuarioId = null): int
    {
        $dias = (int) Config::get('carteira.dias_inatividade', 90);
        $funcaoId = self::funcaoId((string) Config::get('carteira.funcao_reativacao', 'prospeccao_reativacao'));
        if (!$funcaoId) {
            return 0;
        }
        $clientes = Banco::todos(
            'SELECT id, responsavel_id, ultima_compra_em, DATEDIFF(CURDATE(), ultima_compra_em) AS dias
               FROM clientes WHERE excluido_em IS NULL AND ultima_compra_em IS NOT NULL
                AND ultima_compra_em < CURDATE() - INTERVAL ? DAY AND (funcao_id IS NULL OR funcao_id <> ?)
                AND responsavel_id IS NOT NULL', // carteira sem vendedor(a): os clientes ficam parados até alguém assumir
            [$dias, $funcaoId]
        );
        foreach ($clientes as $c) {
            $novo = self::responsavelDaFuncao($funcaoId);
            Banco::atualizar('clientes', (int) $c['id'], ['funcao_id' => $funcaoId, 'responsavel_id' => $novo]);
            self::eventoResponsavel((int) $c['id'], (int) $c['responsavel_id'], (int) $novo, $usuarioId,
                "Sem compra há {$c['dias']} dias: enviado para a fila de reativação", false);
            $recebidos[(int) $novo] = ($recebidos[(int) $novo] ?? 0) + 1;
        }
        // Um aviso por pessoa (e não um por cliente): "N clientes entraram na sua fila de reativação"
        foreach ($recebidos ?? [] as $pessoa => $qtd) {
            NotificacaoService::avisar([$pessoa], 'cliente_recebido', "$qtd cliente(s) entraram na sua fila de reativação", '/reativacao',
                ['texto' => "Sem compra há mais de $dias dias."], $usuarioId);
        }
        if ($clientes) {
            CarteirasService::encaixar(array_column($clientes, 'id'));
            Auditoria::registrar('carteira', null, 'processar_inatividade', null, ['clientes_movidos' => count($clientes), 'dias' => $dias]);
        }
        return count($clientes);
    }

    /** Cliente reativado volta para um vendedor (e sai da fila de reativação) */
    public static function devolverParaVendedor(int $id, int $vendedorId, int $usuarioId): void
    {
        $antes = Banco::um('SELECT responsavel_id FROM clientes WHERE id = ?', [$id]);
        Banco::atualizar('clientes', $id, ['responsavel_id' => $vendedorId, 'funcao_id' => self::funcaoPrincipal($vendedorId)]);
        self::eventoResponsavel($id, (int) $antes['responsavel_id'], $vendedorId, $usuarioId, 'Reativado: devolvido para vendedor');
        CarteirasService::encaixar([$id]);
        Auditoria::registrar('cliente', $id, 'devolver_vendedor', ['responsavel_id' => $antes['responsavel_id']], ['responsavel_id' => $vendedorId]);
    }

    // ------------------------------------------------------------------
    // Eventos padronizados
    // ------------------------------------------------------------------

    private static function eventoEtapa(int $id, int $de, int $para, ?int $usuarioId): void
    {
        $nomes = array_column(Listas::etapas(false), 'nome', 'id');
        ClienteEvento::registrar($id, 'etapa', ($nomes[$de] ?? '—') . ' → ' . ($nomes[$para] ?? '—'), $usuarioId, null, ['de' => $de, 'para' => $para]);
    }

    private static function eventoResponsavel(int $id, int $de, int $para, ?int $usuarioId, string $motivo, bool $avisar = true): void
    {
        if ($avisar && $para && $para !== $de) {
            NotificacaoService::clienteRecebido($id, $para, $motivo, $usuarioId);
        }
        $nomes = array_column(Banco::todos('SELECT id, nome FROM usuarios WHERE id IN (?, ?)', [$de, $para]), 'nome', 'id');
        ClienteEvento::registrar($id, 'responsavel', $motivo . ': ' . ($nomes[$de] ?? '—') . ' → ' . ($nomes[$para] ?? '—'), $usuarioId, null, ['de' => $de, 'para' => $para]);
    }
}
