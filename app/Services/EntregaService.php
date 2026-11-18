<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Endereço de entrega (V16.3): a loja pode ter UM endereço de entrega diferente do endereço do CNPJ.
 *  - O pedido escolhe onde entregar e guarda uma cópia do endereço (entrega_texto, cidade, UF).
 *  - Sem escolha (catálogo, app do externo), vai para o endereço de entrega quando a loja tem um.
 *  - Endereço escrito só na observação (comum no Nota10): o One NÃO adivinha. Marca a loja para revisar
 *    (entrega_revisar) e alguém confere e confirma na tela Carteira › Endereços de entrega.
 */
class EntregaService
{
    /** Frases que costumam anunciar um endereço de entrega na observação */
    private const PADRAO = '/(?:end(?:ere[cç]o|\.)?\s*(?:de\s*)?entrega|entregar\s+(?:em|na|no|para)|local\s+de\s+entrega|entrega\s*(?:em|:|-)|ent\.\s*:)/iu';

    /** A loja tem endereço de entrega cadastrado? */
    public static function tem(array $c): bool
    {
        return trim((string) ($c['entrega_endereco'] ?? '')) !== '' && trim((string) ($c['entrega_cidade'] ?? '')) !== '';
    }

    /** Endereço do cadastro em uma linha */
    public static function textoCadastro(array $c): string
    {
        return self::juntar([$c['endereco'] ?? null, $c['end_numero'] ?? null, $c['end_complemento'] ?? null, $c['bairro'] ?? null,
            trim(($c['cidade'] ?? '') . '/' . ($c['uf'] ?? ''), '/'), self::cep($c['cep'] ?? null)]);
    }

    /** Endereço de entrega em uma linha (com quem recebe, se houver) */
    public static function textoEntrega(array $c): string
    {
        $end = self::juntar([$c['entrega_endereco'] ?? null, $c['entrega_numero'] ?? null, $c['entrega_complemento'] ?? null, $c['entrega_bairro'] ?? null,
            trim(($c['entrega_cidade'] ?? '') . '/' . ($c['entrega_uf'] ?? ''), '/'), self::cep($c['entrega_cep'] ?? null)]);
        return trim(($c['entrega_razao'] ?? '') !== '' && $c['entrega_razao'] !== null ? $c['entrega_razao'] . ' · ' . $end : $end);
    }

    /**
     * Onde entregar este pedido: $escolha 'cadastro' ou 'entrega' (null = o padrão: entrega, se a loja tiver).
     * Devolve os campos que o pedido guarda.
     */
    public static function paraPedido(array $c, ?string $escolha = null): array
    {
        $outro = self::tem($c) && $escolha !== 'cadastro';
        return $outro
            ? ['entrega_outro' => 1, 'entrega_texto' => mb_substr(self::textoEntrega($c), 0, 400), 'entrega_cidade' => $c['entrega_cidade'], 'entrega_uf' => strtoupper((string) ($c['entrega_uf'] ?: $c['uf']))]
            : ['entrega_outro' => 0, 'entrega_texto' => mb_substr(self::textoCadastro($c), 0, 400), 'entrega_cidade' => $c['cidade'] ?? null, 'entrega_uf' => $c['uf'] ?? null];
    }

    /** Trecho da observação que parece endereço de entrega (ou null). Pega da frase-chave até o fim da linha/frase. */
    public static function trechoNaObservacao(?string $texto): ?string
    {
        $texto = (string) $texto;
        if ($texto === '' || !preg_match(self::PADRAO, $texto, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $resto = substr($texto, $m[0][1]);
        $linha = preg_split('/\R/u', $resto)[0];
        return mb_substr(trim($linha), 0, 300) ?: null;
    }

    /**
     * Procura nas observações das lojas que ainda não têm endereço de entrega e marca as que parecem ter um escrito ali.
     * Devolve quantas foram marcadas agora.
     */
    public static function marcarParaRevisar(): int
    {
        $n = 0;
        foreach (Banco::todos("SELECT id, observacoes FROM clientes WHERE excluido_em IS NULL AND entrega_revisar IS NULL
                AND COALESCE(entrega_endereco, '') = '' AND COALESCE(observacoes, '') <> ''") as $c) {
            if ($t = self::trechoNaObservacao($c['observacoes'])) {
                Banco::atualizar('clientes', (int) $c['id'], ['entrega_revisar' => $t]);
                $n++;
            }
        }
        return $n;
    }

    private static function juntar(array $partes): string
    {
        return implode(', ', array_filter(array_map(fn($p) => trim((string) $p), $partes), fn($p) => $p !== ''));
    }

    private static function cep(?string $cep): ?string
    {
        $d = preg_replace('/\D/', '', (string) $cep);
        return strlen($d) === 8 ? 'CEP ' . substr($d, 0, 5) . '-' . substr($d, 5) : null;
    }
}
