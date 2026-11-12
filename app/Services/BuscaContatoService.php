<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\BuscaContatos\BuscaContatosException;
use App\Integrations\BuscaContatos\ReceitaCnpj;
use App\Integrations\Integracoes;
use App\Models\ClienteEvento;

/**
 * "Buscar contatos com IA": regras de uso e gravação.
 *
 * - Nada é gravado no cadastro sozinho: a busca devolve SUGESTÕES; a pessoa marca o que usar.
 * - O valor antigo fica na linha do tempo e a mudança vai para a auditoria.
 * - Limite de buscas por dia (busca_ia.limite_diario) e intervalo mínimo para a mesma empresa
 *   (busca_ia.dias_entre_buscas): antes disso, mostra a última busca.
 * - Primeiro consulta a Receita (grátis), depois a IA.
 */
class BuscaContatoService
{
    /** tipo de contato => [campo do cliente, rótulo, tamanho máximo] */
    public const CAMPOS = [
        'telefone'  => ['telefone', 'Telefone', 20],
        'whatsapp'  => ['whatsapp', 'WhatsApp', 20],
        'email'     => ['email', 'E-mail', 160],
        'instagram' => ['instagram', 'Instagram', 120],
        'facebook'  => ['facebook', 'Facebook', 255],
        'site'      => ['site', 'Site', 255],
        'endereco'  => [null, 'Endereço', 255],   // endereço não substitui o cadastro: vai para a linha do tempo
    ];

    public static function ativa(): bool
    {
        return (bool) Config::get('busca_ia.ativa', true);
    }

    /** Buscas feitas hoje (empresa toda) */
    public static function buscasHoje(): int
    {
        return (int) Banco::valor("SELECT COUNT(*) FROM buscas_contato WHERE criado_em >= CURDATE() AND situacao <> 'erro'");
    }

    /** Última busca bem-sucedida do cliente dentro do intervalo configurado (ou null) */
    public static function ultima(int $clienteId, bool $qualquerData = false): ?array
    {
        $dias = max(0, (int) Config::get('busca_ia.dias_entre_buscas', 7));
        $linha = Banco::um(
            "SELECT b.*, u.nome AS usuario_nome FROM buscas_contato b LEFT JOIN usuarios u ON u.id = b.usuario_id
              WHERE b.cliente_id = ? AND b.situacao = 'ok'" . ($qualquerData ? '' : ' AND b.criado_em >= DATE_SUB(NOW(), INTERVAL ? DAY)') . '
              ORDER BY b.id DESC LIMIT 1',
            $qualquerData ? [$clienteId] : [$clienteId, $dias]
        );
        return $linha ? self::montar($linha, Banco::um('SELECT * FROM clientes WHERE id = ?', [$clienteId])) : null;
    }

