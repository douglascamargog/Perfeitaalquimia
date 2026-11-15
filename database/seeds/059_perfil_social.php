<?php
/**
 * Perfil das pessoas (V11.0) — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Configurações › Pessoas: tipos de elogio e quantos dias o status do dia fica no ar.
 */
use App\Core\Banco;

$configs = [
    ['pessoas.tipos_elogio', '🙌 Ajudou muito,🚀 Arrasou na venda,🦸 Salvou o dia,🤝 Ótimo trabalho em equipe,💡 Ideia brilhante,😄 Deixa o dia mais leve', 'lista', 'Pessoas',
        'Tipos de elogio entre colegas', 'Separados por vírgula. Comece cada um com um emoji. Aparecem no botão "Elogiar" do perfil.', 10],
    ['pessoas.status_horas', '24', 'numero', 'Pessoas', 'Status do dia: some depois de (horas)', 'O status ("☕ focada em ligações hoje") some sozinho depois desse tempo. 0 = só some quando a pessoa apagar.', 20],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
