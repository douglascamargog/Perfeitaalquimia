-- =====================================================================
-- 010 — Produção e matérias-primas
--   Matérias-primas (estoque, mínimo, fornecedor), ficha técnica de cada produto,
--   ordens de produção e estoque do produto acabado (com histórico de movimentos).
--   Toda mudança de estoque passa pelo ProducaoService (se um dia o Omie controlar a
--   produção, só esse Service muda).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE materias_primas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo          VARCHAR(40)   NULL,
  nome            VARCHAR(160)  NOT NULL,
  unidade         VARCHAR(10)   NOT NULL DEFAULT 'kg',     -- kg, g, L, ml, un (lista em Configurações)
  estoque         DECIMAL(14,3) NOT NULL DEFAULT 0,
  estoque_minimo  DECIMAL(14,3) NOT NULL DEFAULT 0,
  fornecedor      VARCHAR(160)  NULL,
  custo_unitario  DECIMAL(12,4) NOT NULL DEFAULT 0,        -- último custo de compra (R$ por unidade)
  observacoes     TEXT          NULL,
  ativo           TINYINT(1)    NOT NULL DEFAULT 1,
  omie_codigo     VARCHAR(40)   NULL,
  criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_mp_nome (nome),
  KEY idx_mp_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada entrada, consumo ou ajuste de matéria-prima (o estoque é a soma; saldo = estoque depois do movimento)
CREATE TABLE mp_movimentos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  materia_prima_id  INT UNSIGNED  NOT NULL,
  tipo              VARCHAR(10)   NOT NULL,                -- entrada | consumo | ajuste | estorno
  quantidade        DECIMAL(14,3) NOT NULL,                -- positiva = entra, negativa = sai
  saldo             DECIMAL(14,3) NOT NULL,
  custo_unitario    DECIMAL(12,4) NULL,                    -- só nas entradas de compra
  referencia        VARCHAR(80)   NULL,                    -- nota da compra ou "OP 12"
  ordem_id          INT UNSIGNED  NULL,
  observacao        VARCHAR(255)  NULL,
  usuario_id        INT UNSIGNED  NULL,
  criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mpm_mp (materia_prima_id, criado_em),
  KEY idx_mpm_tipo (tipo, criado_em),
  CONSTRAINT fk_mpm_mp FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ficha técnica: quanto de cada matéria-prima vai em 1 unidade do produto
CREATE TABLE ficha_tecnica (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id        INT UNSIGNED  NOT NULL,
  materia_prima_id  INT UNSIGNED  NOT NULL,
  quantidade        DECIMAL(14,5) NOT NULL,                -- na unidade da matéria-prima
  criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ficha (produto_id, materia_prima_id),
  KEY idx_ficha_mp (materia_prima_id),
  CONSTRAINT fk_ficha_produto FOREIGN KEY (produto_id) REFERENCES produtos(id),
  CONSTRAINT fk_ficha_mp      FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ordens_producao (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id     INT UNSIGNED  NOT NULL,
  quantidade     INT UNSIGNED  NOT NULL,
  situacao       VARCHAR(12)   NOT NULL DEFAULT 'planejada', -- planejada | concluida | cancelada
  origem         VARCHAR(15)   NOT NULL DEFAULT 'manual',    -- manual | reabastecimento
  lote           VARCHAR(40)   NULL,
  validade       DATE          NULL,
  previsto_para  DATE          NULL,
  observacoes    TEXT          NULL,
  custo_total    DECIMAL(14,2) NULL,                         -- custo das matérias-primas usadas (na conclusão)
  criado_por     INT UNSIGNED  NULL,
  concluida_por  INT UNSIGNED  NULL,
  concluida_em   DATETIME      NULL,
  cancelada_em   DATETIME      NULL,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_op_situacao (situacao, criado_em),
  KEY idx_op_produto (produto_id),
  KEY idx_op_concluida (concluida_em),
  CONSTRAINT fk_op_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estoque do produto acabado na fábrica (controle da Produção; o catálogo segue o estoque do ERP)
CREATE TABLE estoque_acabado (
  produto_id     INT UNSIGNED  NOT NULL PRIMARY KEY,
  quantidade     INT           NOT NULL DEFAULT 0,
  minimo         INT           NOT NULL DEFAULT 0,
  atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_acab_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE acabado_movimentos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id  INT UNSIGNED  NOT NULL,
  tipo        VARCHAR(10)   NOT NULL,                      -- producao | venda | ajuste
  quantidade  INT           NOT NULL,                      -- positiva = entra, negativa = sai
  saldo       INT           NOT NULL,
  referencia  VARCHAR(80)   NULL,                          -- "OP 12" ou "Pedido 345"
  ordem_id    INT UNSIGNED  NULL,
  pedido_id   INT UNSIGNED  NULL,
  observacao  VARCHAR(255)  NULL,
  usuario_id  INT UNSIGNED  NULL,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_acm_produto (produto_id, criado_em),
  KEY idx_acm_tipo (tipo, criado_em),
  KEY idx_acm_pedido (pedido_id),
  CONSTRAINT fk_acm_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
