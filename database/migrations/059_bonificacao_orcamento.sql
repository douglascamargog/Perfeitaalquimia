-- V16.6 — Bonificação também no orçamento: o vendedor oferece unidades bonificadas, o cliente vê no link
-- (não pode mudar) e, ao aprovar, elas vão para o pedido com as mesmas regras (mesma nota, CFOP de bonificação, a loja não paga).
ALTER TABLE orcamento_itens
  ADD COLUMN bonificado TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN valor_bonificado DECIMAL(12,2) NOT NULL DEFAULT 0;

ALTER TABLE orcamentos
  ADD COLUMN bonificado_valor DECIMAL(12,2) NOT NULL DEFAULT 0,
  ADD COLUMN bonificacao_motivo VARCHAR(60) NULL DEFAULT NULL;
