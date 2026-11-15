-- V11.0 · Perfil das pessoas mais pessoal e personalizável: status do dia, frase, cor e capa do perfil,
-- interesses, "pergunte-me sobre", ordem dos blocos, recados e elogios entre colegas.
-- Só acrescenta (nada é apagado).

ALTER TABLE usuario_perfil
  ADD COLUMN frase VARCHAR(140) NULL DEFAULT NULL,
  ADD COLUMN status_emoji VARCHAR(16) NULL DEFAULT NULL,
  ADD COLUMN status_texto VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN status_ate DATETIME NULL DEFAULT NULL,
  ADD COLUMN cor_perfil VARCHAR(7) NULL DEFAULT NULL,
  ADD COLUMN capa_tema VARCHAR(30) NULL DEFAULT NULL,
  ADD COLUMN interesses VARCHAR(300) NULL DEFAULT NULL,
  ADD COLUMN pergunte_sobre VARCHAR(200) NULL DEFAULT NULL,
  ADD COLUMN blocos VARCHAR(300) NULL DEFAULT NULL;

-- Recados deixados no perfil de alguém (o mural do ano todo)
CREATE TABLE IF NOT EXISTS perfil_recados (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perfil_id INT UNSIGNED NOT NULL,
  autor_id INT UNSIGNED NOT NULL,
  texto VARCHAR(500) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  oculto_em DATETIME NULL DEFAULT NULL,
  oculto_por INT UNSIGNED NULL DEFAULT NULL,
  INDEX idx_recados_perfil (perfil_id, criado_em),
  INDEX idx_recados_autor (autor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Elogios entre colegas ("Ajudou muito", "Arrasou na venda"...). Tipos em Configurações › Pessoas.
CREATE TABLE IF NOT EXISTS perfil_elogios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  para_id INT UNSIGNED NOT NULL,
  de_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  texto VARCHAR(300) NULL DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  oculto_em DATETIME NULL DEFAULT NULL,
  INDEX idx_elogios_para (para_id, criado_em),
  INDEX idx_elogios_de (de_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
