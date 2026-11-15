-- V11.2 · Depósito: caixas e embalagens usadas para embalar cada pedido (dão baixa no estoque do item).
-- Só acrescenta (nada é apagado).
CREATE TABLE IF NOT EXISTS pedido_embalagens (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id        INT UNSIGNED  NOT NULL,
  materia_prima_id INT UNSIGNED  NOT NULL,
  quantidade       DECIMAL(12,3) NOT NULL DEFAULT 0,
  usuario_id       INT UNSIGNED  NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em    DATETIME      NULL DEFAULT NULL,
  UNIQUE KEY uq_pedido_embalagem (pedido_id, materia_prima_id),
  KEY idx_embalagem_item (materia_prima_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
