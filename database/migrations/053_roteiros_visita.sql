-- V15.2 — Planejador de visitas do vendedor externo (roteiros de viagem) e "pedido tirado por" (venda em visita de outra carteira).
-- Só acrescenta: duas tabelas novas e uma coluna nova em pedidos.

-- Roteiro de viagem: o externo escolhe as cidades do caminho (em ordem) e o One sugere as lojas que ficam perto da rota
CREATE TABLE IF NOT EXISTS roteiros (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED  NOT NULL,                        -- quem vai viajar (dono do roteiro)
  nome          VARCHAR(120)  NOT NULL,
  data_inicio   DATE          NOT NULL,
  data_fim      DATE          NOT NULL,
  cidades_json  TEXT          NULL,                            -- ids da tabela cidades, na ordem do caminho
  raio_km       SMALLINT      NOT NULL DEFAULT 15,             -- até quantos km da rota o assistente procura lojas
  visitas_dia   TINYINT       NOT NULL DEFAULT 8,              -- quantas visitas por dia (para dividir as paradas nos dias)
  situacao      VARCHAR(15)   NOT NULL DEFAULT 'planejado',    -- planejado | concluido | cancelado
  observacoes   TEXT          NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_roteiros_usuario (usuario_id, situacao, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lojas do roteiro (de qualquer carteira). A loja continua da carteira dela: só a visita é do externo.
CREATE TABLE IF NOT EXISTS roteiro_paradas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  roteiro_id    INT UNSIGNED  NOT NULL,
  cliente_id    INT UNSIGNED  NOT NULL,
  dia           DATE          NULL,                            -- dia previsto da visita
  ordem         SMALLINT      NOT NULL DEFAULT 0,              -- ordem dentro do dia (pelo caminho)
  situacao      VARCHAR(15)   NOT NULL DEFAULT 'pendente',     -- pendente | visitado | nao_atendeu
  observacao    VARCHAR(500)  NULL,
  pedido_id     INT UNSIGNED  NULL,                            -- pedido fechado na visita
  visitado_em   DATETIME      NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parada (roteiro_id, cliente_id),
  KEY idx_parada_cliente (cliente_id),
  KEY idx_parada_dia (roteiro_id, dia, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedido fechado por quem não é a dona da carteira (externo em visita do roteiro): a venda é da dona (vendedor_id),
-- e quem tirou o pedido fica aqui (vê no Meu mês, sem contar para a meta dele)
ALTER TABLE pedidos ADD COLUMN tirado_por_id INT UNSIGNED NULL DEFAULT NULL AFTER vendedor_id;
CREATE INDEX idx_pedidos_tirado_por ON pedidos (tirado_por_id, situacao);
