<?php
namespace App\Integrations\Omie;

/**
 * CONTRATO com o ERP (Omie).
 *
 * Toda conversa do sistema com o ERP passa por estes métodos. Existem duas versões com a MESMA interface:
 *   - OmieFake   → dados simulados (protótipo)
 *   - OmieClient → API real do Omie (Fase 2)
 * Trocar uma pela outra é só mudar 'integracoes.erp' no config.php.
 *
 * Regras:
 *   - Os métodos "listar" recebem $alteradoDesde (sincronização incremental): só devolvem o que mudou depois dessa data.
 *   - Os dados voltam já num formato NOSSO (arrays com chaves em português), nunca no formato bruto do Omie.
 *     Assim o resto do sistema não precisa conhecer o Omie.
 *   - Todo registro traz 'omie_codigo' (o código no Omie) para evitar duplicidade.
 */
interface ErpInterface
{
    /** Produtos: [omie_codigo, sku, nome, unidade, ativo, alterado_em] */
    public function listarProdutos(?string $alteradoDesde = null): array;

    /** Preços: [omie_codigo_produto, tabela (lojista|distribuidor|terceirizacao), preco] */
    public function listarPrecos(?string $alteradoDesde = null): array;

    /** Estoque: [omie_codigo_produto, quantidade] */
    public function listarEstoque(): array;

    /** Dados fiscais de clientes: [omie_codigo, cnpj, razao_social, ie, endereco, cidade, uf, alterado_em] */
    public function listarClientes(?string $alteradoDesde = null): array;

    /** Títulos a receber: [omie_codigo, cnpj, valor, vencimento, pago_em, situacao (aberto|pago|atrasado)] */
    public function listarTitulos(?string $alteradoDesde = null): array;

    /** Notas faturadas: [omie_codigo, omie_codigo_pedido, cnpj, numero_nf, valor, emitido_em] */
    public function listarFaturamento(?string $alteradoDesde = null): array;

    /**
     * Envia um pedido como orçamento / etapa "a aprovar".
     * $pedido: [uuid, cnpj, itens => [[omie_codigo_produto, quantidade, preco_unitario]], forma_pagamento, condicao, observacoes]
     * Devolve: [omie_codigo, numero, etapa]
     */
    public function enviarPedido(array $pedido): array;

    /** Situação atual do pedido no ERP: [omie_codigo, etapa, faturado (bool), numero_nf] */
    public function consultarPedido(string $omieCodigo): array;

    /** Teste rápido de conexão (tela de Integração). Devolve [ok (bool), mensagem] */
    public function testarConexao(): array;
}
