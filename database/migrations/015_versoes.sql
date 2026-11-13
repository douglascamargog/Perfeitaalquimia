-- =====================================================================
-- 015 — Novidades da versão
--   versao_vista: última versão do One cuja janela de novidades a pessoa já viu (ex.: "2.0").
--   Vazio = ainda não viu nenhuma (vê a da versão atual no próximo acesso).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN versao_vista VARCHAR(20) NULL DEFAULT NULL;
