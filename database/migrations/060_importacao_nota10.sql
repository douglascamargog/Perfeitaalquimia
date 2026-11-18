-- V17.0 — Importação de verdade do Nota10 (regras em docs/nota10/regras-importacao.md).
-- Só acrescenta. O que não tem tela no One fica nas tabelas n10_* (área separada), que nunca são apagadas.

-- Pedido: empresa emitente, vendedor antigo do Nota10, NF da bonificação/de "Outras saídas", duplicados
ALTER TABLE pedidos
  ADD COLUMN emitente_cnpj VARCHAR(14) NULL DEFAULT NULL COMMENT 'Empresa do grupo que emitiu (NULL = Perfeita Alquimia, pedidos feitos no One)',
  ADD COLUMN situacao_origem VARCHAR(25) NULL DEFAULT NULL COMMENT 'Situação real quando o pedido é de outra empresa do grupo ou um duplicado guardado',
  ADD COLUMN nota10_tipo CHAR(1) NULL DEFAULT NULL COMMENT 'tipooperacao do Nota10: V, S, O, D...',
  ADD COLUMN nota10_vendedor_codigo INT NULL DEFAULT NULL,
  ADD COLUMN nota10_vendedor_nome VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN nf_bonificacao VARCHAR(20) NULL DEFAULT NULL COMMENT 'NF própria da bonificação (pedido "gêmeo" do Nota10)',
  ADD COLUMN valor_nf DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Valor da NF quando é diferente do pedido (NF de "Outras saídas")',
  ADD COLUMN possivel_duplicado TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_pedidos_emitente ON pedidos (emitente_cnpj);
CREATE INDEX idx_pedidos_nota10_pedido ON pedidos (nota10_pedido);

ALTER TABLE pedido_itens
  ADD COLUMN quantidade_nota10 DECIMAL(14,4) NULL DEFAULT NULL COMMENT 'Quantidade exata do Nota10 (o One guarda inteiro; KG pode ter casas)';
CREATE INDEX idx_pedido_itens_nota10 ON pedido_itens (nota10_guid);

ALTER TABLE titulos_receber
  ADD COLUMN emitente_cnpj VARCHAR(14) NULL DEFAULT NULL;
CREATE INDEX idx_titulos_emitente ON titulos_receber (emitente_cnpj);

-- Fornecedores e transportadoras (o One só tinha o nome em texto)
CREATE TABLE IF NOT EXISTS fornecedores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(15) NOT NULL DEFAULT 'fornecedor' COMMENT 'fornecedor ou transportadora',
  razao_social VARCHAR(200) NOT NULL,
  nome_fantasia VARCHAR(200) NULL,
  cnpj VARCHAR(14) NULL,
  inscricao_estadual VARCHAR(20) NULL,
  endereco VARCHAR(200) NULL,
  bairro VARCHAR(120) NULL,
  cidade VARCHAR(120) NULL,
  uf CHAR(2) NULL,
  cep VARCHAR(8) NULL,
  telefone VARCHAR(40) NULL,
  email VARCHAR(250) NULL,
  contato VARCHAR(100) NULL,
  observacoes TEXT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  cliente_id INT NULL COMMENT 'Quando também é cliente',
  nota10_numero INT NULL,
  nota10_dados LONGTEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fornecedores_nota10 (nota10_numero, tipo),
  INDEX idx_fornecedores_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE compras_lancadas
  ADD COLUMN fornecedor_id INT NULL DEFAULT NULL,
  ADD COLUMN nota10_guid VARCHAR(32) NULL DEFAULT NULL;
CREATE INDEX idx_compras_lancadas_nota10 ON compras_lancadas (nota10_guid);

ALTER TABLE materias_primas
  ADD COLUMN nota10_codigo VARCHAR(30) NULL DEFAULT NULL;
CREATE INDEX idx_mp_nota10 ON materias_primas (nota10_codigo);

-- Andamento da importação (em etapas, como a leitura)
ALTER TABLE nota10_importacoes
  ADD COLUMN etapa VARCHAR(30) NULL DEFAULT NULL,
  ADD COLUMN cursor_id BIGINT NOT NULL DEFAULT 0,
  ADD COLUMN relatorio LONGTEXT NULL COMMENT 'JSON com as contagens de cada regra',
  ADD COLUMN importado_em DATETIME NULL DEFAULT NULL;
