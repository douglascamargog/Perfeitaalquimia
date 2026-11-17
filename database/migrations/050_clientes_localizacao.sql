-- V14.2 — Localização exata das lojas no mapa da Carteira (Google Maps: endereço → coordenada).
-- lat/lng já existiam (vazio = ponto aproximado pela cidade). Aqui: como a coordenada foi obtida e de qual endereço.
ALTER TABLE clientes
  ADD COLUMN geo_precisao VARCHAR(20) NULL DEFAULT NULL,  -- telhado | rua | regiao | manual | nao_encontrado
  ADD COLUMN geo_hash     CHAR(40)    NULL DEFAULT NULL,  -- endereço usado (muda o endereço → localiza de novo)
  ADD COLUMN geo_em       DATETIME    NULL DEFAULT NULL,
  ADD COLUMN geo_manual   TINYINT(1)  NOT NULL DEFAULT 0;  -- 1 = ajustado à mão no mapa (o automático não mexe)
