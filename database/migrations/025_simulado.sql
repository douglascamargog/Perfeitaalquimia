-- =====================================================================
-- 025 — Dados simulados para teste (Administração › Dados de teste)
--   simulado = 1 marca o que foi GERADO pelo botão "Gerar movimento simulado" (para as pessoas reais).
--   O botão "Apagar tudo o que foi simulado" remove só esses registros; o que a equipe fez de verdade fica.
-- Só acrescenta colunas (regra do sistema em uso).
-- =====================================================================
ALTER TABLE clientes        ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE cliente_eventos ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tarefas         ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pedidos         ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE cobrancas       ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE ligacoes        ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE ordens_producao ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE mkt_posts       ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE metas           ADD COLUMN simulado TINYINT(1) NOT NULL DEFAULT 0;
