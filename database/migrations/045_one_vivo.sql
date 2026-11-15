-- =====================================================================
-- 045 — One mais presente (V12.3)
--   usuarios.one_balao: o One pode puxar conversa sozinho (1) ou só fala quando a pessoa clica (0). Meu perfil › One.
-- Só acrescenta (nada é apagado).
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN one_balao TINYINT(1) NOT NULL DEFAULT 1;
