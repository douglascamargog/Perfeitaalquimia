-- =====================================================================
-- 043 — Campanhas comerciais (V12.0)
--   campanhas:           período (mês, semana ou datas livres), público e visual do catálogo
--   campanha_faixas:     "a partir de R$ X" → desconto %, brinde ou frete grátis
--   campanha_produtos:   preço promocional (% de desconto) em produtos escolhidos
--   pedidos/pedido_itens: o que a campanha deu em cada pedido
-- Só acrescenta (nada é apagado).
-- =====================================================================
CREATE TABLE campanhas (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome                VARCHAR(120) NOT NULL,                  -- nome interno ("Campanha de março")
  titulo              VARCHAR(120) NULL,                      -- título grande no catálogo ("Março verde")
  chamada             VARCHAR(255) NULL,                      -- frase abaixo do título
  selo                VARCHAR(30)  NULL,                      -- selo inclinado ("até 15% OFF")
  inicio              DATE NOT NULL,
  fim                 DATE NOT NULL,
  ativa               TINYINT(1) NOT NULL DEFAULT 1,          -- desligar sem apagar
  publico             VARCHAR(20) NOT NULL DEFAULT 'todos',   -- todos | tabela | regiao | uf | setor
  publico_valores     VARCHAR(500) NULL,                      -- ids (tabela, região, setor) ou UFs, separados por vírgula
  soma_desconto_vendedor TINYINT(1) NOT NULL DEFAULT 1,       -- 0: se a vendedora der desconto, o desconto da faixa não entra
  cor_primaria        CHAR(7) NULL,                           -- vazio = cor da empresa
  cor_destaque        CHAR(7) NULL,
  tema                VARCHAR(10) NOT NULL DEFAULT 'claro',   -- claro | escuro
  banner_midia_id     INT UNSIGNED NULL,                      -- imagem do banner (biblioteca de mídia)
  ordem               INT NOT NULL DEFAULT 0,
  criado_por          INT UNSIGNED NULL,
  criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em       DATETIME NULL,
  KEY idx_camp_periodo (ativa, inicio, fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campanha_faixas (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campanha_id         INT UNSIGNED NOT NULL,
  a_partir_de         DECIMAL(12,2) NOT NULL,                 -- valor do pedido
  tipo                VARCHAR(10) NOT NULL,                   -- desconto | brinde | frete
  desconto_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
  brinde              VARCHAR(120) NULL,
  brinde_midia_id     INT UNSIGNED NULL,
  KEY idx_cf_campanha (campanha_id),
  CONSTRAINT fk_cf_campanha FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campanha_produtos (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campanha_id         INT UNSIGNED NOT NULL,
  produto_id          INT UNSIGNED NOT NULL,
  desconto_pct        DECIMAL(5,2) NOT NULL,                  -- vale para todas as tabelas de preço
  destaque            TINYINT(1) NOT NULL DEFAULT 0,          -- aparece grande nos destaques do catálogo
  UNIQUE KEY uq_cp (campanha_id, produto_id),
  KEY idx_cp_produto (produto_id),
  CONSTRAINT fk_cp_campanha FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedido_itens
  ADD COLUMN promo_pct DECIMAL(5,2) NOT NULL DEFAULT 0,          -- preço promocional da campanha (%)
  ADD COLUMN campanha_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE pedidos
  ADD COLUMN campanha_desconto_pct DECIMAL(5,2) NOT NULL DEFAULT 0, -- desconto da faixa atingida
  ADD COLUMN campanha_desconto DECIMAL(12,2) NOT NULL DEFAULT 0,    -- em R$
  ADD COLUMN campanha_brindes VARCHAR(500) NULL DEFAULT NULL,       -- brindes conquistados
  ADD COLUMN frete_gratis TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN campanhas VARCHAR(255) NULL DEFAULT NULL;              -- nomes das campanhas aplicadas
