-- =====================================================================
-- 007 — Marketing: planejamento (campanhas), mural de posts do Instagram
--       com aprovação, e números do e-commerce (Tray).
-- Só acrescenta tabelas (regra do sistema em uso: nada é apagado).
-- =====================================================================

-- Campanhas do planejamento (ex.: Black Friday, lançamento, Dia das Mães)
CREATE TABLE mkt_campanhas (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(120)  NOT NULL,
  objetivo       VARCHAR(255)  NULL,
  inicio         DATE          NOT NULL,
  fim            DATE          NOT NULL,
  cor            VARCHAR(7)    NOT NULL DEFAULT '#9bd344',
  meta_descricao VARCHAR(255)  NULL,          -- ex.: vender 300 un de Magnésio no site
  orcamento      DECIMAL(12,2) NULL,
  produto_id     INT UNSIGNED  NULL,          -- mesmo cadastro de produtos da Academia/catálogo
  situacao       VARCHAR(12)   NOT NULL DEFAULT 'planejada', -- planejada | ativa | encerrada
  observacoes    TEXT          NULL,
  criado_por     INT UNSIGNED  NULL,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_camp_periodo (inicio, fim),
  CONSTRAINT fk_camp_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas do post (editáveis em Configurações › Listas).
-- tipo: rascunho | producao | aprovacao (aguardando) | ajuste | aprovado | agendado | publicado
CREATE TABLE mkt_post_etapas (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL,
  ordem INT         NOT NULL DEFAULT 0,
  cor   VARCHAR(7)  NOT NULL DEFAULT '#94a3b8',
  tipo  VARCHAR(20) NOT NULL DEFAULT 'rascunho',
  ativa TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Posts do Instagram (mural do mês)
CREATE TABLE mkt_posts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campanha_id     INT UNSIGNED  NULL,
  formato         VARCHAR(12)   NOT NULL DEFAULT 'feed',   -- feed | carrossel | reels | stories
  titulo          VARCHAR(160)  NOT NULL,
  legenda         TEXT          NULL,
  hashtags        VARCHAR(1000) NULL,
  data_publicacao DATE          NOT NULL,
  hora            TIME          NULL,
  etapa_id        INT UNSIGNED  NOT NULL,
  responsavel_id  INT UNSIGNED  NULL,
  midia_id        INT UNSIGNED  NULL,
  link_publicado  VARCHAR(500)  NULL,
  aprovado_por    INT UNSIGNED  NULL,
  aprovado_em     DATETIME      NULL,
  motivo_ajuste   VARCHAR(500)  NULL,
  criado_por      INT UNSIGNED  NULL,
  criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_post_data (data_publicacao),
  KEY idx_post_etapa (etapa_id),
  KEY idx_post_campanha (campanha_id),
  CONSTRAINT fk_post_campanha FOREIGN KEY (campanha_id)    REFERENCES mkt_campanhas(id) ON DELETE SET NULL,
  CONSTRAINT fk_post_etapa    FOREIGN KEY (etapa_id)       REFERENCES mkt_post_etapas(id),
  CONSTRAINT fk_post_resp     FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
  CONSTRAINT fk_post_midia    FOREIGN KEY (midia_id)       REFERENCES midias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversa e histórico do post (pedidos de ajuste, aprovação, publicação)
CREATE TABLE mkt_post_comentarios (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id    INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  tipo       VARCHAR(12)  NOT NULL DEFAULT 'comentario', -- comentario | ajuste | aprovacao | etapa
  texto      TEXT         NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_coment_post (post_id, criado_em),
  CONSTRAINT fk_coment_post    FOREIGN KEY (post_id)    REFERENCES mkt_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_coment_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-commerce (Tray): números por dia, copiados da loja para o painel abrir rápido e guardar histórico
CREATE TABLE ecommerce_resumo_diario (
  data           DATE          NOT NULL PRIMARY KEY,
  pedidos        INT           NOT NULL DEFAULT 0,
  faturamento    DECIMAL(12,2) NOT NULL DEFAULT 0,
  visitas        INT           NULL,          -- só se a loja fornecer
  novos_clientes INT           NOT NULL DEFAULT 0,
  origem         VARCHAR(10)   NOT NULL DEFAULT 'fake', -- fake | tray
  atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-commerce: produtos mais vendidos por mês
CREATE TABLE ecommerce_produtos_mes (
  mes         CHAR(7)       NOT NULL,          -- AAAA-MM
  produto_ref VARCHAR(80)   NOT NULL,          -- código do produto na loja
  nome        VARCHAR(200)  NOT NULL,
  quantidade  INT           NOT NULL DEFAULT 0,
  faturamento DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (mes, produto_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
