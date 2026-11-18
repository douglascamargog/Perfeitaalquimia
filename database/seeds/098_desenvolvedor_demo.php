<?php
/**
 * DEMONSTRAÇÃO (só instalação nova/testes): o admin de demonstração é o desenvolvedor do sistema,
 * para os testes automáticos abrirem Atualização, Importar Nota10, Zerar dados e Organograma.
 * Em produção quem mantém o One se marca em Administração › Desenvolvedor do sistema.
 */
use App\Core\Banco;

$n = Banco::executar("UPDATE usuarios SET desenvolvedor = 1 WHERE email = 'admin@demo.local'");
return $n ? 'Admin de demonstração marcado como desenvolvedor do sistema' : null;
