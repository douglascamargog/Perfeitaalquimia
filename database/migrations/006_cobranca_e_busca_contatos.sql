-- =====================================================================
-- 006 — Cobrança (funil próprio) e busca de contatos com IA
-- Só acrescenta tabelas (regra do sistema em uso: nada é apagado).
-- =====================================================================

-- Etapas do funil de cobrança (editáveis em Configurações › Listas).
-- tipo: aberta (em andamento), sem_contato (pede busca de contatos), promessa, pago, acordo, juridico, perda
CREATE TABLE cobranca_etapas (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL,
  ordem INT         NOT NULL DEFAULT 0,
  cor   VARCHAR(7)  NOT NULL DEFAULT '#94a3b8',
  tipo  VARCHAR(20) NOT NULL DEFAULT 'aberta',
  ativa TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Um caso de cobrança por cliente em aberto (os títulos ficam em cobranca_titulos)
CREATE TABLE cobrancas (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id             INT UNSIGNED  NOT NULL,
  etapa_id               INT UNSIGNED  NOT NULL,
  responsavel_id         INT UNSIGNED  NULL,
  valor_total            DECIMAL(12,2) NOT NULL DEFAULT 0,   -- soma dos títulos em aberto
  vencimento_mais_antigo DATE          NULL,                 -- base dos "dias de atraso"
  promessa_em            DATE          NULL,                 -- data que o cliente prometeu pagar
  promessa_valor         DECIMAL(12,2) NULL,
  tentativas_sem_sucesso INT           NOT NULL DEFAULT 0,   -- zera quando alguém atende
  ultimo_contato_em      DATETIME      NULL,
  origem                 VARCHAR(10)   NOT NULL DEFAULT 'manual', -- manual | erp
  situacao               VARCHAR(10)   NOT NULL DEFAULT 'aberta', -- aberta | encerrada
  motivo_encerramento    VARCHAR(80)   NULL,
  observacoes            TEXT          NULL,
  encerrada_em           DATETIME      NULL,
  criado_por             INT UNSIGNED  NULL,
  criado_em              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cob_situacao_etapa (situacao, etapa_id),
  KEY idx_cob_responsavel (responsavel_id, situacao),
  KEY idx_cob_cliente (cliente_id, situacao),
  KEY idx_cob_vencimento (vencimento_mais_antigo),
  KEY idx_cob_promessa (promessa_em),
  CONSTRAINT fk_cob_cliente FOREIGN KEY (cliente_id)     REFERENCES clientes(id),
  CONSTRAINT fk_cob_etapa   FOREIGN KEY (etapa_id)       REFERENCES cobranca_etapas(id),
  CONSTRAINT fk_cob_resp    FOREIGN KEY (responsavel_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Títulos (boletos / parcelas) de cada caso. omie_codigo evita duplicar na importação do ERP.
CREATE TABLE cobranca_titulos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cobranca_id  INT UNSIGNED  NOT NULL,
  documento    VARCHAR(40)   NULL,          -- nº da nota / boleto
  parcela      VARCHAR(10)   NULL,          -- ex.: 2/3
  valor        DECIMAL(12,2) NOT NULL,
  vencimento   DATE          NOT NULL,
  pago_em      DATE          NULL,
  omie_codigo  VARCHAR(40)   NULL,
  criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tit_omie (omie_codigo),
  KEY idx_tit_cobranca (cobranca_id, pago_em),
  CONSTRAINT fk_tit_cobranca FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada busca de contatos com IA (para limite diário, custo e para mostrar a última busca)
CREATE TABLE buscas_contato (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id      INT UNSIGNED  NOT NULL,
  usuario_id      INT UNSIGNED  NULL,
  provedor        VARCHAR(20)   NOT NULL,        -- fake | claude
  situacao        VARCHAR(10)   NOT NULL,        -- ok | erro | recusada
  resultado_json  JSON          NULL,            -- contatos sugeridos + fontes (dados públicos da empresa)
  mensagem        VARCHAR(255)  NULL,
  buscas_web      INT           NOT NULL DEFAULT 0,
  tokens_entrada  INT           NOT NULL DEFAULT 0,
  tokens_saida    INT           NOT NULL DEFAULT 0,
  custo_estimado  DECIMAL(8,4)  NOT NULL DEFAULT 0, -- em dólares (preço de tabela)
  criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_busca_cliente (cliente_id, criado_em),
  KEY idx_busca_data (criado_em),
  CONSTRAINT fk_busca_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_busca_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
