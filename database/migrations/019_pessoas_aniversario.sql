-- =====================================================================
-- 019 — Perfil das pessoas ("tipo rede social") e mural de aniversário
--   usuario_perfil: o que a pessoa conta sobre ela (preenchido em Meu perfil › Meu perfil público).
--   aniversario_mensagens: os parabéns dos colegas no dia (um mural por aniversário: ano).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE usuario_perfil (
  usuario_id     INT UNSIGNED PRIMARY KEY,
  sobre_mim      VARCHAR(600) NULL,
  hobbies        VARCHAR(300) NULL,
  comida         VARCHAR(120) NULL,
  musica         VARCHAR(120) NULL,
  filme_serie    VARCHAR(120) NULL,
  time_futebol   VARCHAR(80)  NULL,
  lugar_favorito VARCHAR(120) NULL,
  familia        VARCHAR(300) NULL,
  pets           VARCHAR(200) NULL,
  pet_foto       VARCHAR(255) NULL,
  capa           VARCHAR(255) NULL,
  instagram      VARCHAR(160) NULL,
  linkedin       VARCHAR(200) NULL,
  atualizado_em  DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aniversario_mensagens (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  aniversariante_id INT UNSIGNED NOT NULL,
  ano               SMALLINT     NOT NULL,
  autor_id          INT UNSIGNED NOT NULL,
  texto             VARCHAR(500) NOT NULL,
  criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aniv_mural (aniversariante_id, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
