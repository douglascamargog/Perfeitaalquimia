-- V17.9 — Desenvolvedor do sistema: quem mantém o One (vê as telas de desenvolvimento) e pode "ver o One como" uma função.
ALTER TABLE usuarios ADD COLUMN desenvolvedor TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'mantém o sistema: Organograma, Atualização, Importar Nota10, Zerar dados';
ALTER TABLE usuarios ADD COLUMN ver_como_funcao_id INT NULL DEFAULT NULL COMMENT 'desenvolvedor vendo o One como esta função (vazio = modo desenvolvedor, vê tudo)';
