-- V16.3 — Endereço de entrega diferente do endereço do CNPJ.
-- O cadastro já tem os campos entrega_* (V16.2). Aqui: o aviso "revisar" (texto da observação que parece endereço de entrega)
-- e a cópia do endereço usado em cada pedido (o histórico não muda se o cadastro mudar depois).
ALTER TABLE clientes
  ADD COLUMN entrega_revisar VARCHAR(500) NULL DEFAULT NULL COMMENT 'Trecho da observação que parece endereço de entrega: alguém confere e confirma';

ALTER TABLE pedidos
  ADD COLUMN entrega_outro TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = entregar no endereço de entrega (diferente do cadastro)',
  ADD COLUMN entrega_texto VARCHAR(400) NULL DEFAULT NULL COMMENT 'Endereço de entrega usado neste pedido (cópia)',
  ADD COLUMN entrega_cidade VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN entrega_uf CHAR(2) NULL DEFAULT NULL COMMENT 'UF de entrega: define CFOP dentro/fora do estado';
