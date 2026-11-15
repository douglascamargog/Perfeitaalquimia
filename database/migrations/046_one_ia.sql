-- =====================================================================
-- 046 — Pergunte ao One (V12.4)
--   one_perguntas: registro de USO (quem, quando, quanto custou, que consultas fez).
--   O texto da pergunta e da resposta NÃO é guardado (LGPD).
-- Só acrescenta (nada é apagado).
-- =====================================================================
CREATE TABLE one_perguntas (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id       INT UNSIGNED NOT NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  provedor         VARCHAR(20) NOT NULL,                 -- local (sem IA) | claude
  consultas        VARCHAR(255) NULL,                    -- nomes das consultas que o One fez (ex.: buscar_clientes,meus_numeros)
  tokens_entrada   INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_saida     INT UNSIGNED NOT NULL DEFAULT 0,
  custo_estimado   DECIMAL(10,4) NOT NULL DEFAULT 0,     -- US$
  situacao         VARCHAR(20) NOT NULL DEFAULT 'ok',    -- ok | erro | limite | recusada
  KEY idx_onep_usuario (usuario_id, criado_em),
  KEY idx_onep_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
