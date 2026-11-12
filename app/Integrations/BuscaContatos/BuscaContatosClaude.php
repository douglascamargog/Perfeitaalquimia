<?php
namespace App\Integrations\BuscaContatos;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\UserLocation;
use Anthropic\Messages\WebSearchTool20260209;

/**
 * Busca REAL de contatos na web com a IA Claude (Anthropic), pelo SDK oficial para PHP.
 *
 * Como funciona:
 *   1. Enviamos só dados públicos da empresa (CNPJ, nomes, cidade/UF).
 *   2. O Claude pesquisa na web (ferramenta web_search, até 5 buscas) em sites, Google Maps, Instagram,
 *      Facebook e listas comerciais.
 *   3. No fim ele chama a ferramenta "registrar_contatos" com a lista organizada (cada contato com a fonte).
 *
 * Custo (preço de tabela): US$ 4 por milhão de tokens de entrada, US$ 20 por milhão de saída
 * e US$ 10 por mil buscas na web. Cada busca grava o custo estimado em buscas_contato.
 *
 * Configuração: config.php › integracoes.anthropic.chave (e .modelo). A pasta vendor/ (SDK) vai no pacote zip.
 */
class BuscaContatosClaude implements BuscaContatosInterface
{
    private const PRECO_ENTRADA = 4.0 / 1_000_000;  // US$ por token
    private const PRECO_SAIDA = 20.0 / 1_000_000;
    private const PRECO_BUSCA = 10.0 / 1000;        // US$ por busca na web
    private const MAX_BUSCAS = 5;

    public function __construct(private string $chave, private string $modelo = 'claude-opus-5-5')
    {
    }

    public function nome(): string
    {
        return 'claude';
    }

    public function buscar(array $empresa): array
    {
        if ($this->chave === '') {
            throw new BuscaContatosException('A busca real ainda não foi ativada: falta a chave da IA no config.php (peça ao admin).');
        }
        $autoload = RAIZ . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new BuscaContatosException('A pasta vendor/ (biblioteca da IA) não está no servidor. Envie o pacote completo gerado pelo gerar_pacote.sh.');
        }
        require_once $autoload;

