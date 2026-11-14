-- =====================================================================
-- 027 — Desenvolvimento de Produto: tipo de embalagem do projeto (o "jogo")
--   pote | frasco | caixinha — define o modelo 3D que vai se formando a cada etapa.
--   Projetos que já existem ficam como pote. Só acrescenta.
-- =====================================================================
ALTER TABLE dp_projetos ADD COLUMN tipo VARCHAR(12) NOT NULL DEFAULT 'pote';
