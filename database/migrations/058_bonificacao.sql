-- V16.4 — Produto bonificado no pedido, na mesma nota fiscal da venda, com CFOP próprio por item.
-- O item bonificado vai na nota com o preço da tabela (exigência fiscal), mas não entra no total que a loja paga,
-- no faturamento nem na meta (o total da linha fica 0 e o valor fica em valor_bonificado).
ALTER TABLE pedido_itens
  ADD COLUMN bonificado TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN valor_bonificado DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Quantidade × preço da tabela (vai na nota; a loja não paga)';

ALTER TABLE pedidos
  ADD COLUMN bonificado_valor DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Soma dos itens bonificados (preço de tabela)',
  ADD COLUMN bonificacao_motivo VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN bonificacao_acima_limite TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = passou do % permitido: precisa de aprovação';
CREATE INDEX idx_pedidos_bonificado ON pedidos (bonificado_valor);
