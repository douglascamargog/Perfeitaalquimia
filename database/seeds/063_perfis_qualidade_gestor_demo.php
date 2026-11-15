<?php
/** Perfis novos (V11.0) — DEMONSTRAÇÃO (só instalação nova): a Qualidade e a gestora de televendas de exemplo vão para os perfis certos. */
use App\Core\Banco;

Banco::executar("UPDATE usuarios SET perfil_id = (SELECT id FROM perfis WHERE slug = 'qualidade') WHERE email = 'qualidade@demo.local'");
Banco::executar("DELETE uf FROM usuario_funcoes uf JOIN usuarios u ON u.id = uf.usuario_id JOIN funcoes f ON f.id = uf.funcao_id
    WHERE u.email = 'qualidade@demo.local' AND f.slug = 'marketing'"); // ela é da Qualidade, não do Marketing
Banco::executar("UPDATE usuarios SET perfil_id = (SELECT id FROM perfis WHERE slug = 'gestor_setor') WHERE email = 'gestora.televendas@demo.local'");
return 'Qualidade e gestora de televendas nos perfis novos.';
