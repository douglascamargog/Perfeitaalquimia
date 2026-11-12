-- =====================================================================
-- 001 — Base do sistema: perfis, permissões, funções, usuários,
--       configurações, auditoria e tentativas de login.
-- =====================================================================

-- Perfis de acesso (Admin, Gestor, Vendedor, Financeiro, Marketing, Produção)
CREATE TABLE perfis (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(40)  NOT NULL UNIQUE,     -- identificador usado no código (ex.: 'financeiro')
  nome      VARCHAR(80)  NOT NULL,            -- nome mostrado na tela
  descricao VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O que cada perfil pode fazer. '*' = tudo (admin)
CREATE TABLE perfil_permissoes (
  perfil_id INT UNSIGNED NOT NULL,
  permissao VARCHAR(60)  NOT NULL,            -- ex.: 'carteira.ver_todas', 'pedidos.aprovar'
  PRIMARY KEY (perfil_id, permissao),
  CONSTRAINT fk_pp_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Funções comerciais. Regras e filas usam FUNÇÕES, nunca nomes de pessoas.
CREATE TABLE funcoes (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(40)  NOT NULL UNIQUE,     -- ex.: 'prospeccao_reativacao' (usado pelas regras)
  nome      VARCHAR(80)  NOT NULL,
  descricao VARCHAR(255) NULL,
  ativa     TINYINT(1)   NOT NULL DEFAULT 1,
  ordem     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuários do sistema (login individual)
CREATE TABLE usuarios (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome             VARCHAR(120) NOT NULL,
  email            VARCHAR(160) NOT NULL UNIQUE,
  senha_hash       VARCHAR(255) NOT NULL,
  perfil_id        INT UNSIGNED NOT NULL,
  codigo_vendedor  VARCHAR(20)  NULL UNIQUE,  -- usado no link do catálogo: /catalogo/v/{codigo}
  telefone         VARCHAR(20)  NULL,
  ativo            TINYINT(1)   NOT NULL DEFAULT 1,
  trocar_senha     TINYINT(1)   NOT NULL DEFAULT 0, -- obriga a trocar a senha no próximo acesso
  ultimo_login_em  DATETIME     NULL,
  criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ligação pessoa ↔ função (uma pessoa pode ter mais de uma função)
CREATE TABLE usuario_funcoes (
  usuario_id INT UNSIGNED NOT NULL,
  funcao_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, funcao_id),
  CONSTRAINT fk_uf_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_uf_funcao  FOREIGN KEY (funcao_id)  REFERENCES funcoes(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regras de negócio editáveis pelo admin (dias de inatividade, sessão, etc.)
CREATE TABLE configuracoes (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave     VARCHAR(80)  NOT NULL UNIQUE,     -- ex.: 'carteira.dias_inatividade'
  valor     TEXT         NULL,
  tipo      VARCHAR(20)  NOT NULL DEFAULT 'texto', -- texto | numero | booleano | lista
  grupo     VARCHAR(40)  NOT NULL DEFAULT 'Geral', -- agrupa na tela
  rotulo    VARCHAR(120) NOT NULL,            -- nome amigável na tela
  descricao VARCHAR(255) NULL,                -- explicação para o admin
  ordem     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de auditoria: quem, o quê, quando
CREATE TABLE auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  entidade    VARCHAR(40)  NOT NULL,          -- ex.: 'cliente', 'pedido', 'configuracao'
  entidade_id INT UNSIGNED NULL,
  acao        VARCHAR(40)  NOT NULL,          -- ex.: 'criar', 'editar', 'login'
  antes_json  JSON         NULL,
  depois_json JSON         NULL,
  ip          VARCHAR(45)  NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aud_entidade (entidade, entidade_id),
  INDEX idx_aud_usuario (usuario_id),
  INDEX idx_aud_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login erradas (bloqueio contra força bruta)
CREATE TABLE tentativas_login (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email     VARCHAR(160) NULL,
  ip        VARCHAR(45)  NULL,
  criado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tl_email (email, criado_em),
  INDEX idx_tl_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
