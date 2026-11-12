-- =====================================================================
-- 008 — Catálogo B2B e fluxo de pedidos
--   Preços e estoque são CÓPIA do ERP (o Omie é a fonte da verdade).
--   O pedido nasce no catálogo, no sistema ou no app; vai ao ERP como orçamento; o Financeiro aprova.
-- Só acrescenta tabelas (regra do sistema em uso: nada é apagado).
-- =====================================================================

-- Preço de cada produto em cada tabela (Lojista, Distribuidor, Terceirização), copiado do ERP
CREATE TABLE produto_precos (
  produto_id      INT UNSIGNED  NOT NULL,
  tabela_preco_id INT UNSIGNED  NOT NULL,
  preco           DECIMAL(12,2) NOT NULL,
  atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (produto_id, tabela_preco_id),
  CONSTRAINT fk_preco_produto FOREIGN KEY (produto_id)      REFERENCES produtos(id) ON DELETE CASCADE,
  CONSTRAINT fk_preco_tabela  FOREIGN KEY (tabela_preco_id) REFERENCES tabelas_preco(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estoque copiado do ERP (uso interno: o catálogo NÃO mostra estoque)
CREATE TABLE produto_estoque (
  produto_id    INT UNSIGNED NOT NULL PRIMARY KEY,
  quantidade    INT          NOT NULL DEFAULT 0,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_estoque_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acesso do cliente ao catálogo (login pelo CNPJ do cadastro)
CREATE TABLE cliente_acessos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id       INT UNSIGNED NOT NULL UNIQUE,
  senha_hash       VARCHAR(255) NULL,
  codigo_hash      VARCHAR(255) NULL,          -- código temporário (primeiro acesso / esqueci a senha)
  codigo_expira_em DATETIME     NULL,
  tentativas       INT          NOT NULL DEFAULT 0,
  bloqueado_ate    DATETIME     NULL,
  ultimo_acesso_em DATETIME     NULL,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acesso_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos
CREATE TABLE pedidos (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid               CHAR(36)      NOT NULL UNIQUE,  -- gerado no aparelho/navegador: o mesmo pedido nunca entra duas vezes
  cliente_id         INT UNSIGNED  NOT NULL,
  vendedor_id        INT UNSIGNED  NULL,
  origem             VARCHAR(10)   NOT NULL DEFAULT 'sistema', -- catalogo | sistema | app
  tabela_preco_id    INT UNSIGNED  NOT NULL,
  forma_pagamento_id INT UNSIGNED  NOT NULL,
  condicao_id        INT UNSIGNED  NULL,
  subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0,
  desconto           DECIMAL(12,2) NOT NULL DEFAULT 0,
  total              DECIMAL(12,2) NOT NULL DEFAULT 0,
  situacao           VARCHAR(25)   NOT NULL DEFAULT 'aguardando_aprovacao', -- aguardando_aprovacao | aprovado | faturado | recusado | cancelado
  omie_codigo        VARCHAR(40)   NULL,
  omie_numero        VARCHAR(40)   NULL,
  numero_nf          VARCHAR(40)   NULL,
  faturado_em        DATETIME      NULL,
  aprovado_por       INT UNSIGNED  NULL,
  aprovado_em        DATETIME      NULL,
  motivo_recusa      VARCHAR(500)  NULL,
  observacoes        TEXT          NULL,
  criado_por_usuario INT UNSIGNED  NULL,          -- pedido feito pela equipe
  criado_por_cliente TINYINT(1)    NOT NULL DEFAULT 0, -- pedido feito pelo próprio cliente no catálogo
  criado_em          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ped_cliente (cliente_id, criado_em),
  KEY idx_ped_vendedor (vendedor_id, criado_em),
  KEY idx_ped_situacao (situacao, criado_em),
  KEY idx_ped_data (criado_em),
  CONSTRAINT fk_ped_cliente  FOREIGN KEY (cliente_id)         REFERENCES clientes(id),
  CONSTRAINT fk_ped_vendedor FOREIGN KEY (vendedor_id)        REFERENCES usuarios(id),
  CONSTRAINT fk_ped_tabela   FOREIGN KEY (tabela_preco_id)    REFERENCES tabelas_preco(id),
  CONSTRAINT fk_ped_forma    FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id),
  CONSTRAINT fk_ped_condicao FOREIGN KEY (condicao_id)        REFERENCES condicoes_pagamento(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens: nome e preço copiados no momento do pedido (mudar o preço depois não altera pedido antigo)
CREATE TABLE pedido_itens (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT UNSIGNED  NOT NULL,
  produto_id     INT UNSIGNED  NULL,
  nome           VARCHAR(200)  NOT NULL,
  quantidade     INT           NOT NULL,
  preco_unitario DECIMAL(12,2) NOT NULL,
  total          DECIMAL(12,2) NOT NULL,
  KEY idx_item_pedido (pedido_id),
  KEY idx_item_produto (produto_id),
  CONSTRAINT fk_item_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linha do tempo do pedido
CREATE TABLE pedido_historico (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id  INT UNSIGNED NOT NULL,
  situacao   VARCHAR(25)  NOT NULL,
  texto      VARCHAR(500) NOT NULL,
  usuario_id INT UNSIGNED NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_pedido (pedido_id, criado_em),
  CONSTRAINT fk_hist_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
