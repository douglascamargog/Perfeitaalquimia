-- =====================================================================
-- 029 — Desenvolvimento de Produto: cores do mockup 3D
--   cor_embalagem: branco | preto | ambar ('' = padrão do tipo: pote branco, frasco âmbar, caixinha branca)
--   cor_tampa: cor da tampa em hexadecimal (ex.: #14473a)
-- Só acrescenta.
-- =====================================================================
ALTER TABLE dp_projetos ADD COLUMN cor_embalagem VARCHAR(12) NOT NULL DEFAULT '';
ALTER TABLE dp_projetos ADD COLUMN cor_tampa VARCHAR(7) NOT NULL DEFAULT '#14473a';
