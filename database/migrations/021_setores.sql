-- =====================================================================
-- 021 — Setores de venda
--   funcoes.setor_venda: a função é um SETOR DE VENDA (carteira própria e um grupo no menu lateral:
--                        Televendas, Externo, Terceirização, Prospecção e Reativação). O admin muda em Funções.
--   funcoes.icone: ícone do setor no menu.
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE funcoes ADD COLUMN setor_venda TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE funcoes ADD COLUMN icone VARCHAR(30) NULL;
