-- =====================================================================
-- 012 — Ficha do funcionário (foto, aniversário...) e código do produto
--   usuarios: dados de contato e aniversário da equipe (sem CPF, salário ou documentos: RH fica fora do sistema)
--   produtos.codigo: código que identifica o produto (vem do Omie na Fase 2 ou é cadastrado na tela Produtos)
--   pedido_itens.codigo: cópia do código no momento do pedido (o histórico não muda se o código mudar)
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE usuarios
  ADD COLUMN foto            VARCHAR(255) NULL AFTER telefone,
  ADD COLUMN whatsapp        VARCHAR(20)  NULL AFTER foto,
  ADD COLUMN apelido         VARCHAR(60)  NULL AFTER whatsapp,
  ADD COLUMN data_nascimento DATE         NULL AFTER apelido,
  ADD COLUMN data_admissao   DATE         NULL AFTER data_nascimento,
  ADD COLUMN observacoes     TEXT         NULL AFTER data_admissao;

CREATE INDEX idx_usuario_aniversario ON usuarios (data_nascimento);

ALTER TABLE produtos ADD COLUMN codigo VARCHAR(40) NULL AFTER slug;
CREATE UNIQUE INDEX uk_produto_codigo ON produtos (codigo);

ALTER TABLE pedido_itens ADD COLUMN codigo VARCHAR(40) NULL AFTER produto_id;
