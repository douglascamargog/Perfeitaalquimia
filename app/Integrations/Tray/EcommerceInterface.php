<?php
namespace App\Integrations\Tray;

/**
 * CONTRATO com a loja virtual (Tray).
 *
 * Duas versões com a MESMA interface:
 *   - TrayFake   → números simulados (protótipo)
 *   - TrayClient → API real da Tray
 * Trocar é só mudar "ecommerce.provedor" em Configurações (e pôr as chaves no config.php).
 *
 * Os dados voltam num formato NOSSO (chaves em português). Nunca devolver nome, e-mail ou
 * documento do comprador (LGPD): o painel só precisa de números, cidade e UF.
 */
interface EcommerceInterface
{
    /** Números por dia: [[data, pedidos, faturamento, visitas|null, novos_clientes (= clientes diferentes que compraram)], ...] */
    public function resumoPorDia(string $desde, string $ate): array;

    /** Mais vendidos do mês (AAAA-MM): [[produto_ref, nome, quantidade, faturamento], ...] */
    public function produtosMaisVendidos(string $mes, int $limite = 10): array;

    /** Pedidos recentes, sem dados pessoais: [[numero, data, valor, situacao, cidade, uf], ...] */
    public function pedidosRecentes(int $limite = 10): array;

    /** Confere se as chaves funcionam: [ok (bool), mensagem] */
    public function testarConexao(): array;

    /** Nome curto para registro (fake | tray) */
    public function nome(): string;
}
