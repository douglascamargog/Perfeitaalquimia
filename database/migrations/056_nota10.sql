-- V16.2 — Preparação para receber os dados do Nota10 (ERP antigo) sem perder nenhuma informação.
-- Só acrescenta: campos novos com valor padrão vazio, tabelas novas e índices.
-- Cada registro importado guarda o código do Nota10, para dar para importar de novo sem duplicar.

-- ---------- Cliente ----------
ALTER TABLE clientes
  ADD COLUMN nota10_numero INT NULL DEFAULT NULL COMMENT 'Número do cliente no Nota10',
  ADD COLUMN nota10_guid VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN end_numero VARCHAR(20) NULL DEFAULT NULL COMMENT 'Número do endereço',
  ADD COLUMN end_complemento VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN inscricao_estadual VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN inscricao_municipal VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN contribuinte VARCHAR(20) NULL DEFAULT NULL COMMENT 'Contribuinte do ICMS: contribuinte, isento ou nao_contribuinte',
  ADD COLUMN suframa VARCHAR(15) NULL DEFAULT NULL,
  ADD COLUMN celular2 VARCHAR(30) NULL DEFAULT NULL,
  ADD COLUMN contato_nome VARCHAR(60) NULL DEFAULT NULL COMMENT 'Pessoa de contato principal',
  ADD COLUMN email_nfe VARCHAR(250) NULL DEFAULT NULL,
  ADD COLUMN email_financeiro VARCHAR(250) NULL DEFAULT NULL,
  ADD COLUMN data_fundacao DATE NULL DEFAULT NULL COMMENT 'Fundação da empresa (ou nascimento, se pessoa física)',
  ADD COLUMN cliente_desde DATE NULL DEFAULT NULL COMMENT 'Data do cadastro no ERP',
  ADD COLUMN limite_credito DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN valor_atraso DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Em atraso segundo o ERP (calculado pelos títulos depois da importação)',
  ADD COLUMN dias_atraso INT NULL DEFAULT NULL,
  ADD COLUMN desconto_padrao DECIMAL(6,2) NULL DEFAULT NULL COMMENT '% de desconto padrão do cliente',
  ADD COLUMN comissao_pct DECIMAL(6,2) NULL DEFAULT NULL COMMENT '% de comissão do vendedor neste cliente',
  ADD COLUMN transportadora_preferida VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN matriz_id INT NULL DEFAULT NULL COMMENT 'Loja matriz (rede): filiais apontam para a matriz',
  ADD COLUMN situacao_erp VARCHAR(30) NULL DEFAULT NULL COMMENT 'Situação no ERP (ativo, inativo, bloqueado...)',
  ADD COLUMN motivo_inativo VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN entrega_razao VARCHAR(100) NULL DEFAULT NULL COMMENT 'Endereço de entrega (quando é diferente do cadastro)',
  ADD COLUMN entrega_cnpj VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN entrega_endereco VARCHAR(150) NULL DEFAULT NULL,
  ADD COLUMN entrega_numero VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN entrega_complemento VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN entrega_bairro VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN entrega_cidade VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN entrega_uf CHAR(2) NULL DEFAULT NULL,
  ADD COLUMN entrega_cep VARCHAR(9) NULL DEFAULT NULL,
  ADD COLUMN nota10_dados LONGTEXT NULL DEFAULT NULL COMMENT 'Registro original do Nota10 (JSON), para consulta: nada se perde';
CREATE INDEX idx_clientes_nota10 ON clientes (nota10_numero);
CREATE INDEX idx_clientes_matriz ON clientes (matriz_id);

-- ---------- Pedido / nota fiscal ----------
ALTER TABLE pedidos
  ADD COLUMN nota10_guid VARCHAR(32) NULL DEFAULT NULL COMMENT 'GUID da nota/pedido no Nota10',
  ADD COLUMN nota10_pedido VARCHAR(20) NULL DEFAULT NULL COMMENT 'Número do pedido no Nota10',
  ADD COLUMN nf_serie VARCHAR(5) NULL DEFAULT NULL,
  ADD COLUMN nf_chave VARCHAR(44) NULL DEFAULT NULL COMMENT 'Chave de acesso da NF-e',
  ADD COLUMN nf_situacao VARCHAR(20) NULL DEFAULT NULL COMMENT 'autorizada, cancelada, denegada...',
  ADD COLUMN emitido_em DATETIME NULL DEFAULT NULL COMMENT 'Emissão da nota',
  ADD COLUMN saida_em DATE NULL DEFAULT NULL,
  ADD COLUMN entrega_em DATE NULL DEFAULT NULL,
  ADD COLUMN cfop VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN natureza_operacao VARCHAR(80) NULL DEFAULT NULL,
  ADD COLUMN tipo_operacao VARCHAR(20) NULL DEFAULT NULL COMMENT 'venda, bonificacao, devolucao, remessa, outra (pelo CFOP)',
  ADD COLUMN frete_valor DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN frete_por VARCHAR(20) NULL DEFAULT NULL COMMENT 'emitente, destinatario, terceiros, sem_frete',
  ADD COLUMN valor_icms DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN valor_st DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN valor_ipi DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN obs_interna TEXT NULL DEFAULT NULL,
  ADD COLUMN romaneio_numero INT NULL DEFAULT NULL;
CREATE INDEX idx_pedidos_nota10 ON pedidos (nota10_guid);
CREATE INDEX idx_pedidos_tipo_operacao ON pedidos (tipo_operacao);

