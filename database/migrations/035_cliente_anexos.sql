-- =====================================================================
-- 035 — Prints e arquivos na linha do tempo do cliente
--   Cada anotação pode levar imagens (print de tela colado) ou PDF. Os arquivos ficam em storage/clientes
--   (fora da web) e só saem pelo sistema, para quem pode ver o cliente (LGPD).
-- Só acrescenta.
-- =====================================================================
CREATE TABLE cliente_anexos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT UNSIGNED  NOT NULL,
  evento_id   INT UNSIGNED  NULL,
  arquivo     VARCHAR(60)   NOT NULL,
  nome        VARCHAR(200)  NOT NULL,
  mime        VARCHAR(60)   NOT NULL,
  tamanho     INT UNSIGNED  NOT NULL DEFAULT 0,
  usuario_id  INT UNSIGNED  NULL,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ca_cliente (cliente_id),
  KEY idx_ca_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
