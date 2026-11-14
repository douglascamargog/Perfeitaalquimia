-- =====================================================================
-- 030 — Rótulos na Produção
--   materias_primas.rotulo = 1: a embalagem é o rótulo de um produto nosso e aparece em Produção › Rótulos
--   (junto com os rótulos dos clientes de terceirização). Só acrescenta.
-- =====================================================================
ALTER TABLE materias_primas ADD COLUMN rotulo TINYINT(1) NOT NULL DEFAULT 0;
