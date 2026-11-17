-- V14.0 — E-commerce completo: cópia local dos pedidos da loja Tray (loja própria + marketplaces).
-- O painel do Marketing lê daqui (rápido e sem gastar o limite de 10 mil requisições/dia da Tray).
-- LGPD: nada de nome, CPF, e-mail, telefone ou endereço do comprador. Só cidade/UF e um código anônimo
-- (cliente_hash) para saber se a pessoa já comprou antes.
CREATE TABLE IF NOT EXISTS ecommerce_pedidos (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tray_id             VARCHAR(40)   NOT NULL,
  origem              VARCHAR(10)   NOT NULL DEFAULT 'tray',   -- tray | fake
  data_hora           DATETIME      NOT NULL,
  modificado_em       DATETIME      NULL,
  status              VARCHAR(80)   NOT NULL DEFAULT '',       -- como está na Tray ("A ENVIAR", "AGUARDANDO PAGAMENTO"...)
  status_tipo         VARCHAR(12)   NOT NULL DEFAULT 'pago',   -- aguardando | pago | enviado | entregue | cancelado
  status_cor          VARCHAR(9)    NULL,
  canal               VARCHAR(20)   NOT NULL DEFAULT 'loja',   -- loja | manual | mercado_livre | shopee | amazon | magalu | americanas | shein | outro
  canal_nome          VARCHAR(80)   NULL,                      -- texto original (point_sale / marketplace_name)
  marketplace_pedido  VARCHAR(60)   NULL,
  marketplace_envio   VARCHAR(40)   NULL,                      -- tipo de envio no marketplace (ex.: me2, full)
  marketplace_status  VARCHAR(60)   NULL,
  parceiro            VARCHAR(60)   NULL,
  total               DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal            DECIMAL(12,2) NOT NULL DEFAULT 0,
  desconto            DECIMAL(12,2) NOT NULL DEFAULT 0,
  frete               DECIMAL(12,2) NOT NULL DEFAULT 0,
  custo               DECIMAL(12,2) NULL,
  cupom               VARCHAR(60)   NULL,
  itens_qtd           INT           NOT NULL DEFAULT 0,
  pagamento           VARCHAR(80)   NULL,
  pagamento_tipo      VARCHAR(20)   NULL,                      -- pix | cartao | boleto | outro
  pago_em             DATE          NULL,
  envio               VARCHAR(80)   NULL,
  transportadora      VARCHAR(80)   NULL,
  prazo_entrega       DATE          NULL,
  enviado_em          DATE          NULL,
  entregue            TINYINT(1)    NOT NULL DEFAULT 0,
  tem_nf              TINYINT(1)    NOT NULL DEFAULT 0,
  nf_numero           VARCHAR(30)   NULL,
  nf_em               DATETIME      NULL,
  cidade              VARCHAR(80)   NULL,
  uf                  CHAR(2)       NULL,
  cliente_hash        CHAR(16)      NULL,
  detalhado           TINYINT(1)    NOT NULL DEFAULT 0,       -- 1 = já leu o pedido completo
  atualizado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_eco_tray (origem, tray_id),
  KEY idx_eco_data (data_hora),
  KEY idx_eco_canal (canal, data_hora),
  KEY idx_eco_status (status_tipo, data_hora),
  KEY idx_eco_uf (uf),
  KEY idx_eco_detalhe (detalhado, modificado_em),
  KEY idx_eco_cliente (cliente_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecommerce_pedido_itens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id   INT UNSIGNED  NOT NULL,
  produto_ref VARCHAR(80)   NOT NULL DEFAULT '',   -- referência na loja (ou código do produto)
  ean         VARCHAR(20)   NULL,
  nome        VARCHAR(200)  NOT NULL,              -- sem HTML
  variacao    VARCHAR(120)  NULL,                  -- "Tamanho: 02"
  categoria   VARCHAR(80)   NULL,
  quantidade  INT           NOT NULL DEFAULT 1,
  preco       DECIMAL(12,2) NOT NULL DEFAULT 0,
  custo       DECIMAL(12,2) NULL,
  imagem      VARCHAR(300)  NULL,
  KEY idx_eco_item_pedido (pedido_id),
  KEY idx_eco_item_ref (produto_ref),
  CONSTRAINT fk_eco_item_pedido FOREIGN KEY (pedido_id) REFERENCES ecommerce_pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
