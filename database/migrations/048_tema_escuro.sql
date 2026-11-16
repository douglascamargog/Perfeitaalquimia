-- =====================================================================
-- 048 — Modo escuro (V13.9)
--   usuarios.tema: claro | escuro | auto (segue o aparelho). Escolhido no botão sol/lua do topo
--   ou em Meu perfil › Aparência. Só acrescenta.
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN tema VARCHAR(10) NOT NULL DEFAULT 'claro';
