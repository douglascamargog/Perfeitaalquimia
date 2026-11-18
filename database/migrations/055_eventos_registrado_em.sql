-- V15.7 — Linha do tempo: quando cada registro foi FEITO (a data do contato pode ser de outro dia).
-- Quem registrou pode corrigir ou apagar o próprio registro em até 24 horas. Os registros antigos ficam sem data (não mudam mais).
ALTER TABLE cliente_eventos ADD COLUMN registrado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
UPDATE cliente_eventos SET registrado_em = NULL;
ALTER TABLE cliente_eventos ADD COLUMN editado_em DATETIME NULL DEFAULT NULL;
