-- V11.4 · Pedidos do e-commerce (Tray: Mercado Livre, Shopee, Amazon, loja própria) na separação do Depósito.
-- Entram no One quando são pagos e vão para a fila de separação só quando a nota fiscal é emitida.
-- Só acrescenta (nada é apagado).
ALTER TABLE pedidos
  ADD COLUMN tipo_venda VARCHAR(15) NULL DEFAULT NULL,         -- NULL = venda da equipe/catálogo (lojista); 'ecommerce' = loja virtual
  ADD COLUMN canal VARCHAR(20) NULL DEFAULT NULL,              -- mercado_livre | shopee | amazon | loja ...
  ADD COLUMN tray_codigo VARCHAR(40) NULL DEFAULT NULL,        -- código do pedido na Tray (não importa duas vezes)
  ADD COLUMN marketplace_numero VARCHAR(60) NULL DEFAULT NULL, -- número do pedido no marketplace
  ADD COLUMN comprador_nome VARCHAR(120) NULL DEFAULT NULL,    -- só o necessário para conferir a etiqueta (visto só no Depósito)
  ADD COLUMN comprador_cidade VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN comprador_uf CHAR(2) NULL DEFAULT NULL,
  ADD COLUMN expedicao_limite DATETIME NULL DEFAULT NULL;      -- horário limite para sair (por canal)

CREATE UNIQUE INDEX uq_pedidos_tray ON pedidos (tray_codigo);
CREATE INDEX idx_pedidos_tipo_venda ON pedidos (tipo_venda, expedicao);
