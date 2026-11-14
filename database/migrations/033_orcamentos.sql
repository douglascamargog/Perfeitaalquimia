-- =====================================================================
-- 033 — Orçamento por link e modelos de pedido
--   orcamentos: montado pela equipe para um cliente; o cliente abre um LINK SECRETO (token, sem senha,
--     com validade), muda as quantidades, aprova (vira pedido "aguardando aprovação") ou recusa.
--   orcamento_itens / orcamento_historico: o que foi oferecido e tudo que aconteceu.
--   pedido_modelos: listas prontas de produtos — "pessoal" (de um vendedor) ou "campanha" (da gestão, para todos
--     ou para um setor). Sem preço: o preço vem da tabela do cliente na hora.
-- Só acrescenta.
-- =====================================================================
CREATE TABLE orcamentos (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL,
  token              CHAR(64)      NOT NULL,
  cliente_id         INT UNSIGNED  NOT NULL,
  vendedor_id        INT UNSIGNED  NULL,
  tabela_preco_id    INT UNSIGNED  NULL,
  situacao           VARCHAR(12)   NOT NULL DEFAULT 'aberto', -- aberto | visto | aprovado | recusado | cancelado
  forma_pagamento_id INT UNSIGNED  NULL,
  condicao_id        INT UNSIGNED  NULL,
  subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0,
  observacoes        VARCHAR(1000) NULL,
  resposta_cliente   VARCHAR(1000) NULL,
  cliente_alterou    TINYINT(1)    NOT NULL DEFAULT 0,
  validade           DATE          NOT NULL,
  visto_em           DATETIME      NULL,
  respondido_em      DATETIME      NULL,
  pedido_id          INT UNSIGNED  NULL,
  criado_por         INT UNSIGNED  NULL,
  criado_em          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME      NULL,
  UNIQUE KEY uq_orc_uuid (uuid),
  UNIQUE KEY uq_orc_token (token),
  KEY idx_orc_cliente (cliente_id),
  KEY idx_orc_vendedor (vendedor_id, situacao),
  KEY idx_orc_situacao (situacao, validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orcamento_itens (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orcamento_id             INT UNSIGNED  NOT NULL,
  chave                    VARCHAR(12)   NOT NULL, -- id do produto, ou "t{id}" para produto com a marca do cliente
  produto_id               INT UNSIGNED  NULL,
  terceirizacao_produto_id INT UNSIGNED  NULL,
  codigo                   VARCHAR(40)   NULL,
  nome                     VARCHAR(200)  NOT NULL,
  quantidade               INT           NOT NULL,
  preco_unitario           DECIMAL(12,2) NOT NULL,
  total                    DECIMAL(12,2) NOT NULL,
  quantidade_cliente       INT           NULL, -- o que o cliente confirmou (se mudou)
  KEY idx_orci_orc (orcamento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orcamento_historico (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orcamento_id  INT UNSIGNED  NOT NULL,
  usuario_id    INT UNSIGNED  NULL,
  pelo_cliente  TINYINT(1)    NOT NULL DEFAULT 0,
  texto         VARCHAR(1000) NOT NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orch_orc (orcamento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_modelos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120)  NOT NULL,
  descricao     VARCHAR(300)  NULL,
  tipo          VARCHAR(10)   NOT NULL DEFAULT 'pessoal', -- pessoal | campanha
  dono_id       INT UNSIGNED  NULL,  -- pessoal: de quem é
  funcao_id     INT UNSIGNED  NULL,  -- campanha: só deste setor (vazio = todos)
  validade_ate  DATE          NULL,  -- campanha: some depois desta data
  ativo         TINYINT(1)    NOT NULL DEFAULT 1,
  criado_por    INT UNSIGNED  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pm_dono (tipo, dono_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pedido_modelo_itens (
  modelo_id   INT UNSIGNED NOT NULL,
  produto_id  INT UNSIGNED NOT NULL,
  quantidade  INT          NOT NULL,
  PRIMARY KEY (modelo_id, produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
