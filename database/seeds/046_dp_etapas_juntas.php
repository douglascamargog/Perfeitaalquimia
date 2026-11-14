<?php
/**
 * Desenvolvimento: Formulação, Rotulagem, Criação do rótulo e Compras passam a andar ao mesmo tempo (uma vez só;
 * depois o admin muda em Administração › Etapas do desenvolvimento). Os projetos em andamento abrem essas etapas.
 */
use App\Core\Banco;
use App\Services\DesenvolvimentoService as D;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'dp:etapas-juntas'")) {
    Banco::executar("UPDATE dp_etapas SET paralela = 1 WHERE checagem IN ('ficha', 'composicao', 'arte', 'compras')");
    foreach (Banco::todos("SELECT id FROM dp_projetos WHERE situacao IN ('andamento', 'pausado')") as $x) {
        D::sincronizar(D::buscar((int) $x['id']), null);
    }
    Banco::executar("INSERT IGNORE INTO migracoes (arquivo) VALUES ('dp:etapas-juntas')");
}
return null;
