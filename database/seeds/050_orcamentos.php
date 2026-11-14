<?php
/**
 * Orçamento por link e modelos de pedido — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - configurações: validade do link e a mensagem do WhatsApp que acompanha o link.
 *  Quem pode fazer orçamento: quem cria pedidos (pedidos.criar). Modelos de campanha: gestão
 *  (pedidos.ver_todos ou gestor(a) do setor). Nenhuma permissão nova.
 */
use App\Core\Banco;

$configs = [
    ['orcamento.validade_dias', '15', 'numero', 'Pedidos', 'Validade do orçamento (dias)', 'Depois disso o link do orçamento para de funcionar e o cliente precisa pedir outro.', 60],
    ['orcamento.mensagem', 'Olá, {nome}! Segue o seu orçamento da Perfeita Alquimia: {link} — é só abrir, conferir e aprovar (vale até {validade}). Qualquer dúvida, fale comigo. {vendedor}', 'texto', 'Pedidos', 'Mensagem do orçamento no WhatsApp',
        'Variáveis: {nome} (cliente), {link}, {validade}, {total}, {vendedor}.', 61],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
