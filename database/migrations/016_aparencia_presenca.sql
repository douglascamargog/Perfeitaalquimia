-- =====================================================================
-- 016 — Aparência (cores escolhidas por cada pessoa) e "quem está online"
--   cor_primaria / cor_secundaria: #rrggbb; vazio = cores padrão da empresa (Configurações › Aparência)
--   visto_em / visto_onde: último sinal da pessoa no sistema e o NOME DA ABA onde estava (ex.: "Carteira").
--     Nunca guarda cliente, pedido ou endereço da página (LGPD).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN cor_primaria VARCHAR(7) NULL DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN cor_secundaria VARCHAR(7) NULL DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN visto_em DATETIME NULL DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN visto_onde VARCHAR(60) NULL DEFAULT NULL;
CREATE INDEX idx_usuarios_visto ON usuarios (visto_em);
