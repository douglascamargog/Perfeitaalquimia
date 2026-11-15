-- =====================================================================
-- 044 — Índices de desempenho (V12.1, teste de carga com 30 pessoas e 20 mil clientes)
--   cliente_eventos: contatos por pessoa e período (Acompanhamento, Meu mês, Equipe)
--   clientes: números de cada carteira (Carteiras) e clientes por responsável e última compra (Reativação, Recompra)
-- Só acrescenta (nada é apagado).
-- =====================================================================
CREATE INDEX idx_ev_usuario_data ON cliente_eventos (usuario_id, ocorrido_em, tipo);
CREATE INDEX idx_cli_carteira_compra ON clientes (carteira_id, excluido_em, tipo, ultima_compra_em);
CREATE INDEX idx_cli_resp_compra ON clientes (responsavel_id, ultima_compra_em);
