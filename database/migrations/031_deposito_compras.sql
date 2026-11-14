-- =====================================================================
-- 031 — Depósito (separação e expedição) e Orçamentos/compras da Qualidade
--   pedidos.expedicao: andamento no depósito (a nota já veio do ERP): aguardando | separando | separado | despachado.
--     NULL = fora da fila (pedidos antigos e não faturados).
--   pedido_item_lotes: lote e validade de cada item separado (rastreabilidade: para quais clientes foi cada lote).
--   compras / compra_cotacoes / compra_historico: orçamentos da Qualidade com aprovação por quem ela escolher.
-- Só acrescenta.
-- =====================================================================
ALTER TABLE pedidos ADD COLUMN expedicao VARCHAR(12) NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN expedicao_prioridade TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pedidos ADD COLUMN expedicao_aguarda_produto TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pedidos ADD COLUMN expedicao_desde DATETIME NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN separado_por INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN separado_em DATETIME NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN despachado_por INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN despachado_em DATETIME NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN transportadora VARCHAR(80) NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN volumes INT NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN peso_kg DECIMAL(10,3) NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN rastreio VARCHAR(80) NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN expedicao_obs VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN falta TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_ped_expedicao ON pedidos (expedicao, expedicao_desde);

ALTER TABLE pedido_itens ADD COLUMN qtd_separada INT NULL DEFAULT NULL;

CREATE TABLE pedido_item_lotes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT UNSIGNED NOT NULL,
  pedido_item_id INT UNSIGNED NOT NULL,
  produto_id     INT UNSIGNED NULL,
  lote           VARCHAR(40)  NOT NULL,
  validade       DATE         NULL,
  quantidade     INT          NOT NULL,
  criado_por     INT UNSIGNED NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lote (lote),
  KEY idx_lote_produto (produto_id, lote),
  KEY idx_lote_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo             VARCHAR(15)   NOT NULL DEFAULT 'avulso',   -- materia_prima | rotulo | embalagem | avulso
  titulo           VARCHAR(160)  NOT NULL,
  materia_prima_id INT UNSIGNED  NULL,
  dp_projeto_id    INT UNSIGNED  NULL,
  quantidade       DECIMAL(14,3) NOT NULL DEFAULT 1,
  unidade          VARCHAR(10)   NOT NULL DEFAULT 'un',
  motivo           TEXT          NULL,
  necessario_ate   DATE          NULL,
  situacao         VARCHAR(12)   NOT NULL DEFAULT 'rascunho', -- rascunho | aprovacao | refazer | aprovado | recusado | comprado | recebido | cancelado
  aprovador_id     INT UNSIGNED  NULL,
  enviado_em       DATETIME      NULL,
  decidido_em      DATETIME      NULL,
  decisao_comentario VARCHAR(500) NULL,
  cotacao_id       INT UNSIGNED  NULL,
  escolha_motivo   VARCHAR(300)  NULL,
  comprado_em      DATETIME      NULL,
  previsto_em      DATE          NULL,
  recebido_em      DATETIME      NULL,
  recebido_por     INT UNSIGNED  NULL,
  recebido_qtd     DECIMAL(14,3) NULL,
  solicitado_por   INT UNSIGNED  NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  simulado         TINYINT(1)    NOT NULL DEFAULT 0,
  KEY idx_compra_situacao (situacao),
  KEY idx_compra_aprovador (aprovador_id, situacao),
  KEY idx_compra_mp (materia_prima_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compra_cotacoes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id      INT UNSIGNED  NOT NULL,
  fornecedor     VARCHAR(160)  NOT NULL,
  valor_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
  frete          DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_total    DECIMAL(14,2) NOT NULL DEFAULT 0,
  prazo_dias     INT           NULL,
  condicao       VARCHAR(120)  NULL,
  observacao     VARCHAR(500)  NULL,
  arquivo        VARCHAR(80)   NULL,
  arquivo_nome   VARCHAR(200)  NULL,
  arquivo_mime   VARCHAR(60)   NULL,
  criado_por     INT UNSIGNED  NULL,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cot_compra (compra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compra_historico (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id  INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  texto      VARCHAR(600) NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ch_compra (compra_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
