-- =====================================================================
-- 023 — Carteiras de clientes (Gestão › Carteiras)
--   carteiras: grupo de clientes de um setor, com um(a) vendedor(a) responsável — ou SEM vendedor(a)
--              (ex.: a pessoa saiu; os clientes ficam parados até alguém assumir).
--   clientes.carteira_id: a carteira do cliente. A carteira manda no responsável: trocar o(a) vendedor(a)
--              da carteira troca o responsável de todos os clientes dela.
-- Só acrescenta (regra do sistema em uso: nada é apagado; carteira vazia fica inativa).
-- =====================================================================
CREATE TABLE carteiras (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(120) NOT NULL,
  funcao_id       INT UNSIGNED NULL,               -- setor de venda (função)
  responsavel_id  INT UNSIGNED NULL,               -- NULL = sem vendedor(a)
  descricao       VARCHAR(255) NULL,
  ativa           TINYINT(1)   NOT NULL DEFAULT 1,
  criado_por      INT UNSIGNED NULL,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cart_funcao (funcao_id),
  KEY idx_cart_resp (responsavel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clientes ADD COLUMN carteira_id INT UNSIGNED NULL;
CREATE INDEX idx_cli_carteira ON clientes (carteira_id);

-- Importação direto para uma carteira (Gestão › Carteiras › Importar): os clientes do arquivo vão para ela
ALTER TABLE importacoes ADD COLUMN carteira_id INT UNSIGNED NULL;
