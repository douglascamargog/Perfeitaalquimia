-- V11.7 · Tabela nutricional de cada produto (modelo Anvisa), editada na ficha técnica e baixada em PNG ou PDF.
-- Só acrescenta (nada é apagado).
CREATE TABLE IF NOT EXISTS produto_nutricional (
  produto_id         INT UNSIGNED  NOT NULL PRIMARY KEY,
  porcoes_embalagem  VARCHAR(40)   NULL,          -- ex.: 30
  porcao             VARCHAR(80)   NULL,          -- ex.: 1,1 g (2 cápsulas)
  porcao_coluna      VARCHAR(20)   NULL,          -- cabeçalho da coluna (ex.: 1,1 g)
  linhas             TEXT          NULL,          -- JSON: [{"nutriente","unidade","quantidade","vd"}]  (vd vazio = calcula pela Anvisa)
  nota               TEXT          NULL,          -- "Não contém quantidades significativas de ..."
  atualizado_por     INT UNSIGNED  NULL,
  atualizado_em      DATETIME      NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
