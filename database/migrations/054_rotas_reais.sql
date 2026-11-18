-- V15.5 — Rotas pelas estradas no Planejador de visitas (Geoapify): trajeto, km e tempo do roteiro e de cada dia.
-- Só acrescenta colunas (vazias = cálculo em linha reta, como antes).
ALTER TABLE roteiros ADD COLUMN trajeto_json MEDIUMTEXT NULL DEFAULT NULL;   -- pontos [lat, lng] do trajeto pelas estradas (cidade a cidade)
ALTER TABLE roteiros ADD COLUMN km_total DECIMAL(8,1) NULL DEFAULT NULL;     -- km do trajeto entre as cidades
ALTER TABLE roteiros ADD COLUMN minutos_total INT NULL DEFAULT NULL;         -- tempo dirigindo entre as cidades
ALTER TABLE roteiros ADD COLUMN dias_json TEXT NULL DEFAULT NULL;            -- {"2026-12-01": {"km": 186.4, "minutos": 220}} das visitas de cada dia
ALTER TABLE roteiros ADD COLUMN rota_aviso VARCHAR(255) NULL DEFAULT NULL;   -- por que a rota real não foi calculada (sem chave, cota...)
