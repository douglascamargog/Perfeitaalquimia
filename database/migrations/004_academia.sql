-- =====================================================================
-- 004 — Academia (Central de Televendas) e cadastro de produtos.
--       O cadastro de produtos é o MESMO que o catálogo B2B vai usar
--       (mesma foto e ficha: atualizou num lugar, muda nos dois).
-- =====================================================================

-- Biblioteca de mídia: imagens e vídeos guardados em /public/media
CREATE TABLE midias (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  arquivo   VARCHAR(255) NOT NULL UNIQUE,     -- caminho a partir de /public, ex.: media/academia/ab12cd.webp
  tipo      VARCHAR(40)  NOT NULL,            -- image/webp, video/mp4...
  tamanho   INT UNSIGNED NOT NULL DEFAULT 0,  -- bytes
  hash      CHAR(40)     NOT NULL,            -- sha1 do conteúdo (evita guardar a mesma imagem duas vezes)
  alt       VARCHAR(255) NULL,                -- descrição da imagem (acessibilidade)
  origem    VARCHAR(80)  NULL,                -- de onde veio (ex.: 'Central de Televendas V2')
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_midia_hash (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias de produto (Cápsulas, Chás a granel, Pós e bebidas...)
CREATE TABLE categorias (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL UNIQUE,
  ordem INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos. Preço e estoque vêm do Omie (tabelas próprias, etapa do catálogo/pedidos).
CREATE TABLE produtos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(120) NOT NULL UNIQUE,      -- usado no endereço da ficha
  nome             VARCHAR(160) NOT NULL,
  embalagem        VARCHAR(120) NULL,                 -- ex.: '60 cápsulas'
  categoria_id     INT UNSIGNED NULL,
  chamada          VARCHAR(255) NULL,                 -- frase curta da ficha (tagline)
  imagem_id        INT UNSIGNED NULL,                 -- foto principal (tabela midias)
  ficha_json       JSON         NOT NULL,             -- conteúdo da ficha técnica e de treinamento (benefícios, ingredientes, FAQ, falas...)
  ordem            INT          NOT NULL DEFAULT 0,
  ativo            TINYINT(1)   NOT NULL DEFAULT 1,
  visivel_catalogo TINYINT(1)   NOT NULL DEFAULT 1,
  omie_codigo      VARCHAR(40)  NULL,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prod_categoria (categoria_id),
  CONSTRAINT fk_prod_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT fk_prod_imagem    FOREIGN KEY (imagem_id)    REFERENCES midias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conteúdo da Academia em blocos (trilha, temas de busca, aulas de CRM, materiais de marketing...)
CREATE TABLE academia_conteudo (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave         VARCHAR(60) NOT NULL UNIQUE,         -- 'modules', 'topics', 'crm', 'media', 'marketing'
  conteudo_json LONGTEXT    NOT NULL,
  atualizado_em DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
