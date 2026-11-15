-- =====================================================================
-- 036 — Compras da empresa e página completa do item comprado
--   materias_primas passa a guardar também os itens de USO E CONSUMO (tipo 'consumo': escritório, limpeza...)
--   com uma categoria. Um cadastro só: o pote é o mesmo item na Produção e no Financeiro.
--   mp_fornecedores: vários fornecedores por item (último preço, prazo, principal).
--   compras_lancadas (+ itens): cada NF/compra lançada (manual, do Omie ou de um orçamento da Qualidade).
--     situacao 'pendente' = nota do Omie com item ainda não ligado a um item do One.
--   mp_documentos: laudos, fichas técnicas, FISPQ, certificados e as NFs de cada item (storage/documentos/itens).
-- Só acrescenta.
-- =====================================================================
ALTER TABLE materias_primas ADD COLUMN categoria VARCHAR(60) NULL DEFAULT NULL;
ALTER TABLE mp_movimentos ADD COLUMN fornecedor_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE mp_movimentos ADD COLUMN lancamento_id INT UNSIGNED NULL DEFAULT NULL;

CREATE TABLE mp_fornecedores (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  materia_prima_id      INT UNSIGNED  NOT NULL,
  fornecedor            VARCHAR(160)  NOT NULL,
  cnpj                  VARCHAR(18)   NULL,
  contato               VARCHAR(120)  NULL,
  telefone              VARCHAR(30)   NULL,
  email                 VARCHAR(160)  NULL,
  codigo_no_fornecedor  VARCHAR(60)   NULL,
  ultimo_preco          DECIMAL(14,4) NULL,
  ultima_compra_em      DATE          NULL,
  prazo_dias            INT           NULL,
  principal             TINYINT(1)    NOT NULL DEFAULT 0,
  ativo                 TINYINT(1)    NOT NULL DEFAULT 1,
  observacoes           VARCHAR(500)  NULL,
  criado_em             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mpf_item (materia_prima_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras_lancadas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  origem        VARCHAR(10)   NOT NULL DEFAULT 'manual', -- manual | omie | orcamento
  situacao      VARCHAR(10)   NOT NULL DEFAULT 'lancado', -- lancado | pendente (item do Omie sem ligação)
  numero_nf     VARCHAR(30)   NULL,
  serie         VARCHAR(10)   NULL,
  fornecedor    VARCHAR(160)  NOT NULL,
  cnpj          VARCHAR(18)   NULL,
  emissao       DATE          NOT NULL,
  valor_total   DECIMAL(14,2) NOT NULL DEFAULT 0,
  omie_codigo   VARCHAR(40)   NULL,
  compra_id     INT UNSIGNED  NULL,
  arquivo       VARCHAR(60)   NULL,
  arquivo_nome  VARCHAR(200)  NULL,
  arquivo_mime  VARCHAR(80)   NULL,
  observacoes   VARCHAR(500)  NULL,
  lancado_por   INT UNSIGNED  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cl_omie (omie_codigo),
  KEY idx_cl_emissao (emissao),
  KEY idx_cl_situacao (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras_lancadas_itens (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lancamento_id     INT UNSIGNED  NOT NULL,
  materia_prima_id  INT UNSIGNED  NULL,
  omie_item         VARCHAR(40)   NULL,
  descricao         VARCHAR(200)  NOT NULL,
  quantidade        DECIMAL(14,3) NOT NULL,
  valor_unitario    DECIMAL(14,4) NOT NULL DEFAULT 0,
  total             DECIMAL(14,2) NOT NULL DEFAULT 0,
  lancado           TINYINT(1)    NOT NULL DEFAULT 0,
  KEY idx_cli_lanc (lancamento_id),
  KEY idx_cli_item (materia_prima_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mp_documentos (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  materia_prima_id  INT UNSIGNED  NOT NULL,
  tipo              VARCHAR(20)   NOT NULL DEFAULT 'outro', -- laudo | ficha_tecnica | fispq | certificado | nf | outro
  titulo            VARCHAR(160)  NOT NULL,
  arquivo           VARCHAR(60)   NOT NULL,
  nome_original     VARCHAR(200)  NOT NULL,
  mime              VARCHAR(80)   NOT NULL,
  tamanho           INT UNSIGNED  NOT NULL DEFAULT 0,
  validade          DATE          NULL,
  lote              VARCHAR(40)   NULL,
  fornecedor_id     INT UNSIGNED  NULL,
  lancamento_id     INT UNSIGNED  NULL,
  enviado_por       INT UNSIGNED  NULL,
  criado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  arquivado_em      DATETIME      NULL,
  KEY idx_mpd_item (materia_prima_id, arquivado_em),
  KEY idx_mpd_validade (validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
