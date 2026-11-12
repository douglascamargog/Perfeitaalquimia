<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;
use App\Models\ClienteEvento;
use App\Models\Listas;

/**
 * Regras da Cobrança.
 *
 * - Um cliente tem no máximo UM caso aberto; títulos novos entram no mesmo caso.
 * - O caso vai para a pessoa da função configurada (cobranca.funcao) com MENOS casos abertos.
 *   Se a função não tiver ninguém, vai para alguém do perfil Financeiro (e por último gestor/admin).
 * - Tentativas sem sucesso somam; ao chegar em cobranca.tentativas_para_sem_contato o caso vai
 *   para a etapa do tipo "sem_contato" (hora de buscar contatos novos).
 * - Etapas de encerramento (pago, acordo, juridico, perda) fecham o caso.
 * - Tudo vira evento na linha do tempo do cliente e registro na auditoria.
 */
class CobrancaService
{
    /** Tipos de etapa que encerram o caso */
    public const TIPOS_ENCERRAMENTO = ['pago', 'acordo', 'juridico', 'perda'];

    /** Resultados de uma tentativa de contato. true = falou com o cliente */
    public const RESULTADOS = [
        'Atendeu'               => true,
        'Respondeu mensagem'    => true,
        'Não atendeu'           => false,
        'Caixa postal'          => false,
        'Mensagem sem resposta' => false,
        'Número errado'         => false,
    ];

    // ------------------------------------------------------------------
    // Etapas
    // ------------------------------------------------------------------

    /** Primeira etapa ativa de um tipo (ex.: 'sem_contato'), ou a primeira etapa aberta */
    public static function etapaDoTipo(string $tipo): ?array
    {
        foreach (Listas::etapasCobranca() as $etapa) {
            if ($etapa['tipo'] === $tipo) {
                return $etapa;
            }
        }
        return null;
    }

    /** Primeira coluna do funil (entrada) e a seguinte em andamento ("Tentativa de contato") */
    public static function etapaEntrada(): array
    {
        return self::etapaDoTipo('aberta') ?? Listas::etapasCobranca()[0];
    }

    public static function etapaTentativa(): array
    {
        $abertas = array_values(array_filter(Listas::etapasCobranca(), fn($e) => $e['tipo'] === 'aberta'));
        return $abertas[1] ?? $abertas[0] ?? self::etapaEntrada();
    }

