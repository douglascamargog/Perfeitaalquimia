-- =====================================================================
-- 022 — Gestor(a) de setor
--   usuario_funcoes.gestor: a pessoa GERENCIA aquele setor de venda (ex.: gestora do Televendas que também vende).
--   Ela continua com a própria carteira e o próprio Meu mês e, no setor que gerencia, vê e trabalha
--   os clientes, pedidos, agenda e recompra de toda a equipe do setor.
-- Só acrescenta (regra do sistema em uso: nada é apagado).
-- =====================================================================
ALTER TABLE usuario_funcoes ADD COLUMN gestor TINYINT(1) NOT NULL DEFAULT 0;
