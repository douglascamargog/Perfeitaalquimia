-- V11.8 · Desconto dado pela vendedora no pedido (por produto e no pedido inteiro).
-- Só acrescenta (nada é apagado).
ALTER TABLE pedido_itens
  ADD COLUMN preco_tabela DECIMAL(12,2) NULL DEFAULT NULL,      -- preço da tabela antes do desconto do item
  ADD COLUMN desconto_pct DECIMAL(5,2) NOT NULL DEFAULT 0;      -- desconto do item (%)
ALTER TABLE pedidos
  ADD COLUMN desconto_pedido_pct DECIMAL(5,2) NOT NULL DEFAULT 0, -- desconto no pedido inteiro (%)
  ADD COLUMN desconto_vendedor DECIMAL(12,2) NOT NULL DEFAULT 0,  -- quanto a vendedora deu de desconto, em R$ (itens + pedido)
  ADD COLUMN desconto_acima_limite TINYINT(1) NOT NULL DEFAULT 0; -- passou do limite: precisa de aprovação
