<?php
namespace App\Integrations\OneIA;

/**
 * "Pergunte ao One": quem responde a pergunta.
 *  - OneIALocal: sem IA, responde com as aulas "Como usar o One", os números da pessoa e as campanhas (nada sai do servidor).
 *  - OneIAClaude: IA Claude (Anthropic) com as consultas de App\Services\OneFerramentas.
 */
interface OneIAInterface
{
    /** Nome curto do provedor (local | claude), gravado no registro de uso */
    public function nome(): string;

    /**
     * Responde. $historico = conversa anterior [['papel' => 'pessoa'|'one', 'texto' => ...]] (as últimas trocas).
     * Devolve ['texto' => resposta (markdown simples: **negrito**, [texto](/caminho), listas com "- "),
     *          'consultas' => [nomes das consultas feitas], 'tokens_entrada', 'tokens_saida', 'custo' (US$), 'situacao' => ok|erro|recusada]
     */
    public function responder(string $pergunta, array $historico, ?array $especialista = null): array;
}
