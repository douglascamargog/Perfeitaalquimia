-- =====================================================================
-- 017 — Frentes de venda e Terceirização
--   funcoes.frente: a qual frente de venda a função pertence (externo, televendas, terceirizacao) — o admin muda em Funções.
--   produtos.cliente_exclusivo_id: fórmula própria de um cliente de terceirização (nunca aparece para outros clientes).
--   terceirizacao_produtos: o que cada cliente faz com a marca dele (nosso produto ou fórmula própria),
--     com o nome do rótulo, preço próprio (opcional) e o estoque de rótulos.
--   terceirizacao_rotulos_mov: entradas, consumos (ordem de produção) e ajustes de rótulos.
--   pedido_itens.terceirizacao_produto_id: linha do pedido que é o produto terceirizado do cliente.
--   ordens_producao.cliente_id: ordem fabricada para um cliente de terceirização (baixa os rótulos dele).
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE funcoes ADD COLUMN frente VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE produtos ADD COLUMN cliente_exclusivo_id INT UNSIGNED NULL DEFAULT NULL;
CREATE INDEX idx_produtos_exclusivo ON produtos (cliente_exclusivo_id);
ALTER TABLE ordens_producao ADD COLUMN cliente_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE pedido_itens ADD COLUMN terceirizacao_produto_id INT UNSIGNED NULL DEFAULT NULL;

CREATE TABLE terceirizacao_produtos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id      INT UNSIGNED NOT NULL,
  produto_id      INT UNSIGNED NOT NULL,              -- nosso produto (marca do cliente) ou o produto exclusivo dele
  nome_rotulo     VARCHAR(160) NOT NULL,              -- como o produto se chama na marca do cliente
  codigo_cliente  VARCHAR(40)  NULL,
  preco           DECIMAL(12,2) NULL,                 -- vazio = preço do produto na tabela Terceirização
  rotulos_estoque INT          NOT NULL DEFAULT 0,
  rotulos_minimo  INT          NOT NULL DEFAULT 0,
  observacoes     VARCHAR(500) NULL,
  ativo           TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_terc_cliente_produto (cliente_id, produto_id),
  KEY idx_terc_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terceirizacao_rotulos_mov (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  terceirizacao_produto_id INT UNSIGNED NOT NULL,
  tipo                     VARCHAR(10)  NOT NULL,     -- entrada, consumo, ajuste
  quantidade               INT          NOT NULL,     -- positivo entra, negativo sai
  saldo_apos               INT          NOT NULL,
  ordem_producao_id        INT UNSIGNED NULL,
  observacao               VARCHAR(255) NULL,
  usuario_id               INT UNSIGNED NULL,
  criado_em                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_terc_mov (terceirizacao_produto_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
