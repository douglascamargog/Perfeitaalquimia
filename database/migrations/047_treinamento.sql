-- =====================================================================
-- 047 — Treinamento por setor + tutorial do One em cada tela (V13.0)
--   tutorial_telas / tutorial_progresso: o passo a passo que o One mostra em cada tela,
--     até a pessoa clicar em "Aprendi tudo desta tela".
--   trein_trilhas / trein_modulos / trein_perguntas / trein_progresso: o treinamento por setor
--     (Integração para todos, trilha de cada função, áreas vizinhas), com prova no fim de cada módulo.
--   usuarios.tutorial_ativo: 1 = o One mostra os tutoriais sozinho (pessoas novas, ou reiniciado pelo gestor).
-- A Academia antiga (academia_conteudo, academia_progresso) continua no banco, sem uso na tela.
-- Só acrescenta (nada é apagado).
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN tutorial_ativo TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE tutorial_telas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rota          VARCHAR(120) NOT NULL UNIQUE,      -- começo do endereço da tela, ex.: /carteira
  titulo        VARCHAR(120) NOT NULL,
  passos_json   JSON         NOT NULL,             -- [{"alvo": "seletor CSS ou vazio", "texto": "..."}]
  ordem         INT          NOT NULL DEFAULT 0,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  editado_em    DATETIME     NULL,                 -- preenchido quando alguém edita pela tela (a atualização não sobrescreve)
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tutorial_progresso (
  usuario_id   INT UNSIGNED NOT NULL,
  rota         VARCHAR(120) NOT NULL,
  vezes        INT          NOT NULL DEFAULT 0,     -- quantas vezes o tutorial abriu
  aprendido_em DATETIME     NULL,
  PRIMARY KEY (usuario_id, rota),
  CONSTRAINT fk_tutprog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trein_trilhas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave       VARCHAR(60)  NOT NULL UNIQUE,        -- 'integracao' ou o slug da função (televendas, producao...)
  funcao_slug VARCHAR(60)  NULL,                   -- NULL = para todos
  titulo      VARCHAR(120) NOT NULL,
  descricao   VARCHAR(255) NULL,
  icone       VARCHAR(10)  NULL,                   -- emoji
  vizinhos    VARCHAR(255) NULL,                   -- funções com que este setor tem contato (slugs separados por vírgula)
  ordem       INT          NOT NULL DEFAULT 0,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trein_modulos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trilha_id   INT UNSIGNED NOT NULL,
  chave       VARCHAR(80)  NOT NULL UNIQUE,        -- identificador fixo (a atualização acha o módulo por ele)
  tipo        VARCHAR(20)  NOT NULL DEFAULT 'setor', -- setor | vizinho (como funciona este setor, para quem tem contato)
  titulo      VARCHAR(160) NOT NULL,
  resumo      VARCHAR(255) NULL,
  conteudo    MEDIUMTEXT   NOT NULL,               -- texto simples: ## título, - lista, > destaque, **negrito**, [link](/tela)
  tempo_min   INT          NOT NULL DEFAULT 5,
  ordem       INT          NOT NULL DEFAULT 0,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1,
  editado_em  DATETIME     NULL,                   -- editado pela tela: a atualização não sobrescreve
  KEY idx_trmod_trilha (trilha_id, ordem),
  CONSTRAINT fk_trmod_trilha FOREIGN KEY (trilha_id) REFERENCES trein_trilhas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trein_perguntas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  modulo_id   INT UNSIGNED NOT NULL,
  pergunta    VARCHAR(255) NOT NULL,
  opcoes_json JSON         NOT NULL,               -- ["opção A", "opção B", ...]
  correta     TINYINT      NOT NULL,               -- índice da opção certa (0, 1, 2...)
  explicacao  VARCHAR(255) NULL,
  ordem       INT          NOT NULL DEFAULT 0,
  KEY idx_trperg_modulo (modulo_id),
  CONSTRAINT fk_trperg_modulo FOREIGN KEY (modulo_id) REFERENCES trein_modulos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trein_progresso (
  usuario_id  INT UNSIGNED NOT NULL,
  modulo_id   INT UNSIGNED NOT NULL,
  lido_em     DATETIME     NULL,
  nota        TINYINT      NULL,                   -- melhor nota (0 a 100)
  tentativas  INT          NOT NULL DEFAULT 0,
  aprovado_em DATETIME     NULL,
  PRIMARY KEY (usuario_id, modulo_id),
  KEY idx_trprog_modulo (modulo_id),
  CONSTRAINT fk_trprog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_trprog_modulo  FOREIGN KEY (modulo_id)  REFERENCES trein_modulos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
