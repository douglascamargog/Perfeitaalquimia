-- =====================================================================
-- 003 — Carteira e CRM: cidades, clientes/leads, linha do tempo,
--       tarefas (agenda) e importações de planilhas.
-- =====================================================================

-- Cidades com coordenadas (filtros e mapa). O ponto do cliente sem lat/lng é aproximado pela cidade.
CREATE TABLE cidades (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome     VARCHAR(120) NOT NULL,
  uf       CHAR(2)      NOT NULL,
  cod_ibge INT UNSIGNED NULL,
  lat      DECIMAL(9,6) NULL,
  lng      DECIMAL(9,6) NULL,
  UNIQUE KEY uk_cidade (uf, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clientes e leads (o mesmo cadastro: "lead" vira "cliente" quando compra)
CREATE TABLE clientes (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo                VARCHAR(10)  NOT NULL DEFAULT 'lead',   -- lead | cliente
  razao_social        VARCHAR(200) NOT NULL,                  -- nome principal exibido
  nome_fantasia       VARCHAR(200) NULL,
  cnpj                CHAR(14)     NULL,                      -- só dígitos; único quando informado
  endereco            VARCHAR(255) NULL,
  bairro              VARCHAR(120) NULL,
  cep                 CHAR(8)      NULL,
  cidade              VARCHAR(120) NULL,
  uf                  CHAR(2)      NULL,
  cidade_id           INT UNSIGNED NULL,
  regiao_id           INT UNSIGNED NULL,
  lat                 DECIMAL(9,6) NULL,                      -- vazio = ponto aproximado pela cidade
  lng                 DECIMAL(9,6) NULL,
  segmento_id         INT UNSIGNED NULL,
  segmento_original   VARCHAR(120) NULL,                      -- como estava escrito na planilha de origem
  telefone            VARCHAR(20)  NULL,
  whatsapp            VARCHAR(20)  NULL,
  email               VARCHAR(160) NULL,
  instagram           VARCHAR(120) NULL,
  facebook            VARCHAR(255) NULL,
  site                VARCHAR(255) NULL,
  mix_produtos        TEXT         NULL,                      -- o que a loja vende (ajuda a abordagem)
  fonte_url           VARCHAR(500) NULL,                      -- onde o lead foi encontrado
  validacao           VARCHAR(255) NULL,                      -- o que ainda precisa ser confirmado
  tabela_preco_id     INT UNSIGNED NULL,
  responsavel_id      INT UNSIGNED NULL,                      -- pessoa responsável (sempre preenchido pelas regras)
  funcao_id           INT UNSIGNED NULL,                      -- função/fila dona do cliente
  etapa_id            INT UNSIGNED NULL,
  prioridade          VARCHAR(10)  NOT NULL DEFAULT 'Média',
  contatado           TINYINT(1)   NOT NULL DEFAULT 0,
  ultimo_contato_em   DATE         NULL,
  origem_id           INT UNSIGNED NULL,
  origem_detalhe      VARCHAR(160) NULL,
  utm_source          VARCHAR(120) NULL,
  utm_medium          VARCHAR(120) NULL,
  utm_campaign        VARCHAR(120) NULL,
  utm_term            VARCHAR(120) NULL,
  utm_content         VARCHAR(120) NULL,
  observacoes         TEXT         NULL,
  ultima_compra_em    DATE         NULL,                      -- vem do faturamento (Omie)
  intervalo_medio_dias INT         NULL,                      -- média entre compras (alerta de recompra)
  credito_situacao    VARCHAR(10)  NOT NULL DEFAULT 'ok',     -- ok | atraso
  omie_codigo         VARCHAR(40)  NULL,
  criado_por          INT UNSIGNED NULL,
  criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  excluido_em         DATETIME     NULL,
  UNIQUE KEY uk_cliente_cnpj (cnpj),
  KEY idx_cli_uf_cidade (uf, cidade),
  KEY idx_cli_cidade (cidade_id),
  KEY idx_cli_segmento (segmento_id),
  KEY idx_cli_responsavel (responsavel_id),
  KEY idx_cli_funcao (funcao_id),
  KEY idx_cli_etapa (etapa_id),
  KEY idx_cli_prioridade (prioridade),
  KEY idx_cli_ultima_compra (ultima_compra_em),
  KEY idx_cli_regiao (regiao_id),
  FULLTEXT KEY ft_cli_nome (razao_social, nome_fantasia),
  CONSTRAINT fk_cli_cidade   FOREIGN KEY (cidade_id)       REFERENCES cidades(id),
  CONSTRAINT fk_cli_regiao   FOREIGN KEY (regiao_id)       REFERENCES regioes(id),
  CONSTRAINT fk_cli_segmento FOREIGN KEY (segmento_id)     REFERENCES segmentos(id),
  CONSTRAINT fk_cli_tabela   FOREIGN KEY (tabela_preco_id) REFERENCES tabelas_preco(id),
  CONSTRAINT fk_cli_resp     FOREIGN KEY (responsavel_id)  REFERENCES usuarios(id),
  CONSTRAINT fk_cli_funcao   FOREIGN KEY (funcao_id)       REFERENCES funcoes(id),
  CONSTRAINT fk_cli_etapa    FOREIGN KEY (etapa_id)        REFERENCES funil_etapas(id),
  CONSTRAINT fk_cli_origem   FOREIGN KEY (origem_id)       REFERENCES origens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linha do tempo do cliente: contatos, ligações, mudanças de etapa, pedidos...
CREATE TABLE cliente_eventos (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT UNSIGNED NOT NULL,
  usuario_id  INT UNSIGNED NULL,
  tipo        VARCHAR(20)  NOT NULL,       -- contato | ligacao | whatsapp | visita | email | nota | etapa | responsavel | importacao | pedido
  canal       VARCHAR(30)  NULL,           -- WhatsApp, Ligação, Visita presencial, E-mail...
  descricao   TEXT         NULL,
  dados_json  JSON         NULL,
  ocorrido_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ev_cliente (cliente_id, ocorrido_em),
  KEY idx_ev_data (ocorrido_em),
  CONSTRAINT fk_ev_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ev_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda: próximos contatos e tarefas
CREATE TABLE tarefas (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id   INT UNSIGNED NULL,
  usuario_id   INT UNSIGNED NOT NULL,       -- quem deve fazer
  titulo       VARCHAR(200) NOT NULL,
  tipo         VARCHAR(20)  NOT NULL DEFAULT 'retorno', -- retorno | ligacao | visita | outro
  vence_em     DATE         NOT NULL,
  concluida_em DATETIME     NULL,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tar_usuario (usuario_id, concluida_em, vence_em),
  KEY idx_tar_cliente (cliente_id, concluida_em),
  CONSTRAINT fk_tar_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_tar_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Importações de planilha (cabeçalho) e suas linhas (pré-visualização antes de gravar)
CREATE TABLE importacoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT UNSIGNED NOT NULL,
  arquivo      VARCHAR(200) NOT NULL,      -- nome original do arquivo
  formato      VARCHAR(10)  NOT NULL,      -- csv | xlsx | backup
  total        INT          NOT NULL DEFAULT 0,
  novos        INT          NOT NULL DEFAULT 0,
  atualizados  INT          NOT NULL DEFAULT 0,
  duplicados   INT          NOT NULL DEFAULT 0,
  erros        INT          NOT NULL DEFAULT 0,
  status       VARCHAR(20)  NOT NULL DEFAULT 'previa', -- previa | concluida | cancelada
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluida_em DATETIME     NULL,
  CONSTRAINT fk_imp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE importacao_linhas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  importacao_id INT UNSIGNED NOT NULL,
  linha         INT          NOT NULL,      -- número da linha na planilha
  acao          VARCHAR(12)  NOT NULL,      -- novo | atualizar | duplicado | erro
  cliente_id    INT UNSIGNED NULL,          -- cliente existente (atualizar/duplicado)
  dados_json    JSON         NOT NULL,      -- dados já normalizados
  mensagem      VARCHAR(255) NULL,
  KEY idx_impl (importacao_id, acao),
  CONSTRAINT fk_impl_imp FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
