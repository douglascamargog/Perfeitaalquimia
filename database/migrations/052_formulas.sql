-- V15.0 — Fórmulas (área da Responsável Técnica): criar e testar fórmulas e composições.
-- A fórmula APROVADA vira a ficha técnica do produto (ficha_tecnica), usada na Produção e no custo.
CREATE TABLE IF NOT EXISTS formulas (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                VARCHAR(12)   NOT NULL,                  -- F-0001 (o mesmo em todas as versões)
  versao                INT           NOT NULL DEFAULT 1,
  formula_origem_id     INT UNSIGNED  NULL,                      -- versão anterior
  nome                  VARCHAR(150)  NOT NULL,
  forma                 VARCHAR(20)   NOT NULL DEFAULT 'capsula', -- capsula | comprimido | po | sache | liquido | cha | outro
  porcao_qtd            DECIMAL(10,3) NOT NULL DEFAULT 1,         -- ex.: 2 (cápsulas) ou 5 (g)
  porcao_unidade        VARCHAR(20)   NOT NULL DEFAULT 'cápsulas',
  porcoes_embalagem     INT           NOT NULL DEFAULT 30,
  capacidade_capsula_mg DECIMAL(10,2) NULL,                       -- só cápsula: quanto cabe em cada uma
  lote_piloto           INT           NOT NULL DEFAULT 100,       -- unidades para conferir o estoque
  objetivo              TEXT          NULL,                       -- para que é / alegação pretendida
  situacao              VARCHAR(12)   NOT NULL DEFAULT 'rascunho',-- rascunho | em_teste | aprovada | arquivada
  produto_id            INT UNSIGNED  NULL,
  dp_projeto_id         INT UNSIGNED  NULL,
  responsavel_id        INT UNSIGNED  NULL,
  aprovado_por          INT UNSIGNED  NULL,
  aprovado_em           DATETIME      NULL,
  observacoes           TEXT          NULL,
  criado_por            INT UNSIGNED  NULL,
  criado_em             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_formulas_codigo (codigo, versao),
  KEY idx_formulas_situacao (situacao),
  KEY idx_formulas_produto (produto_id),
  KEY idx_formulas_projeto (dp_projeto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_itens (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_id        INT UNSIGNED  NOT NULL,
  materia_prima_id  INT UNSIGNED  NULL,                -- vazio = ingrediente ainda não cadastrado (nome_livre)
  nome_livre        VARCHAR(150)  NULL,
  quantidade        DECIMAL(16,6) NOT NULL DEFAULT 0,
  unidade           VARCHAR(5)    NOT NULL DEFAULT 'mg', -- mcg | mg | g | ml | un
  base              VARCHAR(10)   NOT NULL DEFAULT 'porcao', -- porcao (por porção) | embalagem (por pote/frasco)
  funcao            VARCHAR(20)   NOT NULL DEFAULT 'ativo',  -- ativo | excipiente | aromatizante | edulcorante | corante | conservante | veiculo | capsula | embalagem | outro
  ordem             INT           NOT NULL DEFAULT 0,
  observacao        VARCHAR(255)  NULL,
  KEY idx_formula_itens (formula_id),
  CONSTRAINT fk_formula_itens FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_testes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_id    INT UNSIGNED  NOT NULL,
  data          DATE          NOT NULL,
  lote_tamanho  INT           NULL,
  resultado     VARCHAR(12)   NOT NULL DEFAULT 'ajustar',  -- aprovado | reprovado | ajustar
  observacoes   TEXT          NULL,
  criado_por    INT UNSIGNED  NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_formula_testes (formula_id),
  CONSTRAINT fk_formula_testes FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_historico (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_id  INT UNSIGNED  NOT NULL,
  usuario_id  INT UNSIGNED  NULL,
  texto       VARCHAR(500)  NOT NULL,
  dados_json  MEDIUMTEXT    NULL,                    -- ex.: a ficha técnica que existia antes da aprovação
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_formula_historico (formula_id),
  CONSTRAINT fk_formula_historico FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
