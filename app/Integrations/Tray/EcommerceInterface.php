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

    /**
     * Pedidos PAGOS para o Depósito (alterados desde $desde, AAAA-MM-DD HH:MM:SS), um por linha:
     * ['codigo' (id na Tray), 'numero_marketplace', 'canal' (mercado_livre|shopee|amazon|loja), 'criado_em',
     *  'nf' (número da nota ou null = ainda sem nota), 'nf_em', 'cancelado' (bool),
     *  'comprador' (nome curto, só para conferir a etiqueta), 'cidade', 'uf', 'total',
     *  'itens' => [['ref' (código do produto ou EAN), 'nome', 'quantidade', 'preco'], ...]]
     * Só o necessário para separar e despachar (LGPD): sem CPF, e-mail, telefone ou endereço completo.
     */
    public function pedidosParaSeparar(string $desde): array;

    /** Confere se as chaves funcionam: [ok (bool), mensagem] */
    public function testarConexao(): array;

    /** Nome curto para registro (fake | tray) */
    public function nome(): string;
}
