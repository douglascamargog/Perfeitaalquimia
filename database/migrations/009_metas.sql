-- =====================================================================
-- 009 — Metas por vendedor e por função (Gestão › Metas)
--   A meta da EQUIPE continua em metas_equipe (Meu mês).
--   O "vendido" do Painel e das Metas vem dos pedidos FATURADOS (bate com o ERP).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE metas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mes           CHAR(7)       NOT NULL,             -- AAAA-MM
  tipo          VARCHAR(10)   NOT NULL,             -- vendedor | funcao
  alvo_id       INT UNSIGNED  NOT NULL,             -- usuarios.id ou funcoes.id
  valor         DECIMAL(14,2) NOT NULL DEFAULT 0,
  criado_por    INT UNSIGNED  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_meta (mes, tipo, alvo_id),
  KEY idx_meta_alvo (tipo, alvo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultas do painel por data de faturamento
CREATE INDEX idx_ped_faturado ON pedidos (situacao, faturado_em);
