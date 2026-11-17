<?php
namespace App\Integrations\Tray;

/**
 * CONTRATO com a loja virtual (Tray).
 *
 * Duas versões com a MESMA interface:
 *   - TrayFake   → pedidos simulados (demonstração)
 *   - TrayClient → API real da Tray
 * Trocar é só mudar "ecommerce.provedor" (⚙️ Administração › Tray).
 *
 * Os pedidos voltam no formato da tabela ecommerce_pedidos (TrayTexto::pedido). LGPD: nada de nome, CPF,
 * e-mail, telefone ou endereço do comprador é guardado — só cidade, UF e um código anônimo.
 */
interface EcommerceInterface
{
    /**
     * Pedidos ALTERADOS entre $desde e $ate (AAAA-MM-DD), página N:
     * ['pedidos' => [linhas de TrayTexto::pedido], 'total' => n, 'por_pagina' => n].
     * A loja real devolve só o cabeçalho (detalhado = 0); a demonstração já devolve completo (com 'itens').
     */
    public function pedidosAlterados(string $desde, string $ate, int $pagina = 1): array;

    /** Pedido completo (itens, cidade/UF, nota, marketplace) ou null se não existe */
    public function pedidoCompleto(string $id): ?array;

    /** Status cadastrados na loja: [nome do status => tipo da Tray (open/closed...)] */
    public function statusDaLoja(): array;

    /**
     * Só a demonstração: pedidos do dia nos marketplaces para o Depósito (a loja real usa a cópia local dos pedidos).
     * ['codigo', 'numero_marketplace', 'canal', 'criado_em', 'nf', 'nf_em', 'cancelado', 'comprador', 'cidade', 'uf', 'total', 'itens']
     */
    public function pedidosParaSeparar(string $desde): array;

    /** Confere se as chaves funcionam: [ok (bool), mensagem] */
    public function testarConexao(): array;

    /** Nome curto para registro (fake | tray) */
    public function nome(): string;
}
