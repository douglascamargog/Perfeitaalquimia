-- =====================================================================
-- 032 — Desperdício medido na produção
--   ficha_tecnica.perda_pct: perda média medida deste item NESTE produto (média das últimas medições).
--   producao_perdas: cada medição (ao concluir a ordem, a Produção informa quanto usou de verdade).
--   A prévia da ordem soma a perda ao consumo e mostra quantas unidades dá para produzir.
-- Só acrescenta.
-- =====================================================================
ALTER TABLE ficha_tecnica ADD COLUMN perda_pct DECIMAL(7,2) NULL DEFAULT NULL;

CREATE TABLE producao_perdas (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ordem_id         INT UNSIGNED  NOT NULL,
  produto_id       INT UNSIGNED  NOT NULL,
  materia_prima_id INT UNSIGNED  NOT NULL,
  unidades         INT           NOT NULL,
  teorico          DECIMAL(14,3) NOT NULL,
  usado            DECIMAL(14,3) NOT NULL,
  perda_pct        DECIMAL(7,2)  NOT NULL,
  usuario_id       INT UNSIGNED  NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_perda_mp (materia_prima_id),
  KEY idx_perda_produto (produto_id, materia_prima_id),
  KEY idx_perda_ordem (ordem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
