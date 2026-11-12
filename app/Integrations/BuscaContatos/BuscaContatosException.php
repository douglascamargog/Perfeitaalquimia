<?php
namespace App\Integrations\BuscaContatos;

/**
 * Erro da busca de contatos com mensagem que pode ir para a tela.
 * $situacao: 'erro' (falha técnica, sem chave, sem internet) ou 'recusada' (a IA recusou a busca).
 */
class BuscaContatosException extends \RuntimeException
{
    public function __construct(string $mensagem, public readonly string $situacao = 'erro', public readonly array $uso = [])
    {
        parent::__construct($mensagem);
    }
}
