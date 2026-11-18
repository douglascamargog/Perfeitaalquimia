<?php
/**
 * V17.8 — DADOS ESSENCIAIS: área de cada função no organograma (só onde ainda está vazia; o admin muda em Funções).
 * Setores de venda vão para o Comercial; as demais pelo identificador padrão. Função nova sem área aparece em "Outras".
 */
use App\Core\Banco;

$n = Banco::executar("UPDATE funcoes SET area = 'comercial' WHERE area IS NULL AND setor_venda = 1");
$padrao = [
    'diretoria' => ['diretoria'],
    'marketing' => ['marketing', 'social_media', 'gestor_marketing', 'ecommerce'],
    'financeiro' => ['cobranca', 'financeiro'],
    'operacao' => ['producao', 'tecnica', 'qualidade', 'deposito', 'compras'],
];
foreach ($padrao as $area => $slugs) {
    $n += Banco::executar('UPDATE funcoes SET area = ? WHERE area IS NULL AND slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')', array_merge([$area], $slugs));
}
return $n ? "$n função(ões) colocadas numa área do organograma" : null;
