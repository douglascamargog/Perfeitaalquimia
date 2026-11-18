-- V17.7 — Ligar os produtos que vieram só do Nota10 aos produtos que já estavam no One.
-- O produto duplicado (criado pela importação) fica guardado e inativo, apontando para o produto do One;
-- as próximas importações do Nota10 já usam o produto do One.

ALTER TABLE produtos ADD COLUMN juntado_a_id INT NULL DEFAULT NULL COMMENT 'produto do One que ficou no lugar deste (duplicado do Nota10)';
ALTER TABLE produtos ADD COLUMN juntado_em DATETIME NULL DEFAULT NULL;
CREATE INDEX idx_produtos_juntado ON produtos (juntado_a_id);

-- Cada ligação feita (para poder desfazer): o que foi movido de um produto para o outro
CREATE TABLE IF NOT EXISTS produto_juncoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origem_id INT NOT NULL COMMENT 'produto do Nota10 (duplicado)',
    destino_id INT NOT NULL COMMENT 'produto do One',
    movidos TEXT NULL COMMENT '{"tabela": [ids movidos]}',
    codigo_movido VARCHAR(40) NULL DEFAULT NULL COMMENT 'código que passou do duplicado para o produto do One',
    usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    desfeito_em DATETIME NULL DEFAULT NULL,
    KEY idx_juncao_origem (origem_id),
    KEY idx_juncao_destino (destino_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
