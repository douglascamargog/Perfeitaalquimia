<?php
namespace App\Integrations\OneIA;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\OneFerramentas;

/**
 * "Pergunte ao One" com a IA Claude (Anthropic), pelo SDK oficial para PHP (a mesma chave da busca de contatos:
 * config.php › integracoes.anthropic.chave).
 *
 * Como funciona: a pergunta vai para o Claude com a lista de consultas que ele pode pedir (App\Services\OneFerramentas).
 * Ele pede as consultas que precisa; o sistema roda cada uma AQUI, com o login da pessoa (só o que ela pode ver),
 * devolve o resultado, e ele escreve a resposta. No máximo 6 rodadas de consultas por pergunta.
 *
 * Custo (preço de tabela do Claude Opus 5.5): US$ 4 por milhão de tokens de entrada e US$ 20 por milhão de saída;
 * a parte repetida de cada chamada vem do cache (US$ 0,20 por milhão).
 * Esforço "low": respostas rápidas e mais baratas para um bate-papo. Cada pergunta grava o custo estimado em one_perguntas.
 */
class OneIAClaude implements OneIAInterface
{
    private const PRECO_ENTRADA = 4.0 / 1_000_000;
    private const PRECO_SAIDA = 20.0 / 1_000_000;
    private const PRECO_CACHE_GRAVAR = 5.0 / 1_000_000; // guardar no cache: 1,25× a entrada
    private const PRECO_CACHE_LER = 0.20 / 1_000_000;   // ler do cache: 5% da entrada
    private const MAX_RODADAS = 6;

    public function __construct(private string $chave, private string $modelo = 'claude-opus-5-5', private ?string $endereco = null)
    {
    }

    public function nome(): string
    {
        return 'claude';
    }

