-- V17.4 — Cobrança integrada aos títulos a receber do cliente (o que aparece na ficha).
-- Os títulos ficam num lugar só (titulos_receber, vindos do Nota10 ou do ERP); a Cobrança abre/atualiza os casos a partir deles
-- e a baixa feita na Cobrança volta para o título.
ALTER TABLE titulos_receber
  ADD COLUMN omie_codigo VARCHAR(40) NULL DEFAULT NULL COMMENT 'Código do título no ERP (Omie)';
CREATE UNIQUE INDEX uq_titulos_omie ON titulos_receber (omie_codigo);

ALTER TABLE cobranca_titulos
  ADD COLUMN titulo_id INT NULL DEFAULT NULL COMMENT 'Título a receber (ficha do cliente) que este item da cobrança representa';
CREATE INDEX idx_cobranca_titulos_titulo ON cobranca_titulos (titulo_id);

-- liga os itens de cobrança já existentes aos títulos (os que vieram do Nota10 na V17.3)
UPDATE cobranca_titulos ct JOIN titulos_receber t ON ct.omie_codigo = CONCAT('n10:', t.nota10_guid) SET ct.titulo_id = t.id WHERE ct.titulo_id IS NULL;
