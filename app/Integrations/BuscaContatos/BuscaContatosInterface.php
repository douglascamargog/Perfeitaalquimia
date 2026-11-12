<?php
namespace App\Integrations\BuscaContatos;

/**
 * CONTRATO da busca de contatos de uma empresa na internet.
 *
 * Duas versões com a MESMA interface:
 *   - BuscaContatosFake   → demonstração (sem custo, sem internet)
 *   - BuscaContatosClaude → busca real na web com a IA Claude (Anthropic)
 * Trocar é só mudar "busca_ia.provedor" em Configurações.
 *
 * LGPD: recebe só dados PÚBLICOS da empresa (CNPJ, razão social, nome fantasia, cidade, UF).
 * Nunca enviar contatos internos, histórico ou observações da equipe.
 */
interface BuscaContatosInterface
{
    /** Tipos de contato que a busca pode devolver */
    public const TIPOS = ['telefone', 'whatsapp', 'email', 'instagram', 'facebook', 'site', 'endereco'];

    /**
     * @param array $empresa [cnpj, razao_social, nome_fantasia, cidade, uf]
     * @return array [
     *   'contatos' => [[tipo, valor, fonte (URL), fonte_nome, confianca (alta|media|baixa), observacao], ...],
     *   'resumo'   => texto curto (ex.: "Empresa ativa; site e Instagram encontrados"),
     *   'uso'      => [buscas_web, tokens_entrada, tokens_saida, custo_estimado (US$)],
     * ]
     * @throws BuscaContatosException quando não for possível buscar (mensagem amigável)
     */
    public function buscar(array $empresa): array;

    /** Nome curto para registro (fake | claude) */
    public function nome(): string;
}
