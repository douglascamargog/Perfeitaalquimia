-- V15.0 — Especialistas do One: um assistente para cada setor (Vendas, Prospecção, Marketing, Formulação,
-- Cobrança, Terceirização), com instruções e consultas próprias. Editável em Administração › Especialistas do One.
CREATE TABLE IF NOT EXISTS one_especialistas (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(30)  NOT NULL UNIQUE,
  nome        VARCHAR(60)  NOT NULL,
  icone       VARCHAR(8)   NOT NULL DEFAULT '✨',
  descricao   VARCHAR(200) NOT NULL DEFAULT '',
  funcoes     VARCHAR(300) NOT NULL DEFAULT '',      -- slugs das funções que abrem direto neste especialista (vazio = todos)
  instrucoes  TEXT         NULL,                     -- o jeito de trabalhar do especialista (vai para a IA)
  consultas   VARCHAR(500) NOT NULL DEFAULT '',      -- consultas liberadas para ele (separadas por vírgula)
  sugestoes   VARCHAR(600) NOT NULL DEFAULT '',      -- exemplos de pergunta (separados por |)
  ativo       TINYINT(1)   NOT NULL DEFAULT 1,
  ordem       INT          NOT NULL DEFAULT 0,
  editado_em  DATETIME     NULL DEFAULT NULL,        -- editado pela tela: a atualização não troca as instruções
  atualizado_em DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE one_perguntas ADD COLUMN especialista VARCHAR(30) NULL DEFAULT NULL;