    /**
     * Faz a busca (ou devolve a recente). Devolve o resultado pronto para a tela.
     * @throws BuscaContatosException com mensagem amigável
     */
    public static function buscar(array $cliente, int $usuarioId): array
    {
        if (!self::ativa()) {
            throw new BuscaContatosException('A busca de contatos está desligada. Peça ao admin para ativar em Configurações.');
        }
        if ($recente = self::ultima((int) $cliente['id'])) {
            return $recente + ['repetida' => true];
        }
        $limite = (int) Config::get('busca_ia.limite_diario', 30);
        if ($limite > 0 && self::buscasHoje() >= $limite) {
            throw new BuscaContatosException("O limite de $limite buscas por dia foi atingido. Amanhã libera de novo (o admin pode mudar em Configurações).");
        }

        $empresa = array_intersect_key($cliente, array_flip(['cnpj', 'razao_social', 'nome_fantasia', 'cidade', 'uf'])); // só dados públicos
        $provedor = Integracoes::buscaContatos();
        $contatos = [];
        $resumo = [];

        // 1. Receita Federal (gratuito)
        if ($empresa['cnpj'] && Config::get('busca_ia.consultar_receita', true)) {
            $receita = (new ReceitaCnpj())->consultar($empresa['cnpj']);
            if ($receita) {
                $contatos = $receita['contatos'];
                if ($receita['situacao'] !== '') {
                    $resumo[] = 'Situação na Receita: ' . $receita['situacao'] . '.';
                }
            }
        }
        // 2. IA na web
        try {
            $r = $provedor->buscar($empresa);
        } catch (BuscaContatosException $erro) {
            Banco::inserir('buscas_contato', ['cliente_id' => $cliente['id'], 'usuario_id' => $usuarioId, 'provedor' => $provedor->nome(),
                'situacao' => $erro->situacao, 'mensagem' => mb_substr($erro->getMessage(), 0, 255)] + self::colunasUso($erro->uso));
            throw $erro;
        }
        $contatos = self::semRepetidos([...$contatos, ...$r['contatos']]);
        if ($r['resumo'] !== '') {
            $resumo[] = $r['resumo'];
        }

        $id = Banco::inserir('buscas_contato', [
            'cliente_id' => $cliente['id'], 'usuario_id' => $usuarioId, 'provedor' => $provedor->nome(), 'situacao' => 'ok',
            'resultado_json' => json_encode(['contatos' => $contatos, 'resumo' => implode(' ', $resumo)], JSON_UNESCAPED_UNICODE),
        ] + self::colunasUso($r['uso']));
        return self::montar(Banco::um('SELECT b.*, NULL AS usuario_nome FROM buscas_contato b WHERE id = ?', [$id]), $cliente) + ['repetida' => false];
    }

