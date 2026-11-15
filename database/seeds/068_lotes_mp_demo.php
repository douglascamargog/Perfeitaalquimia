<?php
/** Lotes das matérias-primas (V11.5) — DEMONSTRAÇÃO (só instalação nova): fabricante, CAS/INS e 1 ou 2 lotes para o estoque de cada matéria-prima. */
use App\Core\Banco;

$fabricantes = ['Valdequímica', 'Indlabs', 'Konkreta', 'Capsugel', 'Galena', 'Pharmanostra', 'Fagron'];
$n = 0;
foreach (Banco::todos("SELECT id, nome, estoque FROM materias_primas WHERE ativo = 1 AND tipo = 'materia_prima' ORDER BY id") as $i => $m) {
    $fab = $fabricantes[$i % count($fabricantes)];
    $cas = stripos($m['nome'], 'silício') !== false || stripos($m['nome'], 'silicio') !== false ? 'INS 551 · CAS 7631-86-9'
        : (stripos($m['nome'], 'carnitina') !== false ? 'CAS 36687-82-8' : (stripos($m['nome'], 'magnésio') !== false ? 'CAS 7786-30-3' : null));
    Banco::executar('UPDATE materias_primas SET fabricante = COALESCE(fabricante, ?), cas_ins = COALESCE(cas_ins, ?) WHERE id = ?', [$fab, $cas, $m['id']]);
    $estoque = max(0, (float) $m['estoque']);
    if ($estoque <= 0) {
        continue;
    }
    $partes = $i % 3 === 0 ? [round($estoque * 0.35, 3), round($estoque - round($estoque * 0.35, 3), 3)] : [$estoque];
    foreach ($partes as $k => $q) {
        $fabData = date('Y-m-d', strtotime('-' . (120 + $i * 7 + $k * 60) . ' days'));
        Banco::executar('INSERT IGNORE INTO mp_lotes (materia_prima_id, lote, fabricante, fabricacao, validade, quantidade, saldo) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$m['id'], strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $fab), 0, 3)) . date('Ymd', strtotime($fabData)) . chr(65 + $k), $fab, $fabData,
             date('Y-m-d', strtotime($fabData . ' +' . (24 + ($i % 3) * 12) . ' months')), $q, $q]);
        $n++;
    }
}
return "$n lote(s) de matéria-prima de exemplo.";
