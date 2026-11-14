<?php
/**
 * Rótulos na Produção: marca como "rótulo" as embalagens que já existem com nome "Rótulo ..." (uma vez só;
 * depois a Produção marca/desmarca no cadastro da matéria-prima).
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'producao:rotulos'")) {
    Banco::executar("UPDATE materias_primas SET rotulo = 1 WHERE tipo = 'embalagem' AND nome LIKE 'Rótulo%'");
    Banco::executar("INSERT IGNORE INTO migracoes (arquivo) VALUES ('producao:rotulos')");
}
return null;
