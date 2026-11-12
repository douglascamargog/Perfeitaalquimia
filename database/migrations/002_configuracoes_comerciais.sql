-- =====================================================================
-- 002 — Regras comerciais configuráveis (nada fixo no código):
--       segmentos, origens, etapas do funil, regiões e pedido mínimo,
--       tabelas de preço, formas e condições de pagamento.
-- =====================================================================

-- Segmentos padronizados (Produtos naturais, Suplementos, Farmácia, Outros)
CREATE TABLE segmentos (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL UNIQUE,
  ativo TINYINT(1)  NOT NULL DEFAULT 1,
  ordem INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De onde veio o cliente/lead (indicação, site, anúncio, importação...)
CREATE TABLE origens (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL UNIQUE,
  ativo TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas do funil, na ordem. tipo: aberta (em andamento), ganho, perdido, inativo
CREATE TABLE funil_etapas (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL,
  ordem INT         NOT NULL DEFAULT 0,
  cor   VARCHAR(7)  NOT NULL DEFAULT '#94a3b8',
  tipo  VARCHAR(20) NOT NULL DEFAULT 'aberta',
  ativa TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regiões de venda com o pedido mínimo de cada uma
CREATE TABLE regioes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(80)   NOT NULL UNIQUE,
  pedido_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
  observacao    VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quais UFs pertencem a cada região. pedido_minimo aqui (opcional) substitui o da região
CREATE TABLE regiao_ufs (
  uf            CHAR(2)       NOT NULL PRIMARY KEY,
  regiao_id     INT UNSIGNED  NOT NULL,
  pedido_minimo DECIMAL(12,2) NULL,
  CONSTRAINT fk_ru_regiao FOREIGN KEY (regiao_id) REFERENCES regioes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelas de preço: Lojista, Distribuidor, Terceirização
CREATE TABLE tabelas_preco (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(40)  NOT NULL UNIQUE,
  nome        VARCHAR(80)  NOT NULL,
  omie_codigo VARCHAR(40)  NULL,              -- código da tabela no Omie
  ativa       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formas de pagamento (cartão, Pix, boleto)
CREATE TABLE formas_pagamento (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug                VARCHAR(40) NOT NULL UNIQUE,
  nome                VARCHAR(80) NOT NULL,
  bloqueia_com_atraso TINYINT(1)  NOT NULL DEFAULT 0, -- 1 = cliente com título em atraso não pode usar
  ativa               TINYINT(1)  NOT NULL DEFAULT 1,
  ordem               INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Condições de cada forma (parcelas, desconto, prazo). Valores definitivos ainda pendentes.
CREATE TABLE condicoes_pagamento (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  forma_pagamento_id INT UNSIGNED  NOT NULL,
  nome               VARCHAR(80)   NOT NULL,           -- ex.: 'Boleto 28 dias'
  parcelas           INT           NOT NULL DEFAULT 1,
  prazo_dias         VARCHAR(60)   NULL,               -- ex.: '28' ou '28,56'
  desconto_pct       DECIMAL(5,2)  NOT NULL DEFAULT 0,
  valor_minimo       DECIMAL(12,2) NOT NULL DEFAULT 0, -- pedido mínimo para liberar esta condição
  ativa              TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_cp_forma FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
