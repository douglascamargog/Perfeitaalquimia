-- =====================================================================
-- 018 — Comunicados (em destaque no topo da tela)
--   Quem tem a permissão comunicados.emitir escolhe os setores (funções) ou "toda a empresa".
--   Cada pessoa vê o comunicado até clicar em "Li e entendi" (ou até a data de fim / encerrar).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE comunicados (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo       VARCHAR(140)  NOT NULL,
  texto        TEXT          NOT NULL,
  prioridade   VARCHAR(12)   NOT NULL DEFAULT 'normal',   -- normal, importante
  toda_empresa TINYINT(1)    NOT NULL DEFAULT 0,
  inicio       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fim          DATETIME      NULL,
  encerrado_em DATETIME      NULL,
  criado_por   INT UNSIGNED  NULL,
  criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comunicados_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comunicado_funcoes (
  comunicado_id INT UNSIGNED NOT NULL,
  funcao_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (comunicado_id, funcao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comunicado_leituras (
  comunicado_id INT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED NOT NULL,
  lida_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comunicado_id, usuario_id),
  KEY idx_leitura_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
