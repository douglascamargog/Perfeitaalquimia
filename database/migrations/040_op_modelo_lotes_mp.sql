-- V11.5 · Ordem de produção no modelo da empresa (PLA08.1) e lotes das matérias-primas.
-- Só acrescenta (nada é apagado).

-- Cadastro da MP: fabricante padrão e CAS/INS
ALTER TABLE materias_primas
  ADD COLUMN fabricante VARCHAR(120) NULL DEFAULT NULL,
  ADD COLUMN cas_ins VARCHAR(80) NULL DEFAULT NULL;

-- Lotes de cada matéria-prima (registrados quando a compra chega). O saldo baixa quando uma OP usa o lote.
CREATE TABLE IF NOT EXISTS mp_lotes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  materia_prima_id INT UNSIGNED  NOT NULL,
  lote             VARCHAR(60)   NOT NULL,
  fabricante       VARCHAR(120)  NULL,
  fabricacao       DATE          NULL,
  validade         DATE          NULL,
  quantidade       DECIMAL(14,3) NOT NULL DEFAULT 0,
  saldo            DECIMAL(14,3) NOT NULL DEFAULT 0,
  movimento_id     INT UNSIGNED  NULL,
  criado_por       INT UNSIGNED  NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mp_lote (materia_prima_id, lote),
  KEY idx_mp_lote_validade (materia_prima_id, validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lotes de matéria-prima usados em cada ordem de produção (rastreio)
CREATE TABLE IF NOT EXISTS ordem_mp_lotes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ordem_id         INT UNSIGNED  NOT NULL,
  materia_prima_id INT UNSIGNED  NOT NULL,
  mp_lote_id       INT UNSIGNED  NULL,
  quantidade       DECIMAL(14,3) NOT NULL DEFAULT 0,
  baixado          TINYINT(1)    NOT NULL DEFAULT 0,
  KEY idx_oml_ordem (ordem_id),
  KEY idx_oml_lote (mp_lote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados da OP do modelo
ALTER TABLE ordens_producao
  ADD COLUMN forma VARCHAR(20) NULL DEFAULT NULL,            -- capsula | pote | sache | liquido
  ADD COLUMN unidades_por_frasco INT NULL DEFAULT NULL,      -- ex.: 60 cápsulas por frasco
  ADD COLUMN amostra TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN fornecedor_mp VARCHAR(10) NOT NULL DEFAULT 'pa', -- pa | cliente (quem fornece a matéria-prima)
  ADD COLUMN entrada_producao DATE NULL DEFAULT NULL,
  ADD COLUMN aprovada_por INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN aprovada_em DATETIME NULL DEFAULT NULL;
