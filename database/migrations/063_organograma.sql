-- V17.8 — Organograma: em que área da empresa cada função aparece (Administração › Funções › "Área no organograma").
ALTER TABLE funcoes ADD COLUMN area VARCHAR(20) NULL DEFAULT NULL COMMENT 'diretoria | comercial | marketing | financeiro | operacao (organograma)';
