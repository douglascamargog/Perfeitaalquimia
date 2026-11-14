-- =====================================================================
-- 034 — Tarefas e pedidos ao Marketing
--   mkt_tipos: tipos de tarefa e a FUNÇÃO que recebe cada um (editável em Configurações › Listas).
--   mkt_tarefas: o que o Marketing tem para fazer — pedidos de outros setores ou tarefas internas.
--     a_fazer → fazendo → aprovacao (Gestor de Marketing) → feito  (ou cancelado)
--   mkt_tarefa_historico: comentários e mudanças.
-- Só acrescenta.
-- =====================================================================
CREATE TABLE mkt_tipos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(100) NOT NULL,
  funcao_slug  VARCHAR(40)  NOT NULL,
  ordem        INT          NOT NULL DEFAULT 0,
  ativo        TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mkt_tarefas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(160)  NOT NULL,
  descricao       TEXT          NULL,
  tipo_id         INT UNSIGNED  NULL,
  funcao_slug     VARCHAR(40)   NOT NULL,
  responsavel_id  INT UNSIGNED  NULL,
  situacao        VARCHAR(10)   NOT NULL DEFAULT 'a_fazer', -- a_fazer | fazendo | aprovacao | feito | cancelado
  urgente         TINYINT(1)    NOT NULL DEFAULT 0,
  prazo           DATE          NULL,
  solicitante_id  INT UNSIGNED  NULL,
  setor           VARCHAR(100)  NULL,  -- setor de quem pediu (nome da função), vazio = tarefa interna
  anexo           VARCHAR(60)   NULL,
  anexo_nome      VARCHAR(200)  NULL,
  anexo_mime      VARCHAR(60)   NULL,
  post_id         INT UNSIGNED  NULL,
  criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  iniciado_em     DATETIME      NULL,
  concluido_em    DATETIME      NULL,
  atualizado_em   DATETIME      NULL,
  KEY idx_mt_situacao (situacao, funcao_slug),
  KEY idx_mt_solicitante (solicitante_id),
  KEY idx_mt_prazo (prazo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mkt_tarefa_historico (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarefa_id   INT UNSIGNED  NOT NULL,
  usuario_id  INT UNSIGNED  NULL,
  texto       VARCHAR(1000) NOT NULL,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mth_tarefa (tarefa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
