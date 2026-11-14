-- =====================================================================
-- 026 — Desenvolvimento de Produto (do briefing ao lançamento)
--   dp_etapas: as etapas (configuráveis): função responsável, checagem automática e quem aprova.
--   dp_projetos: cada produto novo em desenvolvimento (nosso ou de um cliente de Terceirização).
--   dp_projeto_etapas: situação de cada etapa no projeto (quem concluiu e quando).
--   dp_aprovacoes: aprovação da prova da gráfica (Marketing e Qualidade).
--   dp_historico: linha do tempo do projeto (movimentos e comentários).
-- Só acrescenta (regra do sistema em uso).
-- =====================================================================
CREATE TABLE dp_etapas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(120) NOT NULL,
  ordem       INT          NOT NULL DEFAULT 0,
  funcao_slug VARCHAR(60)  NULL,                 -- função que faz a etapa (tecnica, marketing, qualidade, producao...)
  checagem    VARCHAR(30)  NULL,                 -- regra automática: ficha | composicao | codigo | arte | compras | grafica | aprovacao | recebimento | piloto | lancamento
  aprovadores VARCHAR(120) NULL,                 -- funções que aprovam (ex.: marketing,qualidade)
  ativa       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dp_projetos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(160) NOT NULL,
  briefing     TEXT         NULL,
  categoria_id INT UNSIGNED NULL,
  embalagem    VARCHAR(120) NULL,
  cliente_id   INT UNSIGNED NULL,                -- preenchido = Terceirização (produto com a marca do cliente)
  produto_id   INT UNSIGNED NULL,                -- produto criado no início (oculto até o lançamento)
  etapa_id     INT UNSIGNED NULL,
  situacao     VARCHAR(12)  NOT NULL DEFAULT 'andamento', -- andamento | pausado | lancado | cancelado
  prazo        DATE         NULL,
  grafica      VARCHAR(160) NULL,
  grafica_pedido_em DATE    NULL,
  rotulos_recebidos INT     NOT NULL DEFAULT 0,
  ordem_id     INT UNSIGNED NULL,                -- ordem de produção do lote piloto
  compras_ok   TEXT         NULL,                -- ids das matérias-primas marcadas como compradas (JSON)
  criado_por   INT UNSIGNED NULL,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  lancado_em   DATETIME     NULL,
  simulado     TINYINT(1)   NOT NULL DEFAULT 0,
  KEY idx_dp_situacao (situacao, etapa_id),
  KEY idx_dp_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dp_projeto_etapas (
  projeto_id    INT UNSIGNED NOT NULL,
  etapa_id      INT UNSIGNED NOT NULL,
  situacao      VARCHAR(12)  NOT NULL DEFAULT 'pendente', -- pendente | andamento | concluida
  concluida_por INT UNSIGNED NULL,
  concluida_em  DATETIME     NULL,
  observacao    VARCHAR(500) NULL,
  PRIMARY KEY (projeto_id, etapa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dp_aprovacoes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  projeto_id  INT UNSIGNED NOT NULL,
  etapa_id    INT UNSIGNED NOT NULL,
  funcao_slug VARCHAR(60)  NOT NULL,
  usuario_id  INT UNSIGNED NOT NULL,
  decisao     VARCHAR(10)  NOT NULL,               -- aprovado | ajustar
  comentario  VARCHAR(500) NULL,
  rodada      INT          NOT NULL DEFAULT 1,     -- cada "Ajustar" abre uma rodada nova de aprovação
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dpa_projeto (projeto_id, etapa_id, rodada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dp_historico (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  projeto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  tipo       VARCHAR(20)  NOT NULL DEFAULT 'comentario', -- comentario | etapa | aprovacao | sistema
  texto      VARCHAR(1000) NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dph_projeto (projeto_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
