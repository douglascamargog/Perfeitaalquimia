-- =====================================================================
-- 011 — Prospecção automática, mensagens prontas de WhatsApp e captura de leads
--   prospeccao_empresas: base pública de CNPJs (no protótipo, amostra simulada do RS;
--                        depois, a base aberta da Receita filtrada pelos CNAEs do nosso público)
--   prospeccao_envios:   quem enviou cada CNPJ para o funil
--   mensagens_prontas:   modelos de WhatsApp com variáveis ({nome}, {link_vendedor}...)
--   leads_recebidos:     cada formulário do site/anúncios (IP só em hash, LGPD)
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE prospeccao_empresas (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cnpj           CHAR(14)      NOT NULL,
  razao_social   VARCHAR(200)  NOT NULL,
  nome_fantasia  VARCHAR(200)  NULL,
  cnae           VARCHAR(10)   NOT NULL,                  -- só dígitos (ex.: 4729699)
  cnae_descricao VARCHAR(200)  NULL,
  situacao       VARCHAR(20)   NOT NULL DEFAULT 'ATIVA',
  abertura       DATE          NULL,
  endereco       VARCHAR(255)  NULL,
  bairro         VARCHAR(120)  NULL,
  cidade         VARCHAR(120)  NOT NULL,
  uf             CHAR(2)       NOT NULL,
  cep            CHAR(8)       NULL,
  telefone       VARCHAR(20)   NULL,
  email          VARCHAR(160)  NULL,
  porte          VARCHAR(30)   NULL,                      -- ME, EPP, Demais
  importado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pe_cnpj (cnpj),
  KEY idx_pe_local (uf, cidade),
  KEY idx_pe_cnae (cnae),
  KEY idx_pe_abertura (abertura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prospeccao_envios (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cnpj        CHAR(14)     NOT NULL,
  cliente_id  INT UNSIGNED NULL,
  usuario_id  INT UNSIGNED NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_penv_cnpj (cnpj),
  KEY idx_penv_usuario (usuario_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mensagens_prontas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo        VARCHAR(120)  NOT NULL,
  categoria     VARCHAR(60)   NOT NULL,
  texto         TEXT          NOT NULL,
  ativo         TINYINT(1)    NOT NULL DEFAULT 1,
  ordem         INT           NOT NULL DEFAULT 0,
  usos          INT UNSIGNED  NOT NULL DEFAULT 0,
  criado_por    INT UNSIGNED  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_msg_categoria (categoria, ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads_recebidos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resultado     VARCHAR(12)   NOT NULL,                   -- criado | existente | bloqueado | invalido
  motivo        VARCHAR(120)  NULL,                       -- por que foi bloqueado/recusado
  cliente_id    INT UNSIGNED  NULL,
  nome          VARCHAR(200)  NULL,
  empresa       VARCHAR(200)  NULL,
  cidade        VARCHAR(120)  NULL,
  uf            CHAR(2)       NULL,
  dados_json    JSON          NULL,                       -- campos recebidos já limpos
  origem        VARCHAR(20)   NULL,                       -- site | anuncio
  utm_source    VARCHAR(120)  NULL,
  utm_medium    VARCHAR(120)  NULL,
  utm_campaign  VARCHAR(120)  NULL,
  utm_term      VARCHAR(120)  NULL,
  utm_content   VARCHAR(120)  NULL,
  pagina        VARCHAR(255)  NULL,
  ip_hash       CHAR(64)      NULL,                       -- sha256(ip + segredo): nunca o IP em texto
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lr_data (criado_em),
  KEY idx_lr_ip (ip_hash, criado_em),
  KEY idx_lr_campanha (utm_source, utm_campaign),
  KEY idx_lr_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
