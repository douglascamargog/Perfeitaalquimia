-- =====================================================================
-- 028 — Desenvolvimento de Produto: etapas que andam "ao mesmo tempo"
--   paralela = 1: a etapa fica aberta desde o início do projeto (depois do briefing), pode ser editada a qualquer
--   momento e não segura as outras (ex.: Formulação, Rotulagem, Criação do rótulo, Compras).
--   As dependências reais ficam nas checagens (gráfica espera a arte; piloto espera ficha e compras; lançamento espera tudo).
-- Só acrescenta.
-- =====================================================================
ALTER TABLE dp_etapas ADD COLUMN paralela TINYINT(1) NOT NULL DEFAULT 0;