    public function responder(string $pergunta, array $historico): array
    {
        $uso = ['tokens_entrada' => 0, 'tokens_saida' => 0, 'custo' => 0.0, 'consultas' => []];
        $autoload = RAIZ . '/vendor/autoload.php';
        if ($this->chave === '' || !is_file($autoload)) {
            return ['texto' => 'A IA ainda não está pronta neste servidor (falta a chave em Administração ou a pasta vendor/). Peça ao admin.', 'situacao' => 'erro'] + $uso;
        }
        require_once $autoload;
        $cliente = new Client(apiKey: $this->chave, baseUrl: $this->endereco ?: null); // endereço só muda em testes

        // Conversa: as últimas trocas (só texto) + a pergunta nova
        $mensagens = [];
        foreach ($historico as $h) {
            $mensagens[] = ['role' => $h['papel'] === 'pessoa' ? 'user' : 'assistant', 'content' => (string) $h['texto']];
        }
        $mensagens[] = ['role' => 'user', 'content' => $pergunta];
        $ferramentas = OneFerramentas::definicoes();

        try {
            for ($rodada = 0; $rodada <= self::MAX_RODADAS; $rodada++) {
                $resposta = $cliente->beta->messages->create(
                    model: $this->modelo,
                    maxTokens: 4000,
                    system: $this->instrucoes(),
                    messages: $mensagens,
                    tools: $ferramentas,
                    outputConfig: ['effort' => 'low'],
                    // cache: instruções + consultas + conversa ficam guardadas na Anthropic por alguns minutos (bem mais barato na próxima chamada)
                    cacheControl: ['type' => 'ephemeral'],
                    // se o modelo recusar por política, o servidor tenta o modelo reserva padrão
                    fallbacks: 'default',
                    betas: ['server-side-fallback-2026-07-01'],
                    requestOptions: ['timeout' => 60.0, 'maxRetries' => 1],
                );
                $normal = (int) $resposta->usage->inputTokens;
                $gravado = (int) ($resposta->usage->cacheCreationInputTokens ?? 0);
                $lido = (int) ($resposta->usage->cacheReadInputTokens ?? 0);
                $uso['tokens_entrada'] += $normal + $gravado + $lido;
                $uso['tokens_saida'] += (int) $resposta->usage->outputTokens;
                $custoEntrada = ($uso['custo_entrada'] ?? 0) + $normal * self::PRECO_ENTRADA + $gravado * self::PRECO_CACHE_GRAVAR + $lido * self::PRECO_CACHE_LER;
                $uso['custo_entrada'] = $custoEntrada;
                $uso['custo'] = round($custoEntrada + $uso['tokens_saida'] * self::PRECO_SAIDA, 4);

                if ($resposta->stopReason === 'refusal') {
                    return ['texto' => 'Essa eu não consigo responder. Tente perguntar de outro jeito.', 'situacao' => 'recusada'] + $uso;
                }
                if ($resposta->stopReason !== 'tool_use' || $rodada === self::MAX_RODADAS) {
                    $texto = '';
                    foreach ($resposta->content as $bloco) {
                        if ($bloco->type === 'text') {
                            $texto .= $bloco->text;
                        }
                    }
                    if ($resposta->stopReason === 'max_tokens') {
                        $texto .= "\n\n(resposta cortada: pergunte de forma mais específica)";
                    }
                    return ['texto' => trim($texto) ?: 'Não consegui montar uma resposta agora. Tente de novo.', 'situacao' => 'ok'] + $uso;
                }
                // Rodar as consultas que ele pediu (todas as desta rodada voltam juntas)
                $resultados = [];
                foreach ($resposta->content as $bloco) {
                    if ($bloco->type !== 'tool_use') {
                        continue;
                    }
                    $entrada = is_array($bloco->input) ? $bloco->input : (array) json_decode(json_encode($bloco->input), true);
                    $uso['consultas'][] = $bloco->name;
                    try {
                        $dados = OneFerramentas::executar((string) $bloco->name, $entrada);
                        $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloco->id, 'content' => json_encode($dados, JSON_UNESCAPED_UNICODE)];
                    } catch (\Throwable $e) {
                        error_log('OneIAClaude consulta ' . $bloco->name . ': ' . $e->getMessage());
                        $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloco->id, 'content' => 'Erro ao consultar.', 'isError' => true];
                    }
                }
                $mensagens[] = ['role' => 'assistant', 'content' => $resposta->content];
                $mensagens[] = ['role' => 'user', 'content' => $resultados];
            }
        } catch (AuthenticationException | PermissionDeniedException) {
            return ['texto' => 'A chave da IA foi recusada. Peça ao admin para conferir em Administração › Chave da IA.', 'situacao' => 'erro'] + $uso;
        } catch (RateLimitException) {
            return ['texto' => 'Muita gente perguntando ao mesmo tempo. Espere um minutinho e tente de novo.', 'situacao' => 'erro'] + $uso;
        } catch (AnthropicException $erro) {
            error_log('OneIAClaude: ' . $erro->getMessage());
            return ['texto' => 'Não consegui falar com a IA agora. Tente de novo em alguns minutos.', 'situacao' => 'erro'] + $uso;
        }
        return ['texto' => 'Não consegui montar uma resposta agora. Tente de novo.', 'situacao' => 'erro'] + $uso;
    }

    /**
     * Teste rápido da ligação com a IA (Administração › "Testar a IA agora"): uma pergunta curtíssima, sem consultas.
     * Devolve [deu_certo, mensagem em português para o admin].
     */
    public function testar(): array
    {
        if ($this->chave === '') {
            return [false, 'Falta a chave: cole a chave da Anthropic aqui em Administração, no campo "Chave da IA".'];
        }
        if (!is_file(RAIZ . '/vendor/autoload.php')) {
            return [false, 'A pasta vendor/ (biblioteca da IA) não está no servidor. Envie o pacote completo.'];
        }
        require_once RAIZ . '/vendor/autoload.php';
        try {
            $t = microtime(true);
            $r = (new Client(apiKey: $this->chave, baseUrl: $this->endereco ?: null))->messages->create(
                model: $this->modelo, maxTokens: 200, outputConfig: ['effort' => 'low'],
                messages: [['role' => 'user', 'content' => 'Responda apenas: OK']],
                requestOptions: ['timeout' => 30.0, 'maxRetries' => 0],
            );
            return [true, 'Funcionando! A IA (' . $this->modelo . ') respondeu em ' . number_format(microtime(true) - $t, 1, ',', '') . ' s. Agora ligue "Pergunte ao One com IA" em Configurações › One (IA).'];
        } catch (AuthenticationException) {
            return [false, 'A Anthropic recusou a chave. Confira se ela foi copiada inteira (começa com sk-ant-) e se não foi apagada no console.anthropic.com.'];
        } catch (PermissionDeniedException) {
            return [false, 'A chave não tem permissão para este modelo. Confira a conta em console.anthropic.com.'];
        } catch (\Anthropic\Core\Exceptions\BadRequestException $e) {
            return [false, str_contains(mb_strtolower($e->getMessage()), 'credit') ? 'A conta da Anthropic está sem créditos: coloque créditos em console.anthropic.com › Billing.' : 'A Anthropic recusou o pedido: ' . mb_strimwidth($e->getMessage(), 0, 160, '…')];
        } catch (\Anthropic\Core\Exceptions\NotFoundException) {
            return [false, 'Modelo não encontrado: confira integracoes › anthropic › modelo no config.php (o padrão é claude-opus-5-5).'];
        } catch (RateLimitException) {
            return [false, 'Limite de uso atingido na Anthropic agora. Espere um minuto e teste de novo.'];
        } catch (\Anthropic\Core\Exceptions\APIConnectionException) {
            return [false, 'O servidor não conseguiu falar com api.anthropic.com. Peça à hospedagem para liberar conexões HTTPS de saída para esse endereço.'];
        } catch (AnthropicException $e) {
            error_log('OneIAClaude teste: ' . $e->getMessage());
            return [false, 'Não deu certo: ' . mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    private function instrucoes(): string
    {
        $empresa = config('empresa_nome', 'Perfeita Alquimia');
        $pessoa = \App\Services\EquipeService::comoChamar(\App\Core\Auth::usuario());
        $clientes = OneFerramentas::podeClientes()
            ? 'Você pode consultar os clientes da carteira da pessoa (buscar_clientes, cliente_resumo).'
            : 'Você NÃO tem acesso a dados de clientes (desligado pelo administrador): se perguntarem de clientes, explique isso e indique a tela certa do sistema.';
        return <<<TXT
        Você é o One, o ajudante do sistema One da $empresa (fabricante de suplementos e produtos naturais que vende para lojas por CNPJ).
        Você está conversando com $pessoa, dentro do sistema. Responda sempre em português do Brasil, curto e simpático (2 a 6 frases ou uma lista curta).
        Use as consultas disponíveis para responder com dados reais; nunca invente números, clientes, produtos ou telas.
        Se a resposta estiver numa tela do sistema, mande o link no formato [Nome da tela](/caminho) usando os caminhos que as consultas devolvem.
        Para dúvidas de "como faço" no sistema, use ajuda_sistema. $clientes
        Valores em reais no formato R$ 1.234,56 e datas dd/mm/aaaa. Não mostre ids técnicos.
        Você não altera nada no sistema: só consulta e orienta. Se pedirem para fazer algo (enviar pedido, mudar cadastro), explique onde fazer.
        Assuntos fora do trabalho da empresa: responda com gentileza que você só ajuda com o sistema e as vendas.
        TXT;
    }
}
