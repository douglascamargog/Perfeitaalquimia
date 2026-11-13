-- =====================================================================
-- 020 — Produtos completo (parte técnica, embalagens e documentos)
--   materias_primas.tipo: 'materia_prima' ou 'embalagem' (pote, tampa, rótulo, lacre, caixa...).
--   produto_tecnico: especificações do produto (EAN, NCM, Anvisa, pesos, validade, armazenamento...),
--                    preenchidas pela Responsável Técnica.
--   produto_documentos: laudos, FISPQ, registros, certificados e artes. O arquivo fica FORA da pasta
--                       pública (storage/documentos/produtos) e só é baixado pelo sistema.
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE materias_primas ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'materia_prima' AFTER unidade;

CREATE TABLE produto_tecnico (
  produto_id      INT UNSIGNED PRIMARY KEY,
  ean             VARCHAR(20)   NULL,
  ncm             VARCHAR(12)   NULL,
  registro_anvisa VARCHAR(80)   NULL,     -- número do registro ou "Dispensado de registro (RDC ...)"
  validade_meses  SMALLINT UNSIGNED NULL,
  peso_liquido    VARCHAR(40)   NULL,     -- ex.: "60 g"
  peso_bruto      VARCHAR(40)   NULL,
  dimensoes       VARCHAR(60)   NULL,     -- ex.: "5 x 5 x 10 cm"
  unidades_caixa  SMALLINT UNSIGNED NULL,
  armazenamento   VARCHAR(500)  NULL,
  alergenicos     VARCHAR(500)  NULL,
  avisos          VARCHAR(1000) NULL,     -- avisos obrigatórios do rótulo
  observacoes     TEXT          NULL,
  atualizado_por  INT UNSIGNED  NULL,
  atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pt_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produto_documentos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produto_id     INT UNSIGNED  NOT NULL,
  tipo           VARCHAR(30)   NOT NULL DEFAULT 'outro',  -- laudo | fispq | anvisa | certificado | rotulo | outro
  titulo         VARCHAR(160)  NOT NULL,
  arquivo        VARCHAR(120)  NOT NULL,                  -- nome aleatório dentro de storage/documentos/produtos
  nome_original  VARCHAR(200)  NOT NULL,
  mime           VARCHAR(60)   NOT NULL,
  tamanho        INT UNSIGNED  NOT NULL DEFAULT 0,
  validade       DATE          NULL,
  interno        TINYINT(1)    NOT NULL DEFAULT 0,        -- 1 = só técnica, produção e admin
  enviado_por    INT UNSIGNED  NULL,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  arquivado_em   DATETIME      NULL,                      -- "remover" só arquiva (o arquivo continua guardado)
  KEY idx_pd_produto (produto_id, arquivado_em),
  KEY idx_pd_validade (validade),
  CONSTRAINT fk_pd_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