    private static function etapa(int $id): ?array
    {
        return Banco::um('SELECT * FROM cobranca_etapas WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------------------
    // Responsável
    // ------------------------------------------------------------------

    /** Pessoa da função de cobrança com menos casos abertos (ou alguém do Financeiro, ou gestor/admin) */
    public static function responsavel(): ?int
    {
        $menosCasos = '(SELECT COUNT(*) FROM cobrancas cb WHERE cb.responsavel_id = u.id AND cb.situacao = \'aberta\')';
        $funcaoId = CarteiraService::funcaoId((string) Config::get('cobranca.funcao', 'cobranca'));
        $id = $funcaoId ? Banco::valor(
            "SELECT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id AND uf.funcao_id = ?
              WHERE u.ativo = 1 ORDER BY $menosCasos, u.id LIMIT 1",
            [$funcaoId]
        ) : null;
        $id = $id ?: Banco::valor(
            "SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
              WHERE u.ativo = 1 AND p.slug IN ('financeiro','gestor','admin')
              ORDER BY FIELD(p.slug, 'financeiro','gestor','admin'), $menosCasos, u.id LIMIT 1"
        );
        return $id ? (int) $id : null;
    }

    // ------------------------------------------------------------------
    // Abrir / atualizar caso
    // ------------------------------------------------------------------

    /** Caso aberto do cliente (ou null) */
    public static function casoAberto(int $clienteId): ?array
    {
        return Banco::um("SELECT * FROM cobrancas WHERE cliente_id = ? AND situacao = 'aberta' ORDER BY id DESC LIMIT 1", [$clienteId]);
    }

    /**
     * Abre um caso para o cliente (ou acrescenta títulos ao caso aberto). Devolve o id do caso.
     * $titulos: lista de [documento, parcela, valor, vencimento, omie_codigo?]
     */
    public static function abrir(int $clienteId, array $titulos, string $origem, ?int $usuarioId, string $observacao = ''): int
    {
        return Banco::transacao(function () use ($clienteId, $titulos, $origem, $usuarioId, $observacao) {
            $caso = self::casoAberto($clienteId);
            $novo = !$caso;
            if ($novo) {
                $dados = [
                    'cliente_id' => $clienteId, 'etapa_id' => (int) self::etapaEntrada()['id'], 'responsavel_id' => self::responsavel(),
                    'origem' => $origem, 'criado_por' => $usuarioId, 'observacoes' => $observacao !== '' ? $observacao : null,
                ];
                $id = Banco::inserir('cobrancas', $dados);
                Auditoria::registrar('cobranca', $id, 'criar', null, $dados);
            } else {
                $id = (int) $caso['id'];
            }

            $acrescentados = 0;
            foreach ($titulos as $t) {
                if (!empty($t['omie_codigo']) && Banco::valor('SELECT 1 FROM cobranca_titulos WHERE omie_codigo = ?', [$t['omie_codigo']])) {
                    continue; // já importado
                }
                Banco::inserir('cobranca_titulos', [
                    'cobranca_id' => $id, 'documento' => mb_substr((string) ($t['documento'] ?? ''), 0, 40) ?: null,
                    'parcela' => mb_substr((string) ($t['parcela'] ?? ''), 0, 10) ?: null, 'valor' => round((float) $t['valor'], 2),
                    'vencimento' => $t['vencimento'], 'omie_codigo' => $t['omie_codigo'] ?? null,
                ]);
                $acrescentados++;
            }
            self::recalcular($id);
            Banco::executar("UPDATE clientes SET credito_situacao = 'atraso' WHERE id = ?", [$clienteId]);

            if ($novo) {
                $c = Banco::um('SELECT valor_total FROM cobrancas WHERE id = ?', [$id]);
                ClienteEvento::registrar($clienteId, 'cobranca', 'Enviado para a Cobrança · ' . dinheiro((float) $c['valor_total'])
                    . ($observacao !== '' ? " — $observacao" : ''), $usuarioId, $origem === 'erp' ? 'ERP' : null, ['cobranca_id' => $id]);
            } elseif ($acrescentados) {
                ClienteEvento::registrar($clienteId, 'cobranca', "$acrescentados título(s) novo(s) na cobrança", $usuarioId, null, ['cobranca_id' => $id]);
            }
            return $id;
        });
    }

    /** Recalcula valor em aberto e vencimento mais antigo a partir dos títulos não pagos */
    public static function recalcular(int $id): void
    {
        Banco::executar(
            'UPDATE cobrancas SET
                valor_total = (SELECT COALESCE(SUM(valor), 0) FROM cobranca_titulos WHERE cobranca_id = ? AND pago_em IS NULL),
                vencimento_mais_antigo = (SELECT MIN(vencimento) FROM cobranca_titulos WHERE cobranca_id = ? AND pago_em IS NULL)
              WHERE id = ?',
            [$id, $id, $id]
        );
    }

    // ------------------------------------------------------------------
    // Trabalho do dia a dia
    // ------------------------------------------------------------------

    /** Registra uma tentativa de contato. Devolve uma mensagem para a tela */
    public static function registrarTentativa(int $id, string $resultado, string $canal, string $nota, int $usuarioId): string
    {
        return Banco::transacao(function () use ($id, $resultado, $canal, $nota, $usuarioId) {
            $caso = Banco::um('SELECT * FROM cobrancas WHERE id = ? FOR UPDATE', [$id]);
            $etapa = self::etapa((int) $caso['etapa_id']);
            $sucesso = self::RESULTADOS[$resultado] ?? false;
            $tentativas = $sucesso ? 0 : (int) $caso['tentativas_sem_sucesso'] + 1;
            $dados = ['tentativas_sem_sucesso' => $tentativas, 'ultimo_contato_em' => date('Y-m-d H:i:s')];
            $mensagem = 'Tentativa registrada.';

            // Saiu da entrada: vai para "Tentativa de contato"
            if ((int) $caso['etapa_id'] === (int) self::etapaEntrada()['id']) {
                $dados['etapa_id'] = (int) self::etapaTentativa()['id'];
            }
            // Muitas tentativas sem sucesso: "Sem contato — buscar contatos"
            $limite = max(1, (int) Config::get('cobranca.tentativas_para_sem_contato', 3));
            $semContato = self::etapaDoTipo('sem_contato');
            if (!$sucesso && $semContato && $tentativas >= $limite && !in_array($etapa['tipo'], ['sem_contato', 'promessa'], true)) {
                $dados['etapa_id'] = (int) $semContato['id'];
                $mensagem = "$tentativas tentativas sem sucesso: o caso foi para \"{$semContato['nome']}\". Que tal buscar contatos novos com IA?";
            }
            // Falou com o cliente e o caso estava em "sem contato": volta para tentativa
            if ($sucesso && $etapa['tipo'] === 'sem_contato') {
                $dados['etapa_id'] = (int) self::etapaTentativa()['id'];
            }
            Banco::atualizar('cobrancas', $id, $dados);

            $descricao = "Cobrança — $resultado" . ($nota !== '' ? ": $nota" : '');
            ClienteEvento::registrar((int) $caso['cliente_id'], 'cobranca', $descricao, $usuarioId, $canal, ['cobranca_id' => $id]);
            if (isset($dados['etapa_id']) && (int) $dados['etapa_id'] !== (int) $caso['etapa_id']) {
                self::eventoEtapa($caso, (int) $dados['etapa_id'], $usuarioId);
            }
            if ($sucesso) {
                Banco::executar('UPDATE clientes SET ultimo_contato_em = CURDATE() WHERE id = ?', [$caso['cliente_id']]);
            }
            return $mensagem;
        });
    }

    /** Promessa de pagamento: guarda data/valor, move para a etapa "promessa" e cria a tarefa de conferir o pagamento */
    public static function registrarPromessa(int $id, string $data, float $valor, int $usuarioId): void
    {
        Banco::transacao(function () use ($id, $data, $valor, $usuarioId) {
            $caso = Banco::um('SELECT * FROM cobrancas WHERE id = ? FOR UPDATE', [$id]);
            $etapa = self::etapaDoTipo('promessa');
            $dados = ['promessa_em' => $data, 'promessa_valor' => $valor ?: $caso['valor_total'], 'tentativas_sem_sucesso' => 0,
                'ultimo_contato_em' => date('Y-m-d H:i:s')];
            if ($etapa) {
                $dados['etapa_id'] = (int) $etapa['id'];
            }
            Banco::atualizar('cobrancas', $id, $dados);
            Auditoria::registrar('cobranca', $id, 'promessa', $caso, $dados);

            // Tarefa para o responsável conferir o pagamento no dia (substitui a anterior deste caso)
            Banco::executar("UPDATE tarefas SET concluida_em = NOW() WHERE cliente_id = ? AND tipo = 'cobranca' AND concluida_em IS NULL", [$caso['cliente_id']]);
            Banco::inserir('tarefas', ['cliente_id' => $caso['cliente_id'], 'usuario_id' => $caso['responsavel_id'] ?: $usuarioId,
                'titulo' => 'Conferir pagamento prometido · ' . dinheiro((float) $dados['promessa_valor']), 'tipo' => 'cobranca', 'vence_em' => $data]);

            ClienteEvento::registrar((int) $caso['cliente_id'], 'promessa', 'Prometeu pagar ' . dinheiro((float) $dados['promessa_valor']) . ' em ' . data_br($data),
                $usuarioId, null, ['cobranca_id' => $id]);
            if (isset($dados['etapa_id']) && (int) $dados['etapa_id'] !== (int) $caso['etapa_id']) {
                self::eventoEtapa($caso, (int) $dados['etapa_id'], $usuarioId);
            }
        });
    }

    /** Muda a etapa (arrastar no funil). Etapas de encerramento fecham o caso */
    public static function mudarEtapa(int $id, int $etapaId, ?int $usuarioId, string $motivo = ''): void
    {
        Banco::transacao(function () use ($id, $etapaId, $usuarioId, $motivo) {
            $caso = Banco::um('SELECT * FROM cobrancas WHERE id = ? FOR UPDATE', [$id]);
            $etapa = self::etapa($etapaId);
            if (!$caso || !$etapa || (int) $caso['etapa_id'] === $etapaId) {
                return;
            }
            $dados = ['etapa_id' => $etapaId];
            if (in_array($etapa['tipo'], self::TIPOS_ENCERRAMENTO, true)) {
                $dados += ['situacao' => 'encerrada', 'encerrada_em' => date('Y-m-d H:i:s'), 'motivo_encerramento' => mb_substr($motivo !== '' ? $motivo : $etapa['nome'], 0, 80)];
                if ($etapa['tipo'] === 'pago') {
                    Banco::executar('UPDATE cobranca_titulos SET pago_em = CURDATE() WHERE cobranca_id = ? AND pago_em IS NULL', [$id]);
                }
                Banco::executar("UPDATE tarefas SET concluida_em = NOW() WHERE cliente_id = ? AND tipo = 'cobranca' AND concluida_em IS NULL", [$caso['cliente_id']]);
                // Cliente volta a "ok" no crédito quando pagou ou fez acordo (jurídico/perda continuam bloqueando o boleto)
                if (in_array($etapa['tipo'], ['pago', 'acordo'], true)) {
                    Banco::executar("UPDATE clientes SET credito_situacao = 'ok' WHERE id = ?", [$caso['cliente_id']]);
                }
            } elseif ($caso['situacao'] === 'encerrada') {
                $dados += ['situacao' => 'aberta', 'encerrada_em' => null, 'motivo_encerramento' => null]; // reaberto
                Banco::executar("UPDATE clientes SET credito_situacao = 'atraso' WHERE id = ?", [$caso['cliente_id']]);
            }
            Banco::atualizar('cobrancas', $id, $dados);
            if (isset($dados['situacao']) && $dados['situacao'] === 'encerrada') {
                self::recalcular($id);
            }
            Auditoria::registrar('cobranca', $id, 'etapa', $caso, $dados);
            self::eventoEtapa($caso, $etapaId, $usuarioId);
        });
    }

    /** Troca o responsável pelo caso */
    public static function transferir(int $id, int $responsavelId, int $usuarioId): void
    {
        $caso = Banco::um('SELECT * FROM cobrancas WHERE id = ?', [$id]);
        if ((int) $caso['responsavel_id'] === $responsavelId) {
            return;
        }
        Banco::atualizar('cobrancas', $id, ['responsavel_id' => $responsavelId]);
        Auditoria::registrar('cobranca', $id, 'responsavel', ['responsavel_id' => $caso['responsavel_id']], ['responsavel_id' => $responsavelId]);
        $nome = (string) Banco::valor('SELECT nome FROM usuarios WHERE id = ?', [$responsavelId]);
        ClienteEvento::registrar((int) $caso['cliente_id'], 'cobranca', "Cobrança passada para $nome", $usuarioId, null, ['cobranca_id' => $id]);
    }

    /** Marca um título como pago; se não sobrar nada em aberto, encerra o caso como pago */
    public static function baixarTitulo(int $tituloId, ?int $usuarioId): void
    {
        $titulo = Banco::um('SELECT * FROM cobranca_titulos WHERE id = ? AND pago_em IS NULL', [$tituloId]);
        if (!$titulo) {
            return;
        }
        Banco::executar('UPDATE cobranca_titulos SET pago_em = CURDATE() WHERE id = ?', [$tituloId]);
        $caso = Banco::um('SELECT * FROM cobrancas WHERE id = ?', [$titulo['cobranca_id']]);
        self::recalcular((int) $caso['id']);
        Auditoria::registrar('cobranca', (int) $caso['id'], 'titulo_pago', $titulo, ['pago_em' => date('Y-m-d')]);
        ClienteEvento::registrar((int) $caso['cliente_id'], 'cobranca', 'Título pago: ' . trim($titulo['documento'] . ' ' . $titulo['parcela']) . ' · ' . dinheiro((float) $titulo['valor']),
            $usuarioId, null, ['cobranca_id' => (int) $caso['id']]);
        if (!Banco::valor('SELECT 1 FROM cobranca_titulos WHERE cobranca_id = ? AND pago_em IS NULL', [$caso['id']]) && ($pago = self::etapaDoTipo('pago'))) {
            self::mudarEtapa((int) $caso['id'], (int) $pago['id'], $usuarioId, 'Todos os títulos pagos');
        }
    }

    private static function eventoEtapa(array $caso, int $etapaNova, ?int $usuarioId): void
    {
        $nomes = array_column(Listas::etapasCobranca(false), 'nome', 'id');
        ClienteEvento::registrar((int) $caso['cliente_id'], 'cobranca',
            'Cobrança: ' . ($nomes[$caso['etapa_id']] ?? '—') . ' → ' . ($nomes[$etapaNova] ?? '—'), $usuarioId, null, ['cobranca_id' => (int) $caso['id']]);
    }

    // ------------------------------------------------------------------
    // Automático (ERP e cron)
    // ------------------------------------------------------------------

    /**
     * Lê os títulos do ERP (OmieFake no protótipo):
     *   - vencidos há X dias (cobranca.dias_atraso_entrada) → abre/atualiza o caso do cliente;
     *   - pagos no ERP → baixa o título aqui (e encerra o caso se zerou).
     * Devolve contagens para mostrar na tela.
     */
    public static function importarDoErp(?int $usuarioId): array
    {
        $dias = max(0, (int) Config::get('cobranca.dias_atraso_entrada', 5));
        $limite = date('Y-m-d', strtotime("-$dias days"));
        $porCliente = [];
        $pagos = 0;
        foreach (Integracoes::erp()->listarTitulos() as $t) {
            if (!empty($t['pago_em'])) {
                $local = Banco::um('SELECT id FROM cobranca_titulos WHERE omie_codigo = ? AND pago_em IS NULL', [$t['omie_codigo']]);
                if ($local) {
                    self::baixarTitulo((int) $local["id"], $usuarioId);
                    $pagos++;
                }
                continue;
            }
            if ($t['vencimento'] > $limite) {
                continue; // ainda dentro da tolerância
            }
            $porCliente[$t['cnpj']][] = $t;
        }

        $casos = $clientesSemCadastro = 0;
        foreach ($porCliente as $cnpj => $titulos) {
            $clienteId = Banco::valor('SELECT id FROM clientes WHERE cnpj = ? AND excluido_em IS NULL', [$cnpj]);
            if (!$clienteId) {
                $clientesSemCadastro++;
                continue;
            }
            $novos = array_filter($titulos, fn($t) => !Banco::valor('SELECT 1 FROM cobranca_titulos WHERE omie_codigo = ?', [$t['omie_codigo']]));
            if ($novos) {
                self::abrir((int) $clienteId, array_values($novos), 'erp', $usuarioId);
                $casos++;
            }
        }
        return ['casos' => $casos, 'pagos' => $pagos, 'sem_cadastro' => $clientesSemCadastro];
    }

    /** Promessas vencidas e não pagas voltam para "Tentativa de contato" (rodado pelo cron e ao abrir a tela) */
    public static function verificarPromessas(): int
    {
        $promessa = self::etapaDoTipo('promessa');
        if (!$promessa) {
            return 0;
        }
        $quebradas = Banco::todos("SELECT * FROM cobrancas WHERE situacao = 'aberta' AND etapa_id = ? AND promessa_em < CURDATE()", [$promessa['id']]);
        $volta = (int) self::etapaTentativa()['id'];
        foreach ($quebradas as $caso) {
            Banco::atualizar('cobrancas', (int) $caso['id'], ['etapa_id' => $volta]);
            ClienteEvento::registrar((int) $caso['cliente_id'], 'promessa', 'Promessa de ' . data_br($caso['promessa_em']) . ' não cumprida: caso voltou para tentativa de contato',
                null, null, ['cobranca_id' => (int) $caso['id']]);
        }
        return count($quebradas);
    }

    // ------------------------------------------------------------------
    // Mensagens prontas
    // ------------------------------------------------------------------

    /** Modelos de WhatsApp da cobrança (Configurações), já com as variáveis trocadas */
    public static function mensagens(array $caso): array
    {
        $dias = $caso['vencimento_mais_antigo'] ? max(0, (int) ((strtotime('today') - strtotime($caso['vencimento_mais_antigo'])) / 86400)) : 0;
        $troca = [
            '{nome}' => $caso['nome_fantasia'] ?: $caso['razao_social'], '{valor}' => dinheiro((float) $caso['valor_total']),
            '{vencimento}' => $caso['vencimento_mais_antigo'] ? data_br($caso['vencimento_mais_antigo']) : '', '{dias}' => (string) $dias,
            '{link_pix}' => (string) Config::get('cobranca.link_pix', ''),
        ];
        $modelos = ['Lembrete gentil' => 'cobranca.msg_lembrete', 'Segunda via' => 'cobranca.msg_segunda_via', 'Proposta de acordo' => 'cobranca.msg_acordo'];
        $saida = [];
        foreach ($modelos as $titulo => $chave) {
            $texto = trim(preg_replace('/\s+/', ' ', strtr((string) Config::get($chave, ''), $troca)));
            if ($texto !== '') {
                $saida[$titulo] = $texto;
            }
        }
        return $saida;
    }
}
