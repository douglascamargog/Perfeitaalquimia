<?php
/**
 * Ligue Talk pela tela (V12.7) — ESSENCIAL (idempotente). Só atualiza o TEXTO DE AJUDA de duas configurações:
 * o company_id/token do Ligue Talk e a chave da IA agora são cadastrados em ⚙️ Administração (não mais no config.php).
 * Não mexe em nenhum valor escolhido pelo admin.
 */
use App\Core\Banco;

$textos = [
    'telefonia.modo' => 'manual = registrar com 1 clique · simulado = demonstração (ligações e gravações de exemplo) · api = Ligue Talk real (código da empresa e token em ⚙️ Administração › Ligue Talk)',
    'one.ia_ativa'   => 'Ligado: entende qualquer pergunta e consulta o sistema (custa por uso; chave da Anthropic em ⚙️ Administração). Desligado: responde dúvidas do sistema, sem IA.',
];
foreach ($textos as $chave => $descricao) {
    Banco::executar('UPDATE configuracoes SET descricao = ? WHERE chave = ? AND descricao <> ?', [$descricao, $chave, $descricao]);
}
return null;
