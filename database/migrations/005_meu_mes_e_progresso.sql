-- =====================================================================
-- 005 — Progresso na Academia, "Meu mês" (meta individual) e meta da equipe.
--       Antes, na Central de Televendas, isso ficava salvo só no navegador
--       de cada pessoa. Agora fica no servidor, por usuário.
-- =====================================================================

-- O que cada pessoa já estudou (módulos da trilha e aulas de CRM)
CREATE TABLE academia_progresso (
  usuario_id   INT UNSIGNED NOT NULL,
  item         VARCHAR(80)  NOT NULL,            -- ex.: 'modulo:empresa', 'crm:mapa'
  concluido_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, item),
  CONSTRAINT fk_prog_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meu mês: meta individual e dias de trabalho de cada pessoa, por mês
CREATE TABLE meu_mes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED  NOT NULL,
  mes         CHAR(7)       NOT NULL,            -- AAAA-MM
  meta        DECIMAL(14,2) NOT NULL DEFAULT 0,
  dias_semana VARCHAR(20)   NOT NULL DEFAULT '1,2,3,4,5', -- 0 = domingo ... 6 = sábado
  folgas      JSON          NULL,                -- lista de datas sem trabalho: ["2026-11-20", ...]
  anotacoes   TEXT          NULL,
  UNIQUE KEY uk_meu_mes (usuario_id, mes),
  CONSTRAINT fk_mm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendas que a pessoa registra no Meu mês (enquanto não há integração com o faturamento do Omie)
CREATE TABLE vendas_registradas (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED  NOT NULL,
  data       DATE          NOT NULL,
  valor      DECIMAL(14,2) NOT NULL,
  descricao  VARCHAR(200)  NULL,                 -- ex.: nome do cliente ou número do pedido
  criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vr_usuario_data (usuario_id, data),
  CONSTRAINT fk_vr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meta da EQUIPE por mês. Só o gestor (supervisor de vendas) altera.
-- vendido = total vendido pela equipe até o momento, digitado pelo gestor (sem integração com vendas ainda)
CREATE TABLE metas_equipe (
  mes            CHAR(7)       NOT NULL PRIMARY KEY, -- AAAA-MM
  meta           DECIMAL(14,2) NOT NULL DEFAULT 0,
  vendido        DECIMAL(14,2) NOT NULL DEFAULT 0,
  dias_semana    VARCHAR(20)   NOT NULL DEFAULT '1,2,3,4,5',
  folgas         JSON          NULL,                 -- feriados e dias sem expediente
  atualizado_por INT UNSIGNED  NULL,
  atualizado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_me_usuario FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de cada lançamento do vendido da equipe (mostra a evolução no mês)
CREATE TABLE metas_equipe_lancamentos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mes        CHAR(7)       NOT NULL,
  data       DATE          NOT NULL,                 -- dia a que o valor se refere
  vendido    DECIMAL(14,2) NOT NULL,                 -- total acumulado no mês até esse dia
  usuario_id INT UNSIGNED  NULL,
  criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mel_mes (mes, data),
  CONSTRAINT fk_mel_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarefas e compromissos do Meu mês usam a mesma agenda da Carteira
ALTER TABLE tarefas
  ADD COLUMN prioridade VARCHAR(10) NOT NULL DEFAULT 'normal' AFTER tipo,
  ADD COLUMN hora       TIME        NULL AFTER vence_em,
  ADD COLUMN notas      VARCHAR(1000) NULL AFTER hora;
