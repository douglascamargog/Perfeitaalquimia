-- =====================================================================
-- 024 — Ligações (Ligue Talk) e Acompanhamento da gestão
--   usuarios.ramal: ramal da pessoa no Ligue Talk (de onde sai a ligação).
--   ligacoes: cada chamada feita pelo sistema (ou que chegou do Ligue Talk): quem, cliente, situação,
--             duração e a gravação (arquivo guardado fora da pasta pública ou endereço do Ligue Talk).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE usuarios ADD COLUMN ramal VARCHAR(20) NULL DEFAULT NULL;

CREATE TABLE ligacoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_externo   VARCHAR(80)  NULL,                    -- id da chamada no Ligue Talk (evita duplicar)
  usuario_id   INT UNSIGNED NULL,
  cliente_id   INT UNSIGNED NULL,
  numero       VARCHAR(20)  NULL,
  sentido      VARCHAR(10)  NOT NULL DEFAULT 'saida',  -- saida | entrada
  situacao     VARCHAR(15)  NOT NULL DEFAULT 'chamando', -- chamando | atendida | nao_atendida | ocupado | falha
  inicio       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  duracao_seg  INT UNSIGNED NOT NULL DEFAULT 0,
  gravacao     VARCHAR(255) NULL,                    -- arquivo em storage/gravacoes ou endereço no Ligue Talk
  origem       VARCHAR(12)  NOT NULL DEFAULT 'sistema', -- sistema | ligue_talk | demonstracao
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_lig_externo (id_externo),
  KEY idx_lig_usuario (usuario_id, inicio),
  KEY idx_lig_cliente (cliente_id),
  KEY idx_lig_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
