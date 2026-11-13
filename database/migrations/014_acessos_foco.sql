-- =====================================================================
-- 014 — Acessos por pessoa e "foco" (o que aparece por iniciativa do sistema)
--   usuario_permissoes: exceções de uma pessoa sobre o perfil (concedida = 1 dá, 0 tira)
--   funcoes.areas_foco: áreas que a função acompanha (comercial, aprovacao, cobranca, marketing, producao, diretoria)
--   usuarios.areas_foco: escolha pessoal no Meu perfil (NULL = segue as funções)
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE usuario_permissoes (
  usuario_id  INT UNSIGNED NOT NULL,
  permissao   VARCHAR(60)  NOT NULL,
  concedida   TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, permissao),
  CONSTRAINT fk_uperm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE funcoes ADD COLUMN areas_foco VARCHAR(120) NULL AFTER descricao;
ALTER TABLE usuarios ADD COLUMN areas_foco VARCHAR(120) NULL AFTER observacoes;