        $cliente = new Client(apiKey: $this->chave);
        $mensagens = [['role' => 'user', 'content' => $this->pedido($empresa)]];
        $ferramentas = [
            WebSearchTool20260209::with(maxUses: self::MAX_BUSCAS, userLocation: UserLocation::with(country: 'BR', region: $empresa['uf'] ?: null, city: $empresa['cidade'] ?: null, timezone: 'America/Sao_Paulo')),
            [
                'name'        => 'registrar_contatos',
                'description' => 'Registra os contatos encontrados para a empresa pesquisada. Chame UMA vez, no final, mesmo que não encontre nada (lista vazia).',
                'strict'      => true,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'empresa_encontrada' => ['type' => 'boolean', 'description' => 'true se encontrou a empresa certa (mesmo nome e cidade)'],
                        'resumo' => ['type' => 'string', 'description' => 'Uma ou duas frases em português: o que encontrou, se a empresa parece ativa, alertas'],
                        'contatos' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'tipo'       => ['type' => 'string', 'enum' => self::TIPOS],
                                    'valor'      => ['type' => 'string', 'description' => 'Telefone só com DDD e números; e-mail; @usuario; URL; endereço completo'],
                                    'fonte'      => ['type' => 'string', 'description' => 'URL exata da página onde o contato aparece'],
                                    'fonte_nome' => ['type' => 'string', 'description' => 'Nome curto da fonte, ex.: Instagram, Google Maps, site da empresa'],
                                    'confianca'  => ['type' => 'string', 'enum' => ['alta', 'media', 'baixa']],
                                    'observacao' => ['type' => 'string', 'description' => 'Ex.: número do botão de WhatsApp; pode ser do contador; perfil sem posts desde 2023'],
                                ],
                                'required' => ['tipo', 'valor', 'fonte', 'fonte_nome', 'confianca', 'observacao'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['empresa_encontrada', 'resumo', 'contatos'],
                    'additionalProperties' => false,
                ],
            ],
        ];

        $uso = ['buscas_web' => 0, 'tokens_entrada' => 0, 'tokens_saida' => 0, 'custo_estimado' => 0.0];
        try {
            // pause_turn = a busca na web ainda está em andamento: reenviamos a conversa para ele continuar (até 3 vezes)
            for ($volta = 0; $volta < 4; $volta++) {
                $resposta = $cliente->messages->create(
                    model: $this->modelo,
                    maxTokens: 8000,
                    system: $this->instrucoes(),
                    messages: $mensagens,
                    tools: $ferramentas,
                    requestOptions: ['timeout' => 120.0, 'maxRetries' => 1],
                );
                $this->somarUso($uso, $resposta->usage);

                if ($resposta->stopReason === 'refusal') {
                    throw new BuscaContatosException('A IA não fez esta busca. Tente pelos canais de sempre ou ajuste o cadastro.', 'recusada', $uso);
                }
                foreach ($resposta->content as $bloco) {
                    if ($bloco->type === 'tool_use' && $bloco->name === 'registrar_contatos') {
                        return $this->resultado($bloco->input, $uso);
                    }
                }
                if ($resposta->stopReason !== 'pause_turn') {
                    break;
                }
                $mensagens[] = ['role' => 'assistant', 'content' => $resposta->content];
            }
        } catch (AuthenticationException | PermissionDeniedException) {
            throw new BuscaContatosException('A chave da IA no config.php foi recusada. Peça ao admin para conferir.', 'erro', $uso);
        } catch (RateLimitException) {
            throw new BuscaContatosException('Muitas buscas ao mesmo tempo. Espere um minuto e tente de novo.', 'erro', $uso);
        } catch (AnthropicException $erro) {
            error_log('BuscaContatosClaude: ' . $erro->getMessage());
            throw new BuscaContatosException('Não foi possível buscar agora (sem conexão com a IA). Tente de novo em alguns minutos.', 'erro', $uso);
        }
        throw new BuscaContatosException('A IA terminou sem organizar os contatos. Tente de novo.', 'erro', $uso);
    }

    private function instrucoes(): string
    {
        return <<<TXT
        Você ajuda a equipe comercial e financeira de uma fabricante de suplementos a atualizar o cadastro de CLIENTES EMPRESAS (lojas, farmácias, distribuidores) no Brasil.
        Tarefa: encontrar na internet os canais de contato ATUAIS e PÚBLICOS da empresa informada: telefone, WhatsApp comercial, e-mail, Instagram, Facebook, site e endereço.

        Como pesquisar:
        - Comece pelo CNPJ e pelo nome fantasia + cidade. Procure site oficial, Google Maps/ficha da empresa, Instagram, Facebook, listas comerciais e marketplaces.
        - Confirme que é a MESMA empresa (nome e cidade batem). Se houver dúvida, use confiança "baixa" e explique na observação.
        - Prefira contatos recentes. Avise se a empresa parece fechada, mudou de nome ou de endereço.

        Regras:
        - Só contatos da EMPRESA publicados por ela ou em fontes públicas. Não procure dados pessoais de sócios, funcionários ou familiares (CPF, celular pessoal, endereço residencial).
        - Não invente: cada contato precisa da URL exata da fonte onde aparece.
        - Telefones só com números, com DDD, sem +55 (ex.: 51999998888).
        - No fim, chame a ferramenta registrar_contatos uma única vez (lista vazia se não encontrar nada). Escreva em português do Brasil.
        TXT;
    }

    private function pedido(array $e): string
    {
        $linhas = ['Empresa para pesquisar:'];
        foreach (['CNPJ' => $e['cnpj'] ? cnpj_formatado($e['cnpj']) : '', 'Razão social' => $e['razao_social'] ?? '', 'Nome fantasia' => $e['nome_fantasia'] ?? '',
                  'Cidade' => $e['cidade'] ?? '', 'UF' => $e['uf'] ?? ''] as $rotulo => $valor) {
            if (trim((string) $valor) !== '') {
                $linhas[] = "- $rotulo: $valor";
            }
        }
        return implode("\n", $linhas);
    }

    /** Soma tokens e buscas da resposta e recalcula o custo estimado */
    private function somarUso(array &$uso, $usage): void
    {
        $uso['tokens_entrada'] += (int) $usage->inputTokens + (int) ($usage->cacheCreationInputTokens ?? 0) + (int) ($usage->cacheReadInputTokens ?? 0);
        $uso['tokens_saida'] += (int) $usage->outputTokens;
        $uso['buscas_web'] += (int) ($usage->serverToolUse?->webSearchRequests ?? 0);
        $uso['custo_estimado'] = round($uso['tokens_entrada'] * self::PRECO_ENTRADA + $uso['tokens_saida'] * self::PRECO_SAIDA + $uso['buscas_web'] * self::PRECO_BUSCA, 4);
    }

    /** Confere e limpa o que a IA devolveu (nunca confiar cegamente) */
    private function resultado(array $entrada, array $uso): array
    {
        $contatos = [];
        foreach ((array) ($entrada['contatos'] ?? []) as $c) {
            $tipo = (string) ($c['tipo'] ?? '');
            $valor = trim((string) ($c['valor'] ?? ''));
            $fonte = trim((string) ($c['fonte'] ?? ''));
            if (!in_array($tipo, self::TIPOS, true) || $valor === '' || !preg_match('#^https?://#i', $fonte)) {
                continue; // sem fonte verificável não entra
            }
            $contatos[] = [
                'tipo' => $tipo, 'valor' => mb_substr($valor, 0, 255), 'fonte' => mb_substr($fonte, 0, 500),
                'fonte_nome' => mb_substr((string) ($c['fonte_nome'] ?? ''), 0, 80),
                'confianca' => in_array($c['confianca'] ?? '', ['alta', 'media', 'baixa'], true) ? $c['confianca'] : 'baixa',
                'observacao' => mb_substr((string) ($c['observacao'] ?? ''), 0, 255),
            ];
        }
        $resumo = mb_substr(trim((string) ($entrada['resumo'] ?? '')), 0, 500);
        if (empty($entrada['empresa_encontrada'])) {
            $resumo = 'A IA não teve certeza de ter achado a empresa certa. ' . $resumo;
        }
        return ['contatos' => array_slice($contatos, 0, 20), 'resumo' => $resumo, 'uso' => $uso];
    }
}
