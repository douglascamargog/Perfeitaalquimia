-- =====================================================================
-- 013 — Notificações (sino do topo e marca "novidade" nos clientes)
--   Cada linha é um aviso para UMA pessoa: pedido para aprovar, pedido aprovado/recusado/faturado,
--   lead novo, cliente que passou para você, atualização no seu cliente feita por outra pessoa...
--   Guarda só o nome da loja, o número do pedido e o link (LGPD). Cada pessoa vê só as próprias.
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
CREATE TABLE notificacoes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED  NOT NULL,
  tipo        VARCHAR(30)   NOT NULL,               -- pedido_aprovar, pedido_aprovado, pedido_recusado, pedido_faturado, pedido_catalogo,
                                                    -- lead_novo, cliente_recebido, cliente_atualizado, post_aprovar, post_ajuste
  titulo      VARCHAR(160)  NOT NULL,
  texto       VARCHAR(300)  NULL,
  link        VARCHAR(255)  NOT NULL,
  cliente_id  INT UNSIGNED  NULL,
  pedido_id   INT UNSIGNED  NULL,
  lida_em     DATETIME      NULL,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_usuario (usuario_id, lida_em, id),
  KEY idx_notif_cliente (usuario_id, cliente_id, lida_em),
  KEY idx_notif_pedido (pedido_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
