<?php
/**
 * DEMONSTRAÇÃO da ficha do funcionário: aniversários e admissões fictícios da equipe de exemplo
 * (um aniversário hoje e outros no mês, para o card "Aniversariantes do mês" aparecer).
 */
use App\Core\Banco;

mt_srand(2424);
$usuarios = Banco::todos('SELECT id FROM usuarios ORDER BY id');
$n = 0;
foreach ($usuarios as $i => ['id' => $id]) {
    $ano = mt_rand(1975, 2001);
    if ($i === 1) {
        $nascimento = '1992' . date('-m-d');                                           // aniversário hoje (1992 é bissexto: vale até em 29/02)
    } elseif ($i % 3 === 0) {
        $nascimento = $ano . date('-m-') . str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT); // neste mês
    } else {
        $nascimento = $ano . '-' . str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT);
    }
    $n += Banco::executar('UPDATE usuarios SET data_nascimento = ?, data_admissao = ? WHERE id = ? AND data_nascimento IS NULL',
        [$nascimento, date('Y-m-d', strtotime('-' . mt_rand(60, 2500) . ' days')), $id]);
}
return "Equipe (demonstração): $n aniversários e admissões fictícios.";