-- ---------- Item do pedido ----------
ALTER TABLE pedido_itens
  ADD COLUMN custo_unitario DECIMAL(12,4) NULL DEFAULT NULL COMMENT 'Custo do produto na data da venda (margem)',
  ADD COLUMN desconto_valor DECIMAL(12,2) NULL DEFAULT NULL,
  ADD COLUMN lote VARCHAR(30) NULL DEFAULT NULL,
  ADD COLUMN validade DATE NULL DEFAULT NULL,
  ADD COLUMN fabricacao DATE NULL DEFAULT NULL,
  ADD COLUMN ncm VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN cfop VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN vendedor_id INT NULL DEFAULT NULL COMMENT 'Quando o item tem vendedor diferente do pedido',
  ADD COLUMN nota10_guid VARCHAR(32) NULL DEFAULT NULL;
CREATE INDEX idx_pedido_itens_lote ON pedido_itens (lote);

-- ---------- Produto ----------
-- (código de barras, NCM, peso e validade já ficam em produto_tecnico; estoque mínimo em estoque_acabado)
ALTER TABLE produtos
  ADD COLUMN nota10_codigo VARCHAR(30) NULL DEFAULT NULL,
  ADD COLUMN unidade VARCHAR(6) NULL DEFAULT NULL,
  ADD COLUMN cest VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN custo DECIMAL(12,4) NULL DEFAULT NULL COMMENT 'Custo atual (ERP)',
  ADD COLUMN grupo_erp VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN subgrupo_erp VARCHAR(60) NULL DEFAULT NULL,
  ADD COLUMN cadastrado_erp_em DATE NULL DEFAULT NULL;
CREATE INDEX idx_produtos_nota10 ON produtos (nota10_codigo);

-- ---------- Contatos da loja (várias pessoas por cliente) ----------
CREATE TABLE IF NOT EXISTS cliente_contatos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  nome VARCHAR(80) NOT NULL,
  cargo VARCHAR(80) NULL COMMENT 'Função ou observação (ex.: compradora, dona)',
  telefone VARCHAR(35) NULL,
  celular VARCHAR(35) NULL,
  email VARCHAR(250) NULL,
  aniversario_dia TINYINT NULL,
  aniversario_mes TINYINT NULL,
  origem VARCHAR(20) NOT NULL DEFAULT 'one' COMMENT 'one ou nota10',
  nota10_seq INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contatos_cliente (cliente_id),
  INDEX idx_contatos_aniversario (aniversario_mes, aniversario_dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Títulos a receber (financeiro do cliente) ----------
CREATE TABLE IF NOT EXISTS titulos_receber (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NULL,
  pedido_id INT NULL,
  documento VARCHAR(40) NULL,
  parcela VARCHAR(10) NULL,
  nosso_numero VARCHAR(30) NULL,
  forma_pagamento VARCHAR(40) NULL,
  historico VARCHAR(255) NULL,
  lancado_em DATE NULL,
  vencimento DATE NULL,
  pago_em DATE NULL,
  cancelado_em DATE NULL,
  valor DECIMAL(12,2) NOT NULL DEFAULT 0,
  desconto DECIMAL(12,2) NOT NULL DEFAULT 0,
  acrescimo DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Juros e multa',
  valor_pago DECIMAL(12,2) NULL,
  situacao VARCHAR(12) NOT NULL DEFAULT 'aberto' COMMENT 'aberto, pago, cancelado',
  origem VARCHAR(20) NOT NULL DEFAULT 'nota10' COMMENT 'nota10 ou omie',
  nota10_guid VARCHAR(32) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_titulos_nota10 (nota10_guid),
  INDEX idx_titulos_cliente (cliente_id, situacao),
  INDEX idx_titulos_vencimento (situacao, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Importação do Nota10 ----------
-- O backup é lido para tabelas com o prefixo n10_ (área separada, não mexe nos dados do One).
CREATE TABLE IF NOT EXISTS nota10_importacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arquivo VARCHAR(255) NOT NULL COMMENT 'Arquivo .sql em storage/importacoes/nota10',
  nome_original VARCHAR(255) NULL,
  tamanho BIGINT NOT NULL DEFAULT 0,
  posicao BIGINT NOT NULL DEFAULT 0 COMMENT 'Até onde o arquivo já foi lido (bytes)',
  situacao VARCHAR(12) NOT NULL DEFAULT 'enviado' COMMENT 'enviado, lendo, lido, erro',
  charset VARCHAR(20) NULL COMMENT 'Codificação do backup (SET NAMES do arquivo)',
  comandos INT NOT NULL DEFAULT 0,
  ignorados INT NOT NULL DEFAULT 0,
  erro TEXT NULL,
  criado_por INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lido_em DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De-para escolhido na conferência: vendedor do Nota10 → pessoa do One, tabela de preço, forma de pagamento, segmento, tipo de cadastro
CREATE TABLE IF NOT EXISTS nota10_mapas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(20) NOT NULL COMMENT 'vendedor, tabela, forma, segmento, tipo_cadastro',
  codigo VARCHAR(40) NOT NULL COMMENT 'Código no Nota10',
  nome VARCHAR(120) NULL COMMENT 'Nome no Nota10 (para mostrar)',
  destino VARCHAR(40) NULL COMMENT 'id no One (ou a escolha, ex.: cliente/fornecedor/transportadora/ignorar)',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_nota10_mapa (tipo, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