    /**
     * Grava no cadastro os contatos que a pessoa marcou ($indices = posições na lista da busca).
     * Devolve a mensagem para a tela.
     */
    public static function aplicar(array $cliente, int $buscaId, array $indices, int $usuarioId): string
    {
        $busca = Banco::um("SELECT * FROM buscas_contato WHERE id = ? AND cliente_id = ? AND situacao = 'ok'", [$buscaId, $cliente['id']]);
        if (!$busca) {
            throw new BuscaContatosException('Busca não encontrada. Faça a busca de novo.');
        }
        $lista = json_decode((string) $busca['resultado_json'], true)['contatos'] ?? [];
        $novos = [];
        $linhas = [];
        foreach (array_unique(array_map('intval', $indices)) as $i) {
            $c = $lista[$i] ?? null;
            if (!$c || !isset(self::CAMPOS[$c['tipo']])) {
                continue;
            }
            [$campo, $rotulo, $max] = self::CAMPOS[$c['tipo']];
            $valor = self::normalizar($c['tipo'], $c['valor']);
            $fonte = $c['fonte_nome'] ?: parse_url($c['fonte'], PHP_URL_HOST);
            if ($campo === null) {
                $linhas[] = "$rotulo encontrado: {$c['valor']} (fonte: $fonte)";
                continue;
            }
            if ($valor === '' || isset($novos[$campo]) || $valor === (string) $cliente[$campo]) {
                continue; // vazio, já escolhido outro do mesmo tipo, ou igual ao atual
            }
            $novos[$campo] = mb_substr($valor, 0, $max);
            $antes = (string) $cliente[$campo] !== '' ? $cliente[$campo] : 'vazio';
            $linhas[] = "$rotulo: $antes → $valor (fonte: $fonte)";
        }
        if (!$linhas) {
            return 'Nada mudou: os contatos marcados já estão no cadastro.';
        }

        Banco::transacao(function () use ($cliente, $novos, $linhas, $usuarioId, $busca) {
            if ($novos) {
                Banco::atualizar('clientes', (int) $cliente['id'], $novos);
                Auditoria::registrar('cliente', (int) $cliente['id'], 'contatos_ia', array_intersect_key($cliente, $novos), $novos);
            }
            ClienteEvento::registrar((int) $cliente['id'], 'busca_contato', implode("\n", $linhas), $usuarioId,
                $busca['provedor'] === 'fake' ? 'Busca (demonstração)' : 'Busca com IA', ['busca_id' => (int) $busca['id']]);

            // Caso de cobrança "sem contato" ganhou contato novo: volta para tentativa, com as tentativas zeradas
            $caso = Banco::um("SELECT cb.id FROM cobrancas cb JOIN cobranca_etapas e ON e.id = cb.etapa_id
                WHERE cb.cliente_id = ? AND cb.situacao = 'aberta' AND e.tipo = 'sem_contato'", [$cliente['id']]);
            if ($caso && $novos) {
                Banco::atualizar('cobrancas', (int) $caso['id'], ['etapa_id' => (int) CobrancaService::etapaTentativa()['id'], 'tentativas_sem_sucesso' => 0]);
                ClienteEvento::registrar((int) $cliente['id'], 'cobranca', 'Contato novo encontrado: caso voltou para tentativa de contato', $usuarioId, null, ['cobranca_id' => (int) $caso['id']]);
            }
        });
        return count($novos) ? count($novos) . ' contato(s) atualizado(s) no cadastro. O valor antigo ficou na linha do tempo.' : 'Registrado na linha do tempo.';
    }

    /** Custo das buscas no mês (para o admin) */
    public static function custoDoMes(): array
    {
        return Banco::um("SELECT COUNT(*) AS buscas, COALESCE(SUM(custo_estimado), 0) AS custo, COALESCE(SUM(buscas_web), 0) AS buscas_web
            FROM buscas_contato WHERE criado_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND provedor <> 'fake'");
    }

    // ------------------------------------------------------------------

    /** Prepara para a tela: marca o que é novo em relação ao cadastro atual */
    private static function montar(array $linha, array $cliente): array
    {
        $dados = json_decode((string) $linha['resultado_json'], true) ?: [];
        $contatos = [];
        foreach ($dados['contatos'] ?? [] as $i => $c) {
            $campo = self::CAMPOS[$c['tipo']][0] ?? null;
            $atual = $campo ? (string) ($cliente[$campo] ?? '') : '';
            $c['indice'] = $i;
            $c['rotulo'] = self::CAMPOS[$c['tipo']][1] ?? $c['tipo'];
            $c['atual'] = $atual;
            $c['igual'] = $campo !== null && $atual !== '' && self::normalizar($c['tipo'], $c['valor']) === $atual;
            $c['fonte'] = preg_match('#^https?://#i', (string) $c['fonte']) ? $c['fonte'] : '';
            $contatos[] = $c;
        }
        return [
            'id' => (int) $linha['id'], 'provedor' => $linha['provedor'], 'demonstracao' => $linha['provedor'] === 'fake',
            'criado_em' => data_br($linha['criado_em'], true), 'usuario' => $linha['usuario_nome'] ?? null,
            'resumo' => (string) ($dados['resumo'] ?? ''), 'contatos' => $contatos,
        ];
    }

    private static function normalizar(string $tipo, string $valor): string
    {
        $valor = trim($valor);
        return match ($tipo) {
            'telefone', 'whatsapp' => (string) Normalizar::telefone($valor),
            'email'                => mb_strtolower($valor),
            'instagram'            => str_starts_with($valor, '@') || str_contains($valor, '/') ? $valor : '@' . $valor,
            default                => $valor,
        };
    }

    /** Tira contatos repetidos (mesmo tipo e mesmo valor normalizado) */
    private static function semRepetidos(array $contatos): array
    {
        $vistos = [];
        $saida = [];
        foreach ($contatos as $c) {
            $chave = $c['tipo'] . '|' . self::normalizar($c['tipo'], $c['valor']);
            if (!isset($vistos[$chave])) {
                $vistos[$chave] = true;
                $saida[] = $c;
            }
        }
        return $saida;
    }

    private static function colunasUso(array $uso): array
    {
        return ['buscas_web' => (int) ($uso['buscas_web'] ?? 0), 'tokens_entrada' => (int) ($uso['tokens_entrada'] ?? 0),
            'tokens_saida' => (int) ($uso['tokens_saida'] ?? 0), 'custo_estimado' => (float) ($uso['custo_estimado'] ?? 0)];
    }
}
