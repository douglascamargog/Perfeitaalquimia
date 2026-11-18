<?php
/**
 * V17.0 — DADOS ESSENCIAIS (idempotente): CNPJ da empresa principal (Perfeita Alquimia).
 * Pedidos de outras empresas do grupo (vindos do Nota10) ficam guardados, mas fora dos painéis, metas e análises.
 */
use App\Core\Banco;

Banco::executar('INSERT IGNORE INTO configuracoes (chave, rotulo, valor, tipo, grupo, descricao) VALUES (?, ?, ?, ?, ?, ?)', [
    'empresa.cnpj_principal', 'CNPJ da empresa principal', '15652740000180', 'texto', 'Empresa',
    'Pedidos de outras empresas do grupo (importados do Nota10) ficam guardados como "Outra empresa do grupo" e não entram nos painéis',
]);
return null;
