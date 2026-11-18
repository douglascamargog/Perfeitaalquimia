-- Estrutura do banco do One (V16.8): só as tabelas, SEM dados. Gerado de um banco de teste.
-- Para entender os campos; a fonte da verdade são as migrations em database/migrations/.
/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `acabado_movimentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(10) unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `saldo` int(11) NOT NULL,
  `referencia` varchar(80) DEFAULT NULL,
  `ordem_id` int(10) unsigned DEFAULT NULL,
  `pedido_id` int(10) unsigned DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_acm_produto` (`produto_id`,`criado_em`),
  KEY `idx_acm_tipo` (`tipo`,`criado_em`),
  KEY `idx_acm_pedido` (`pedido_id`),
  CONSTRAINT `fk_acm_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `academia_conteudo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(60) NOT NULL,
  `conteudo_json` longtext NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `academia_progresso` (
  `usuario_id` int(10) unsigned NOT NULL,
  `item` varchar(80) NOT NULL,
  `concluido_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`,`item`),
  CONSTRAINT `fk_prog_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aniversario_mensagens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aniversariante_id` int(10) unsigned NOT NULL,
  `ano` smallint(6) NOT NULL,
  `autor_id` int(10) unsigned NOT NULL,
  `texto` varchar(500) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aniv_mural` (`aniversariante_id`,`ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `entidade` varchar(40) NOT NULL,
  `entidade_id` int(10) unsigned DEFAULT NULL,
  `acao` varchar(40) NOT NULL,
  `antes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`antes_json`)),
  `depois_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`depois_json`)),
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aud_entidade` (`entidade`,`entidade_id`),
  KEY `idx_aud_usuario` (`usuario_id`),
  KEY `idx_aud_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `buscas_contato` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `provedor` varchar(20) NOT NULL,
  `situacao` varchar(10) NOT NULL,
  `resultado_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resultado_json`)),
  `mensagem` varchar(255) DEFAULT NULL,
  `buscas_web` int(11) NOT NULL DEFAULT 0,
  `tokens_entrada` int(11) NOT NULL DEFAULT 0,
  `tokens_saida` int(11) NOT NULL DEFAULT 0,
  `custo_estimado` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_busca_cliente` (`cliente_id`,`criado_em`),
  KEY `idx_busca_data` (`criado_em`),
  KEY `fk_busca_usuario` (`usuario_id`),
  CONSTRAINT `fk_busca_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_busca_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campanha_faixas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campanha_id` int(10) unsigned NOT NULL,
  `a_partir_de` decimal(12,2) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `desconto_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `brinde` varchar(120) DEFAULT NULL,
  `brinde_midia_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cf_campanha` (`campanha_id`),
  CONSTRAINT `fk_cf_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `campanhas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campanha_produtos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campanha_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned NOT NULL,
  `desconto_pct` decimal(5,2) NOT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cp` (`campanha_id`,`produto_id`),
  KEY `idx_cp_produto` (`produto_id`),
  CONSTRAINT `fk_cp_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `campanhas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campanhas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `titulo` varchar(120) DEFAULT NULL,
  `chamada` varchar(255) DEFAULT NULL,
  `selo` varchar(30) DEFAULT NULL,
  `inicio` date NOT NULL,
  `fim` date NOT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `publico` varchar(20) NOT NULL DEFAULT 'todos',
  `publico_valores` varchar(500) DEFAULT NULL,
  `soma_desconto_vendedor` tinyint(1) NOT NULL DEFAULT 1,
  `cor_primaria` char(7) DEFAULT NULL,
  `cor_destaque` char(7) DEFAULT NULL,
  `tema` varchar(10) NOT NULL DEFAULT 'claro',
  `banner_midia_id` int(10) unsigned DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_camp_periodo` (`ativa`,`inicio`,`fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `carteiras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `funcao_id` int(10) unsigned DEFAULT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_funcao` (`funcao_id`),
  KEY `idx_cart_resp` (`responsavel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cidades` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `uf` char(2) NOT NULL,
  `cod_ibge` int(10) unsigned DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cidade` (`uf`,`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_acessos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `codigo_hash` varchar(255) DEFAULT NULL,
  `codigo_expira_em` datetime DEFAULT NULL,
  `tentativas` int(11) NOT NULL DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `ultimo_acesso_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `fk_acesso_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_anexos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `arquivo` varchar(60) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `mime` varchar(60) NOT NULL,
  `tamanho` int(10) unsigned NOT NULL DEFAULT 0,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ca_cliente` (`cliente_id`),
  KEY `idx_ca_evento` (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_contatos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `cargo` varchar(80) DEFAULT NULL COMMENT 'Função ou observação (ex.: compradora, dona)',
  `telefone` varchar(35) DEFAULT NULL,
  `celular` varchar(35) DEFAULT NULL,
  `email` varchar(250) DEFAULT NULL,
  `aniversario_dia` tinyint(4) DEFAULT NULL,
  `aniversario_mes` tinyint(4) DEFAULT NULL,
  `origem` varchar(20) NOT NULL DEFAULT 'one' COMMENT 'one ou nota10',
  `nota10_seq` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contatos_cliente` (`cliente_id`),
  KEY `idx_contatos_aniversario` (`aniversario_mes`,`aniversario_dia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `canal` varchar(30) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_json`)),
  `ocorrido_em` datetime NOT NULL DEFAULT current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  `registrado_em` datetime DEFAULT current_timestamp(),
  `editado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ev_cliente` (`cliente_id`,`ocorrido_em`),
  KEY `idx_ev_data` (`ocorrido_em`),
  KEY `idx_ev_usuario_data` (`usuario_id`,`ocorrido_em`,`tipo`),
  CONSTRAINT `fk_ev_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ev_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(10) NOT NULL DEFAULT 'lead',
  `razao_social` varchar(200) NOT NULL,
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `cnpj` char(14) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `bairro` varchar(120) DEFAULT NULL,
  `cep` char(8) DEFAULT NULL,
  `cidade` varchar(120) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `cidade_id` int(10) unsigned DEFAULT NULL,
  `regiao_id` int(10) unsigned DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `segmento_id` int(10) unsigned DEFAULT NULL,
  `segmento_original` varchar(120) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `instagram` varchar(120) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `site` varchar(255) DEFAULT NULL,
  `mix_produtos` text DEFAULT NULL,
  `fonte_url` varchar(500) DEFAULT NULL,
  `validacao` varchar(255) DEFAULT NULL,
  `tabela_preco_id` int(10) unsigned DEFAULT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `funcao_id` int(10) unsigned DEFAULT NULL,
  `etapa_id` int(10) unsigned DEFAULT NULL,
  `prioridade` varchar(10) NOT NULL DEFAULT 'Média',
  `contatado` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_contato_em` date DEFAULT NULL,
  `origem_id` int(10) unsigned DEFAULT NULL,
  `origem_detalhe` varchar(160) DEFAULT NULL,
  `utm_source` varchar(120) DEFAULT NULL,
  `utm_medium` varchar(120) DEFAULT NULL,
  `utm_campaign` varchar(120) DEFAULT NULL,
  `utm_term` varchar(120) DEFAULT NULL,
  `utm_content` varchar(120) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `ultima_compra_em` date DEFAULT NULL,
  `intervalo_medio_dias` int(11) DEFAULT NULL,
  `credito_situacao` varchar(10) NOT NULL DEFAULT 'ok',
  `omie_codigo` varchar(40) DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `excluido_em` datetime DEFAULT NULL,
  `carteira_id` int(10) unsigned DEFAULT NULL,
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  `geo_precisao` varchar(20) DEFAULT NULL,
  `geo_hash` char(40) DEFAULT NULL,
  `geo_em` datetime DEFAULT NULL,
  `geo_manual` tinyint(1) NOT NULL DEFAULT 0,
  `nota10_numero` int(11) DEFAULT NULL COMMENT 'Número do cliente no Nota10',
  `nota10_guid` varchar(32) DEFAULT NULL,
  `end_numero` varchar(20) DEFAULT NULL COMMENT 'Número do endereço',
  `end_complemento` varchar(60) DEFAULT NULL,
  `inscricao_estadual` varchar(20) DEFAULT NULL,
  `inscricao_municipal` varchar(20) DEFAULT NULL,
  `contribuinte` varchar(20) DEFAULT NULL COMMENT 'Contribuinte do ICMS: contribuinte, isento ou nao_contribuinte',
  `suframa` varchar(15) DEFAULT NULL,
  `celular2` varchar(30) DEFAULT NULL,
  `contato_nome` varchar(60) DEFAULT NULL COMMENT 'Pessoa de contato principal',
  `email_nfe` varchar(250) DEFAULT NULL,
  `email_financeiro` varchar(250) DEFAULT NULL,
  `data_fundacao` date DEFAULT NULL COMMENT 'Fundação da empresa (ou nascimento, se pessoa física)',
  `cliente_desde` date DEFAULT NULL COMMENT 'Data do cadastro no ERP',
  `limite_credito` decimal(12,2) DEFAULT NULL,
  `valor_atraso` decimal(12,2) DEFAULT NULL COMMENT 'Em atraso segundo o ERP (calculado pelos títulos depois da importação)',
  `dias_atraso` int(11) DEFAULT NULL,
  `desconto_padrao` decimal(6,2) DEFAULT NULL COMMENT '% de desconto padrão do cliente',
  `comissao_pct` decimal(6,2) DEFAULT NULL COMMENT '% de comissão do vendedor neste cliente',
  `transportadora_preferida` varchar(100) DEFAULT NULL,
  `matriz_id` int(11) DEFAULT NULL COMMENT 'Loja matriz (rede): filiais apontam para a matriz',
  `situacao_erp` varchar(30) DEFAULT NULL COMMENT 'Situação no ERP (ativo, inativo, bloqueado...)',
  `motivo_inativo` varchar(100) DEFAULT NULL,
  `entrega_razao` varchar(100) DEFAULT NULL COMMENT 'Endereço de entrega (quando é diferente do cadastro)',
  `entrega_cnpj` varchar(20) DEFAULT NULL,
  `entrega_endereco` varchar(150) DEFAULT NULL,
  `entrega_numero` varchar(20) DEFAULT NULL,
  `entrega_complemento` varchar(60) DEFAULT NULL,
  `entrega_bairro` varchar(60) DEFAULT NULL,
  `entrega_cidade` varchar(80) DEFAULT NULL,
  `entrega_uf` char(2) DEFAULT NULL,
  `entrega_cep` varchar(9) DEFAULT NULL,
  `nota10_dados` longtext DEFAULT NULL COMMENT 'Registro original do Nota10 (JSON), para consulta: nada se perde',
  `entrega_revisar` varchar(500) DEFAULT NULL COMMENT 'Trecho da observação que parece endereço de entrega: alguém confere e confirma',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cliente_cnpj` (`cnpj`),
  KEY `idx_cli_uf_cidade` (`uf`,`cidade`),
  KEY `idx_cli_cidade` (`cidade_id`),
  KEY `idx_cli_segmento` (`segmento_id`),
  KEY `idx_cli_responsavel` (`responsavel_id`),
  KEY `idx_cli_funcao` (`funcao_id`),
  KEY `idx_cli_etapa` (`etapa_id`),
  KEY `idx_cli_prioridade` (`prioridade`),
  KEY `idx_cli_ultima_compra` (`ultima_compra_em`),
  KEY `idx_cli_regiao` (`regiao_id`),
  KEY `fk_cli_tabela` (`tabela_preco_id`),
  KEY `fk_cli_origem` (`origem_id`),
  KEY `idx_cli_carteira` (`carteira_id`),
  KEY `idx_cli_carteira_compra` (`carteira_id`,`excluido_em`,`tipo`,`ultima_compra_em`),
  KEY `idx_cli_resp_compra` (`responsavel_id`,`ultima_compra_em`),
  KEY `idx_clientes_nota10` (`nota10_numero`),
  KEY `idx_clientes_matriz` (`matriz_id`),
  FULLTEXT KEY `ft_cli_nome` (`razao_social`,`nome_fantasia`),
  CONSTRAINT `fk_cli_cidade` FOREIGN KEY (`cidade_id`) REFERENCES `cidades` (`id`),
  CONSTRAINT `fk_cli_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `funil_etapas` (`id`),
  CONSTRAINT `fk_cli_funcao` FOREIGN KEY (`funcao_id`) REFERENCES `funcoes` (`id`),
  CONSTRAINT `fk_cli_origem` FOREIGN KEY (`origem_id`) REFERENCES `origens` (`id`),
  CONSTRAINT `fk_cli_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regioes` (`id`),
  CONSTRAINT `fk_cli_resp` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cli_segmento` FOREIGN KEY (`segmento_id`) REFERENCES `segmentos` (`id`),
  CONSTRAINT `fk_cli_tabela` FOREIGN KEY (`tabela_preco_id`) REFERENCES `tabelas_preco` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranca_etapas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `cor` varchar(7) NOT NULL DEFAULT '#94a3b8',
  `tipo` varchar(20) NOT NULL DEFAULT 'aberta',
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobranca_titulos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cobranca_id` int(10) unsigned NOT NULL,
  `documento` varchar(40) DEFAULT NULL,
  `parcela` varchar(10) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `vencimento` date NOT NULL,
  `pago_em` date DEFAULT NULL,
  `omie_codigo` varchar(40) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tit_omie` (`omie_codigo`),
  KEY `idx_tit_cobranca` (`cobranca_id`,`pago_em`),
  CONSTRAINT `fk_tit_cobranca` FOREIGN KEY (`cobranca_id`) REFERENCES `cobrancas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cobrancas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `etapa_id` int(10) unsigned NOT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `valor_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vencimento_mais_antigo` date DEFAULT NULL,
  `promessa_em` date DEFAULT NULL,
  `promessa_valor` decimal(12,2) DEFAULT NULL,
  `tentativas_sem_sucesso` int(11) NOT NULL DEFAULT 0,
  `ultimo_contato_em` datetime DEFAULT NULL,
  `origem` varchar(10) NOT NULL DEFAULT 'manual',
  `situacao` varchar(10) NOT NULL DEFAULT 'aberta',
  `motivo_encerramento` varchar(80) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `encerrada_em` datetime DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cob_situacao_etapa` (`situacao`,`etapa_id`),
  KEY `idx_cob_responsavel` (`responsavel_id`,`situacao`),
  KEY `idx_cob_cliente` (`cliente_id`,`situacao`),
  KEY `idx_cob_vencimento` (`vencimento_mais_antigo`),
  KEY `idx_cob_promessa` (`promessa_em`),
  KEY `fk_cob_etapa` (`etapa_id`),
  CONSTRAINT `fk_cob_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_cob_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `cobranca_etapas` (`id`),
  CONSTRAINT `fk_cob_resp` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compra_cotacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `compra_id` int(10) unsigned NOT NULL,
  `fornecedor` varchar(160) NOT NULL,
  `valor_unitario` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `frete` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `prazo_dias` int(11) DEFAULT NULL,
  `condicao` varchar(120) DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  `arquivo` varchar(80) DEFAULT NULL,
  `arquivo_nome` varchar(200) DEFAULT NULL,
  `arquivo_mime` varchar(60) DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cot_compra` (`compra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compra_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `compra_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `texto` varchar(600) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ch_compra` (`compra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(15) NOT NULL DEFAULT 'avulso',
  `titulo` varchar(160) NOT NULL,
  `materia_prima_id` int(10) unsigned DEFAULT NULL,
  `dp_projeto_id` int(10) unsigned DEFAULT NULL,
  `quantidade` decimal(14,3) NOT NULL DEFAULT 1.000,
  `unidade` varchar(10) NOT NULL DEFAULT 'un',
  `motivo` text DEFAULT NULL,
  `necessario_ate` date DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'rascunho',
  `aprovador_id` int(10) unsigned DEFAULT NULL,
  `enviado_em` datetime DEFAULT NULL,
  `decidido_em` datetime DEFAULT NULL,
  `decisao_comentario` varchar(500) DEFAULT NULL,
  `cotacao_id` int(10) unsigned DEFAULT NULL,
  `escolha_motivo` varchar(300) DEFAULT NULL,
  `comprado_em` datetime DEFAULT NULL,
  `previsto_em` date DEFAULT NULL,
  `recebido_em` datetime DEFAULT NULL,
  `recebido_por` int(10) unsigned DEFAULT NULL,
  `recebido_qtd` decimal(14,3) DEFAULT NULL,
  `solicitado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_compra_situacao` (`situacao`),
  KEY `idx_compra_aprovador` (`aprovador_id`,`situacao`),
  KEY `idx_compra_mp` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras_lancadas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `origem` varchar(10) NOT NULL DEFAULT 'manual',
  `situacao` varchar(10) NOT NULL DEFAULT 'lancado',
  `numero_nf` varchar(30) DEFAULT NULL,
  `serie` varchar(10) DEFAULT NULL,
  `fornecedor` varchar(160) NOT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `emissao` date NOT NULL,
  `valor_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `omie_codigo` varchar(40) DEFAULT NULL,
  `compra_id` int(10) unsigned DEFAULT NULL,
  `arquivo` varchar(60) DEFAULT NULL,
  `arquivo_nome` varchar(200) DEFAULT NULL,
  `arquivo_mime` varchar(80) DEFAULT NULL,
  `observacoes` varchar(500) DEFAULT NULL,
  `lancado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cl_omie` (`omie_codigo`),
  KEY `idx_cl_emissao` (`emissao`),
  KEY `idx_cl_situacao` (`situacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras_lancadas_itens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lancamento_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned DEFAULT NULL,
  `omie_item` varchar(40) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `quantidade` decimal(14,3) NOT NULL,
  `valor_unitario` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `lancado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cli_lanc` (`lancamento_id`),
  KEY `idx_cli_item` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunicado_funcoes` (
  `comunicado_id` int(10) unsigned NOT NULL,
  `funcao_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`comunicado_id`,`funcao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunicado_leituras` (
  `comunicado_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `lida_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`comunicado_id`,`usuario_id`),
  KEY `idx_leitura_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunicados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(140) NOT NULL,
  `texto` text NOT NULL,
  `prioridade` varchar(12) NOT NULL DEFAULT 'normal',
  `toda_empresa` tinyint(1) NOT NULL DEFAULT 0,
  `inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fim` datetime DEFAULT NULL,
  `encerrado_em` datetime DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comunicados_inicio` (`inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `condicoes_pagamento` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `forma_pagamento_id` int(10) unsigned NOT NULL,
  `nome` varchar(80) NOT NULL,
  `parcelas` int(11) NOT NULL DEFAULT 1,
  `prazo_dias` varchar(60) DEFAULT NULL,
  `desconto_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_minimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_cp_forma` (`forma_pagamento_id`),
  CONSTRAINT `fk_cp_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'texto',
  `grupo` varchar(40) NOT NULL DEFAULT 'Geral',
  `rotulo` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dp_aprovacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int(10) unsigned NOT NULL,
  `etapa_id` int(10) unsigned NOT NULL,
  `funcao_slug` varchar(60) NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `decisao` varchar(10) NOT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  `rodada` int(11) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dpa_projeto` (`projeto_id`,`etapa_id`,`rodada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dp_etapas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `funcao_slug` varchar(60) DEFAULT NULL,
  `checagem` varchar(30) DEFAULT NULL,
  `aprovadores` varchar(120) DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `paralela` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dp_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projeto_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'comentario',
  `texto` varchar(1000) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dph_projeto` (`projeto_id`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dp_projeto_etapas` (
  `projeto_id` int(10) unsigned NOT NULL,
  `etapa_id` int(10) unsigned NOT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'pendente',
  `concluida_por` int(10) unsigned DEFAULT NULL,
  `concluida_em` datetime DEFAULT NULL,
  `observacao` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`projeto_id`,`etapa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dp_projetos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(160) NOT NULL,
  `briefing` text DEFAULT NULL,
  `categoria_id` int(10) unsigned DEFAULT NULL,
  `embalagem` varchar(120) DEFAULT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `produto_id` int(10) unsigned DEFAULT NULL,
  `etapa_id` int(10) unsigned DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'andamento',
  `prazo` date DEFAULT NULL,
  `grafica` varchar(160) DEFAULT NULL,
  `grafica_pedido_em` date DEFAULT NULL,
  `rotulos_recebidos` int(11) NOT NULL DEFAULT 0,
  `ordem_id` int(10) unsigned DEFAULT NULL,
  `compras_ok` text DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lancado_em` datetime DEFAULT NULL,
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  `tipo` varchar(12) NOT NULL DEFAULT 'pote',
  `cor_embalagem` varchar(12) NOT NULL DEFAULT '',
  `cor_tampa` varchar(7) NOT NULL DEFAULT '#14473a',
  PRIMARY KEY (`id`),
  KEY `idx_dp_situacao` (`situacao`,`etapa_id`),
  KEY `idx_dp_produto` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_pedido_itens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `produto_ref` varchar(80) NOT NULL DEFAULT '',
  `ean` varchar(20) DEFAULT NULL,
  `nome` varchar(200) NOT NULL,
  `variacao` varchar(120) DEFAULT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `preco` decimal(12,2) NOT NULL DEFAULT 0.00,
  `custo` decimal(12,2) DEFAULT NULL,
  `imagem` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eco_item_pedido` (`pedido_id`),
  KEY `idx_eco_item_ref` (`produto_ref`),
  CONSTRAINT `fk_eco_item_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `ecommerce_pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_pedidos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tray_id` varchar(40) NOT NULL,
  `origem` varchar(10) NOT NULL DEFAULT 'tray',
  `data_hora` datetime NOT NULL,
  `modificado_em` datetime DEFAULT NULL,
  `status` varchar(80) NOT NULL DEFAULT '',
  `status_tipo` varchar(12) NOT NULL DEFAULT 'pago',
  `status_cor` varchar(9) DEFAULT NULL,
  `canal` varchar(20) NOT NULL DEFAULT 'loja',
  `canal_nome` varchar(80) DEFAULT NULL,
  `marketplace_pedido` varchar(60) DEFAULT NULL,
  `marketplace_envio` varchar(40) DEFAULT NULL,
  `marketplace_status` varchar(60) DEFAULT NULL,
  `parceiro` varchar(60) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frete` decimal(12,2) NOT NULL DEFAULT 0.00,
  `custo` decimal(12,2) DEFAULT NULL,
  `cupom` varchar(60) DEFAULT NULL,
  `itens_qtd` int(11) NOT NULL DEFAULT 0,
  `pagamento` varchar(80) DEFAULT NULL,
  `pagamento_tipo` varchar(20) DEFAULT NULL,
  `pago_em` date DEFAULT NULL,
  `envio` varchar(80) DEFAULT NULL,
  `transportadora` varchar(80) DEFAULT NULL,
  `prazo_entrega` date DEFAULT NULL,
  `enviado_em` date DEFAULT NULL,
  `entregue` tinyint(1) NOT NULL DEFAULT 0,
  `tem_nf` tinyint(1) NOT NULL DEFAULT 0,
  `nf_numero` varchar(30) DEFAULT NULL,
  `nf_em` datetime DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `cliente_hash` char(16) DEFAULT NULL,
  `detalhado` tinyint(1) NOT NULL DEFAULT 0,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eco_tray` (`origem`,`tray_id`),
  KEY `idx_eco_data` (`data_hora`),
  KEY `idx_eco_canal` (`canal`,`data_hora`),
  KEY `idx_eco_status` (`status_tipo`,`data_hora`),
  KEY `idx_eco_uf` (`uf`),
  KEY `idx_eco_detalhe` (`detalhado`,`modificado_em`),
  KEY `idx_eco_cliente` (`cliente_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_produtos_mes` (
  `mes` char(7) NOT NULL,
  `produto_ref` varchar(80) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `faturamento` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`mes`,`produto_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_resumo_diario` (
  `data` date NOT NULL,
  `pedidos` int(11) NOT NULL DEFAULT 0,
  `faturamento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `visitas` int(11) DEFAULT NULL,
  `novos_clientes` int(11) NOT NULL DEFAULT 0,
  `origem` varchar(10) NOT NULL DEFAULT 'fake',
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estoque_acabado` (
  `produto_id` int(10) unsigned NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `minimo` int(11) NOT NULL DEFAULT 0,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`produto_id`),
  CONSTRAINT `fk_acab_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ficha_tecnica` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `quantidade` decimal(14,5) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `perda_pct` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ficha` (`produto_id`,`materia_prima_id`),
  KEY `idx_ficha_mp` (`materia_prima_id`),
  CONSTRAINT `fk_ficha_mp` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`),
  CONSTRAINT `fk_ficha_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_pagamento` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `bloqueia_com_atraso` tinyint(1) NOT NULL DEFAULT 0,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formula_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formula_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `texto` varchar(500) NOT NULL,
  `dados_json` mediumtext DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_formula_historico` (`formula_id`),
  CONSTRAINT `fk_formula_historico` FOREIGN KEY (`formula_id`) REFERENCES `formulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formula_itens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formula_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned DEFAULT NULL,
  `nome_livre` varchar(150) DEFAULT NULL,
  `quantidade` decimal(16,6) NOT NULL DEFAULT 0.000000,
  `unidade` varchar(5) NOT NULL DEFAULT 'mg',
  `base` varchar(10) NOT NULL DEFAULT 'porcao',
  `funcao` varchar(20) NOT NULL DEFAULT 'ativo',
  `ordem` int(11) NOT NULL DEFAULT 0,
  `observacao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_formula_itens` (`formula_id`),
  CONSTRAINT `fk_formula_itens` FOREIGN KEY (`formula_id`) REFERENCES `formulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formula_testes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `formula_id` int(10) unsigned NOT NULL,
  `data` date NOT NULL,
  `lote_tamanho` int(11) DEFAULT NULL,
  `resultado` varchar(12) NOT NULL DEFAULT 'ajustar',
  `observacoes` text DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_formula_testes` (`formula_id`),
  CONSTRAINT `fk_formula_testes` FOREIGN KEY (`formula_id`) REFERENCES `formulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formulas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(12) NOT NULL,
  `versao` int(11) NOT NULL DEFAULT 1,
  `formula_origem_id` int(10) unsigned DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `forma` varchar(20) NOT NULL DEFAULT 'capsula',
  `porcao_qtd` decimal(10,3) NOT NULL DEFAULT 1.000,
  `porcao_unidade` varchar(20) NOT NULL DEFAULT 'cápsulas',
  `porcoes_embalagem` int(11) NOT NULL DEFAULT 30,
  `capacidade_capsula_mg` decimal(10,2) DEFAULT NULL,
  `lote_piloto` int(11) NOT NULL DEFAULT 100,
  `objetivo` text DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'rascunho',
  `produto_id` int(10) unsigned DEFAULT NULL,
  `dp_projeto_id` int(10) unsigned DEFAULT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `aprovado_por` int(10) unsigned DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_formulas_codigo` (`codigo`,`versao`),
  KEY `idx_formulas_situacao` (`situacao`),
  KEY `idx_formulas_produto` (`produto_id`),
  KEY `idx_formulas_projeto` (`dp_projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `funcoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `areas_foco` varchar(120) DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `frente` varchar(20) DEFAULT NULL,
  `setor_venda` tinyint(1) NOT NULL DEFAULT 0,
  `icone` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `funil_etapas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `cor` varchar(7) NOT NULL DEFAULT '#94a3b8',
  `tipo` varchar(20) NOT NULL DEFAULT 'aberta',
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `importacao_linhas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `importacao_id` int(10) unsigned NOT NULL,
  `linha` int(11) NOT NULL,
  `acao` varchar(12) NOT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dados_json`)),
  `mensagem` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_impl` (`importacao_id`,`acao`),
  CONSTRAINT `fk_impl_imp` FOREIGN KEY (`importacao_id`) REFERENCES `importacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `importacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `arquivo` varchar(200) NOT NULL,
  `formato` varchar(10) NOT NULL,
  `total` int(11) NOT NULL DEFAULT 0,
  `novos` int(11) NOT NULL DEFAULT 0,
  `atualizados` int(11) NOT NULL DEFAULT 0,
  `duplicados` int(11) NOT NULL DEFAULT 0,
  `erros` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'previa',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `concluida_em` datetime DEFAULT NULL,
  `carteira_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_imp_usuario` (`usuario_id`),
  CONSTRAINT `fk_imp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads_recebidos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resultado` varchar(12) NOT NULL,
  `motivo` varchar(120) DEFAULT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `nome` varchar(200) DEFAULT NULL,
  `empresa` varchar(200) DEFAULT NULL,
  `cidade` varchar(120) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_json`)),
  `origem` varchar(20) DEFAULT NULL,
  `utm_source` varchar(120) DEFAULT NULL,
  `utm_medium` varchar(120) DEFAULT NULL,
  `utm_campaign` varchar(120) DEFAULT NULL,
  `utm_term` varchar(120) DEFAULT NULL,
  `utm_content` varchar(120) DEFAULT NULL,
  `pagina` varchar(255) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lr_data` (`criado_em`),
  KEY `idx_lr_ip` (`ip_hash`,`criado_em`),
  KEY `idx_lr_campanha` (`utm_source`,`utm_campaign`),
  KEY `idx_lr_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ligacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_externo` varchar(80) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `sentido` varchar(10) NOT NULL DEFAULT 'saida',
  `situacao` varchar(15) NOT NULL DEFAULT 'chamando',
  `inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `duracao_seg` int(10) unsigned NOT NULL DEFAULT 0,
  `gravacao` varchar(255) DEFAULT NULL,
  `origem` varchar(12) NOT NULL DEFAULT 'sistema',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lig_externo` (`id_externo`),
  KEY `idx_lig_usuario` (`usuario_id`,`inicio`),
  KEY `idx_lig_cliente` (`cliente_id`),
  KEY `idx_lig_inicio` (`inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `materias_primas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) DEFAULT NULL,
  `nome` varchar(160) NOT NULL,
  `unidade` varchar(10) NOT NULL DEFAULT 'kg',
  `tipo` varchar(20) NOT NULL DEFAULT 'materia_prima',
  `estoque` decimal(14,3) NOT NULL DEFAULT 0.000,
  `estoque_minimo` decimal(14,3) NOT NULL DEFAULT 0.000,
  `fornecedor` varchar(160) DEFAULT NULL,
  `custo_unitario` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `observacoes` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `omie_codigo` varchar(40) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rotulo` tinyint(1) NOT NULL DEFAULT 0,
  `categoria` varchar(60) DEFAULT NULL,
  `fabricante` varchar(120) DEFAULT NULL,
  `cas_ins` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mp_nome` (`nome`),
  KEY `idx_mp_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mensagens_prontas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(120) NOT NULL,
  `categoria` varchar(60) NOT NULL,
  `texto` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `usos` int(10) unsigned NOT NULL DEFAULT 0,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_msg_categoria` (`categoria`,`ativo`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mes` char(7) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `alvo_id` int(10) unsigned NOT NULL,
  `valor` decimal(14,2) NOT NULL DEFAULT 0.00,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meta` (`mes`,`tipo`,`alvo_id`),
  KEY `idx_meta_alvo` (`tipo`,`alvo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metas_equipe` (
  `mes` char(7) NOT NULL,
  `meta` decimal(14,2) NOT NULL DEFAULT 0.00,
  `vendido` decimal(14,2) NOT NULL DEFAULT 0.00,
  `dias_semana` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `folgas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`folgas`)),
  `atualizado_por` int(10) unsigned DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`mes`),
  KEY `fk_me_usuario` (`atualizado_por`),
  CONSTRAINT `fk_me_usuario` FOREIGN KEY (`atualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metas_equipe_lancamentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mes` char(7) NOT NULL,
  `data` date NOT NULL,
  `vendido` decimal(14,2) NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mel_mes` (`mes`,`data`),
  KEY `fk_mel_usuario` (`usuario_id`),
  CONSTRAINT `fk_mel_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meu_mes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `mes` char(7) NOT NULL,
  `meta` decimal(14,2) NOT NULL DEFAULT 0.00,
  `dias_semana` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `folgas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`folgas`)),
  `anotacoes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meu_mes` (`usuario_id`,`mes`),
  CONSTRAINT `fk_mm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `midias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `arquivo` varchar(255) NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `tamanho` int(10) unsigned NOT NULL DEFAULT 0,
  `hash` char(40) NOT NULL,
  `alt` varchar(255) DEFAULT NULL,
  `origem` varchar(80) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `arquivo` (`arquivo`),
  KEY `idx_midia_hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migracoes` (
  `arquivo` varchar(160) NOT NULL,
  `aplicada_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`arquivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_campanhas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `objetivo` varchar(255) DEFAULT NULL,
  `inicio` date NOT NULL,
  `fim` date NOT NULL,
  `cor` varchar(7) NOT NULL DEFAULT '#9bd344',
  `meta_descricao` varchar(255) DEFAULT NULL,
  `orcamento` decimal(12,2) DEFAULT NULL,
  `produto_id` int(10) unsigned DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'planejada',
  `observacoes` text DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_camp_periodo` (`inicio`,`fim`),
  KEY `fk_camp_produto` (`produto_id`),
  CONSTRAINT `fk_camp_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_post_comentarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'comentario',
  `texto` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_coment_post` (`post_id`,`criado_em`),
  KEY `fk_coment_usuario` (`usuario_id`),
  CONSTRAINT `fk_coment_post` FOREIGN KEY (`post_id`) REFERENCES `mkt_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coment_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_post_etapas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `cor` varchar(7) NOT NULL DEFAULT '#94a3b8',
  `tipo` varchar(20) NOT NULL DEFAULT 'rascunho',
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campanha_id` int(10) unsigned DEFAULT NULL,
  `formato` varchar(12) NOT NULL DEFAULT 'feed',
  `titulo` varchar(160) NOT NULL,
  `legenda` text DEFAULT NULL,
  `hashtags` varchar(1000) DEFAULT NULL,
  `data_publicacao` date NOT NULL,
  `hora` time DEFAULT NULL,
  `etapa_id` int(10) unsigned NOT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `midia_id` int(10) unsigned DEFAULT NULL,
  `link_publicado` varchar(500) DEFAULT NULL,
  `aprovado_por` int(10) unsigned DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `motivo_ajuste` varchar(500) DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_post_data` (`data_publicacao`),
  KEY `idx_post_etapa` (`etapa_id`),
  KEY `idx_post_campanha` (`campanha_id`),
  KEY `fk_post_resp` (`responsavel_id`),
  KEY `fk_post_midia` (`midia_id`),
  CONSTRAINT `fk_post_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `mkt_campanhas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_post_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `mkt_post_etapas` (`id`),
  CONSTRAINT `fk_post_midia` FOREIGN KEY (`midia_id`) REFERENCES `midias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_post_resp` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_tarefa_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `texto` varchar(1000) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mth_tarefa` (`tarefa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_tarefas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(160) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo_id` int(10) unsigned DEFAULT NULL,
  `funcao_slug` varchar(40) NOT NULL,
  `responsavel_id` int(10) unsigned DEFAULT NULL,
  `situacao` varchar(10) NOT NULL DEFAULT 'a_fazer',
  `urgente` tinyint(1) NOT NULL DEFAULT 0,
  `prazo` date DEFAULT NULL,
  `solicitante_id` int(10) unsigned DEFAULT NULL,
  `setor` varchar(100) DEFAULT NULL,
  `anexo` varchar(60) DEFAULT NULL,
  `anexo_nome` varchar(200) DEFAULT NULL,
  `anexo_mime` varchar(60) DEFAULT NULL,
  `post_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `iniciado_em` datetime DEFAULT NULL,
  `concluido_em` datetime DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mt_situacao` (`situacao`,`funcao_slug`),
  KEY `idx_mt_solicitante` (`solicitante_id`),
  KEY `idx_mt_prazo` (`prazo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mkt_tipos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `funcao_slug` varchar(40) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mp_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'outro',
  `titulo` varchar(160) NOT NULL,
  `arquivo` varchar(60) NOT NULL,
  `nome_original` varchar(200) NOT NULL,
  `mime` varchar(80) NOT NULL,
  `tamanho` int(10) unsigned NOT NULL DEFAULT 0,
  `validade` date DEFAULT NULL,
  `lote` varchar(40) DEFAULT NULL,
  `fornecedor_id` int(10) unsigned DEFAULT NULL,
  `lancamento_id` int(10) unsigned DEFAULT NULL,
  `enviado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `arquivado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mpd_item` (`materia_prima_id`,`arquivado_em`),
  KEY `idx_mpd_validade` (`validade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mp_fornecedores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `fornecedor` varchar(160) NOT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `contato` varchar(120) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `codigo_no_fornecedor` varchar(60) DEFAULT NULL,
  `ultimo_preco` decimal(14,4) DEFAULT NULL,
  `ultima_compra_em` date DEFAULT NULL,
  `prazo_dias` int(11) DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacoes` varchar(500) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mpf_item` (`materia_prima_id`,`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mp_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `lote` varchar(60) NOT NULL,
  `fabricante` varchar(120) DEFAULT NULL,
  `fabricacao` date DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `quantidade` decimal(14,3) NOT NULL DEFAULT 0.000,
  `saldo` decimal(14,3) NOT NULL DEFAULT 0.000,
  `movimento_id` int(10) unsigned DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_lote` (`materia_prima_id`,`lote`),
  KEY `idx_mp_lote_validade` (`materia_prima_id`,`validade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mp_movimentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `quantidade` decimal(14,3) NOT NULL,
  `saldo` decimal(14,3) NOT NULL,
  `custo_unitario` decimal(12,4) DEFAULT NULL,
  `referencia` varchar(80) DEFAULT NULL,
  `ordem_id` int(10) unsigned DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `fornecedor_id` int(10) unsigned DEFAULT NULL,
  `lancamento_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mpm_mp` (`materia_prima_id`,`criado_em`),
  KEY `idx_mpm_tipo` (`tipo`,`criado_em`),
  CONSTRAINT `fk_mpm_mp` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota10_importacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arquivo` varchar(255) NOT NULL COMMENT 'Arquivo .sql em storage/importacoes/nota10',
  `nome_original` varchar(255) DEFAULT NULL,
  `tamanho` bigint(20) NOT NULL DEFAULT 0,
  `posicao` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Até onde o arquivo já foi lido (bytes)',
  `situacao` varchar(12) NOT NULL DEFAULT 'enviado' COMMENT 'enviado, lendo, lido, erro',
  `charset` varchar(20) DEFAULT NULL COMMENT 'Codificação do backup (SET NAMES do arquivo)',
  `comandos` int(11) NOT NULL DEFAULT 0,
  `ignorados` int(11) NOT NULL DEFAULT 0,
  `erro` text DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `lido_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota10_mapas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) NOT NULL COMMENT 'vendedor, tabela, forma, segmento, tipo_cadastro',
  `codigo` varchar(40) NOT NULL COMMENT 'Código no Nota10',
  `nome` varchar(120) DEFAULT NULL COMMENT 'Nome no Nota10 (para mostrar)',
  `destino` varchar(40) DEFAULT NULL COMMENT 'id no One (ou a escolha, ex.: cliente/fornecedor/transportadora/ignorar)',
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nota10_mapa` (`tipo`,`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacoes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `texto` varchar(300) DEFAULT NULL,
  `link` varchar(255) NOT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `pedido_id` int(10) unsigned DEFAULT NULL,
  `lida_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_usuario` (`usuario_id`,`lida_em`,`id`),
  KEY `idx_notif_cliente` (`usuario_id`,`cliente_id`,`lida_em`),
  KEY `idx_notif_pedido` (`pedido_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `one_especialistas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(30) NOT NULL,
  `nome` varchar(60) NOT NULL,
  `icone` varchar(8) NOT NULL DEFAULT '✨',
  `descricao` varchar(200) NOT NULL DEFAULT '',
  `funcoes` varchar(300) NOT NULL DEFAULT '',
  `instrucoes` text DEFAULT NULL,
  `consultas` varchar(500) NOT NULL DEFAULT '',
  `sugestoes` varchar(600) NOT NULL DEFAULT '',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `editado_em` datetime DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `one_perguntas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `provedor` varchar(20) NOT NULL,
  `consultas` varchar(255) DEFAULT NULL,
  `tokens_entrada` int(10) unsigned NOT NULL DEFAULT 0,
  `tokens_saida` int(10) unsigned NOT NULL DEFAULT 0,
  `custo_estimado` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `situacao` varchar(20) NOT NULL DEFAULT 'ok',
  `especialista` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_onep_usuario` (`usuario_id`,`criado_em`),
  KEY `idx_onep_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamento_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `pelo_cliente` tinyint(1) NOT NULL DEFAULT 0,
  `texto` varchar(1000) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orch_orc` (`orcamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamento_itens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(10) unsigned NOT NULL,
  `chave` varchar(12) NOT NULL,
  `produto_id` int(10) unsigned DEFAULT NULL,
  `terceirizacao_produto_id` int(10) unsigned DEFAULT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `nome` varchar(200) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `quantidade_cliente` int(11) DEFAULT NULL,
  `bonificado` tinyint(1) NOT NULL DEFAULT 0,
  `valor_bonificado` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_orci_orc` (`orcamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `token` char(64) NOT NULL,
  `cliente_id` int(10) unsigned NOT NULL,
  `vendedor_id` int(10) unsigned DEFAULT NULL,
  `tabela_preco_id` int(10) unsigned DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'aberto',
  `forma_pagamento_id` int(10) unsigned DEFAULT NULL,
  `condicao_id` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observacoes` varchar(1000) DEFAULT NULL,
  `resposta_cliente` varchar(1000) DEFAULT NULL,
  `cliente_alterou` tinyint(1) NOT NULL DEFAULT 0,
  `validade` date NOT NULL,
  `visto_em` datetime DEFAULT NULL,
  `respondido_em` datetime DEFAULT NULL,
  `pedido_id` int(10) unsigned DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL,
  `bonificado_valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bonificacao_motivo` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orc_uuid` (`uuid`),
  UNIQUE KEY `uq_orc_token` (`token`),
  KEY `idx_orc_cliente` (`cliente_id`),
  KEY `idx_orc_vendedor` (`vendedor_id`,`situacao`),
  KEY `idx_orc_situacao` (`situacao`,`validade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordem_mp_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ordem_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `mp_lote_id` int(10) unsigned DEFAULT NULL,
  `quantidade` decimal(14,3) NOT NULL DEFAULT 0.000,
  `baixado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_oml_ordem` (`ordem_id`),
  KEY `idx_oml_lote` (`mp_lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordens_producao` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(10) unsigned NOT NULL,
  `quantidade` int(10) unsigned NOT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'planejada',
  `origem` varchar(15) NOT NULL DEFAULT 'manual',
  `lote` varchar(40) DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `previsto_para` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `custo_total` decimal(14,2) DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `concluida_por` int(10) unsigned DEFAULT NULL,
  `concluida_em` datetime DEFAULT NULL,
  `cancelada_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  `forma` varchar(20) DEFAULT NULL,
  `unidades_por_frasco` int(11) DEFAULT NULL,
  `amostra` tinyint(1) NOT NULL DEFAULT 0,
  `fornecedor_mp` varchar(10) NOT NULL DEFAULT 'pa',
  `entrada_producao` date DEFAULT NULL,
  `aprovada_por` int(10) unsigned DEFAULT NULL,
  `aprovada_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_op_situacao` (`situacao`,`criado_em`),
  KEY `idx_op_produto` (`produto_id`),
  KEY `idx_op_concluida` (`concluida_em`),
  CONSTRAINT `fk_op_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `origens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_embalagens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `quantidade` decimal(12,3) NOT NULL DEFAULT 0.000,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pedido_embalagem` (`pedido_id`,`materia_prima_id`),
  KEY `idx_embalagem_item` (`materia_prima_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `situacao` varchar(25) NOT NULL,
  `texto` varchar(500) NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hist_pedido` (`pedido_id`,`criado_em`),
  CONSTRAINT `fk_hist_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_item_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `pedido_item_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned DEFAULT NULL,
  `lote` varchar(40) NOT NULL,
  `validade` date DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lote` (`lote`),
  KEY `idx_lote_produto` (`produto_id`,`lote`),
  KEY `idx_lote_pedido` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_itens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned DEFAULT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `nome` varchar(200) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `terceirizacao_produto_id` int(10) unsigned DEFAULT NULL,
  `qtd_separada` int(11) DEFAULT NULL,
  `preco_tabela` decimal(12,2) DEFAULT NULL,
  `desconto_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `promo_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `campanha_id` int(10) unsigned DEFAULT NULL,
  `custo_unitario` decimal(12,4) DEFAULT NULL COMMENT 'Custo do produto na data da venda (margem)',
  `desconto_valor` decimal(12,2) DEFAULT NULL,
  `lote` varchar(30) DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `fabricacao` date DEFAULT NULL,
  `ncm` varchar(10) DEFAULT NULL,
  `cfop` varchar(10) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL COMMENT 'Quando o item tem vendedor diferente do pedido',
  `nota10_guid` varchar(32) DEFAULT NULL,
  `bonificado` tinyint(1) NOT NULL DEFAULT 0,
  `valor_bonificado` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Quantidade × preço da tabela (vai na nota; a loja não paga)',
  PRIMARY KEY (`id`),
  KEY `idx_item_pedido` (`pedido_id`),
  KEY `idx_item_produto` (`produto_id`),
  KEY `idx_pedido_itens_lote` (`lote`),
  CONSTRAINT `fk_item_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_modelo_itens` (
  `modelo_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned NOT NULL,
  `quantidade` int(11) NOT NULL,
  PRIMARY KEY (`modelo_id`,`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_modelos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` varchar(300) DEFAULT NULL,
  `tipo` varchar(10) NOT NULL DEFAULT 'pessoal',
  `dono_id` int(10) unsigned DEFAULT NULL,
  `funcao_id` int(10) unsigned DEFAULT NULL,
  `validade_ate` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pm_dono` (`tipo`,`dono_id`,`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `cliente_id` int(10) unsigned NOT NULL,
  `vendedor_id` int(10) unsigned DEFAULT NULL,
  `tirado_por_id` int(10) unsigned DEFAULT NULL,
  `origem` varchar(10) NOT NULL DEFAULT 'sistema',
  `tabela_preco_id` int(10) unsigned NOT NULL,
  `forma_pagamento_id` int(10) unsigned NOT NULL,
  `condicao_id` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `situacao` varchar(25) NOT NULL DEFAULT 'aguardando_aprovacao',
  `omie_codigo` varchar(40) DEFAULT NULL,
  `omie_numero` varchar(40) DEFAULT NULL,
  `numero_nf` varchar(40) DEFAULT NULL,
  `faturado_em` datetime DEFAULT NULL,
  `aprovado_por` int(10) unsigned DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `motivo_recusa` varchar(500) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_por_usuario` int(10) unsigned DEFAULT NULL,
  `criado_por_cliente` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  `expedicao` varchar(12) DEFAULT NULL,
  `expedicao_prioridade` tinyint(1) NOT NULL DEFAULT 0,
  `expedicao_aguarda_produto` tinyint(1) NOT NULL DEFAULT 0,
  `expedicao_desde` datetime DEFAULT NULL,
  `separado_por` int(10) unsigned DEFAULT NULL,
  `separado_em` datetime DEFAULT NULL,
  `despachado_por` int(10) unsigned DEFAULT NULL,
  `despachado_em` datetime DEFAULT NULL,
  `transportadora` varchar(80) DEFAULT NULL,
  `volumes` int(11) DEFAULT NULL,
  `peso_kg` decimal(10,3) DEFAULT NULL,
  `rastreio` varchar(80) DEFAULT NULL,
  `expedicao_obs` varchar(500) DEFAULT NULL,
  `falta` tinyint(1) NOT NULL DEFAULT 0,
  `tipo_venda` varchar(15) DEFAULT NULL,
  `canal` varchar(20) DEFAULT NULL,
  `tray_codigo` varchar(40) DEFAULT NULL,
  `marketplace_numero` varchar(60) DEFAULT NULL,
  `comprador_nome` varchar(120) DEFAULT NULL,
  `comprador_cidade` varchar(80) DEFAULT NULL,
  `comprador_uf` char(2) DEFAULT NULL,
  `expedicao_limite` datetime DEFAULT NULL,
  `desconto_pedido_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `desconto_vendedor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto_acima_limite` tinyint(1) NOT NULL DEFAULT 0,
  `campanha_desconto_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `campanha_desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `campanha_brindes` varchar(500) DEFAULT NULL,
  `frete_gratis` tinyint(1) NOT NULL DEFAULT 0,
  `campanhas` varchar(255) DEFAULT NULL,
  `nota10_guid` varchar(32) DEFAULT NULL COMMENT 'GUID da nota/pedido no Nota10',
  `nota10_pedido` varchar(20) DEFAULT NULL COMMENT 'Número do pedido no Nota10',
  `nf_serie` varchar(5) DEFAULT NULL,
  `nf_chave` varchar(44) DEFAULT NULL COMMENT 'Chave de acesso da NF-e',
  `nf_situacao` varchar(20) DEFAULT NULL COMMENT 'autorizada, cancelada, denegada...',
  `emitido_em` datetime DEFAULT NULL COMMENT 'Emissão da nota',
  `saida_em` date DEFAULT NULL,
  `entrega_em` date DEFAULT NULL,
  `cfop` varchar(10) DEFAULT NULL,
  `natureza_operacao` varchar(80) DEFAULT NULL,
  `tipo_operacao` varchar(20) DEFAULT NULL COMMENT 'venda, bonificacao, devolucao, remessa, outra (pelo CFOP)',
  `frete_valor` decimal(12,2) DEFAULT NULL,
  `frete_por` varchar(20) DEFAULT NULL COMMENT 'emitente, destinatario, terceiros, sem_frete',
  `valor_icms` decimal(12,2) DEFAULT NULL,
  `valor_st` decimal(12,2) DEFAULT NULL,
  `valor_ipi` decimal(12,2) DEFAULT NULL,
  `obs_interna` text DEFAULT NULL,
  `romaneio_numero` int(11) DEFAULT NULL,
  `entrega_outro` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = entregar no endereço de entrega (diferente do cadastro)',
  `entrega_texto` varchar(400) DEFAULT NULL COMMENT 'Endereço de entrega usado neste pedido (cópia)',
  `entrega_cidade` varchar(80) DEFAULT NULL,
  `entrega_uf` char(2) DEFAULT NULL COMMENT 'UF de entrega: define CFOP dentro/fora do estado',
  `bonificado_valor` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Soma dos itens bonificados (preço de tabela)',
  `bonificacao_motivo` varchar(60) DEFAULT NULL,
  `bonificacao_acima_limite` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = passou do % permitido: precisa de aprovação',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `uq_pedidos_tray` (`tray_codigo`),
  KEY `idx_ped_cliente` (`cliente_id`,`criado_em`),
  KEY `idx_ped_vendedor` (`vendedor_id`,`criado_em`),
  KEY `idx_ped_situacao` (`situacao`,`criado_em`),
  KEY `idx_ped_data` (`criado_em`),
  KEY `fk_ped_tabela` (`tabela_preco_id`),
  KEY `fk_ped_forma` (`forma_pagamento_id`),
  KEY `fk_ped_condicao` (`condicao_id`),
  KEY `idx_ped_faturado` (`situacao`,`faturado_em`),
  KEY `idx_ped_expedicao` (`expedicao`,`expedicao_desde`),
  KEY `idx_pedidos_tipo_venda` (`tipo_venda`,`expedicao`),
  KEY `idx_pedidos_tirado_por` (`tirado_por_id`,`situacao`),
  KEY `idx_pedidos_nota10` (`nota10_guid`),
  KEY `idx_pedidos_tipo_operacao` (`tipo_operacao`),
  KEY `idx_pedidos_bonificado` (`bonificado_valor`),
  CONSTRAINT `fk_ped_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `fk_ped_condicao` FOREIGN KEY (`condicao_id`) REFERENCES `condicoes_pagamento` (`id`),
  CONSTRAINT `fk_ped_forma` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  CONSTRAINT `fk_ped_tabela` FOREIGN KEY (`tabela_preco_id`) REFERENCES `tabelas_preco` (`id`),
  CONSTRAINT `fk_ped_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_elogios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `para_id` int(10) unsigned NOT NULL,
  `de_id` int(10) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `texto` varchar(300) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `oculto_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_elogios_para` (`para_id`,`criado_em`),
  KEY `idx_elogios_de` (`de_id`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_permissoes` (
  `perfil_id` int(10) unsigned NOT NULL,
  `permissao` varchar(60) NOT NULL,
  PRIMARY KEY (`perfil_id`,`permissao`),
  CONSTRAINT `fk_pp_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_recados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `perfil_id` int(10) unsigned NOT NULL,
  `autor_id` int(10) unsigned NOT NULL,
  `texto` varchar(500) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `oculto_em` datetime DEFAULT NULL,
  `oculto_por` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recados_perfil` (`perfil_id`,`criado_em`),
  KEY `idx_recados_autor` (`autor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producao_perdas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ordem_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned NOT NULL,
  `materia_prima_id` int(10) unsigned NOT NULL,
  `unidades` int(11) NOT NULL,
  `teorico` decimal(14,3) NOT NULL,
  `usado` decimal(14,3) NOT NULL,
  `perda_pct` decimal(7,2) NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_perda_mp` (`materia_prima_id`),
  KEY `idx_perda_produto` (`produto_id`,`materia_prima_id`),
  KEY `idx_perda_ordem` (`ordem_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(10) unsigned NOT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'outro',
  `titulo` varchar(160) NOT NULL,
  `arquivo` varchar(120) NOT NULL,
  `nome_original` varchar(200) NOT NULL,
  `mime` varchar(60) NOT NULL,
  `tamanho` int(10) unsigned NOT NULL DEFAULT 0,
  `validade` date DEFAULT NULL,
  `interno` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `arquivado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pd_produto` (`produto_id`,`arquivado_em`),
  KEY `idx_pd_validade` (`validade`),
  CONSTRAINT `fk_pd_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_estoque` (
  `produto_id` int(10) unsigned NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`produto_id`),
  CONSTRAINT `fk_estoque_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_nutricional` (
  `produto_id` int(10) unsigned NOT NULL,
  `porcoes_embalagem` varchar(40) DEFAULT NULL,
  `porcao` varchar(80) DEFAULT NULL,
  `porcao_coluna` varchar(20) DEFAULT NULL,
  `linhas` text DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `atualizado_por` int(10) unsigned DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_precos` (
  `produto_id` int(10) unsigned NOT NULL,
  `tabela_preco_id` int(10) unsigned NOT NULL,
  `preco` decimal(12,2) NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`produto_id`,`tabela_preco_id`),
  KEY `fk_preco_tabela` (`tabela_preco_id`),
  CONSTRAINT `fk_preco_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_preco_tabela` FOREIGN KEY (`tabela_preco_id`) REFERENCES `tabelas_preco` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_tecnico` (
  `produto_id` int(10) unsigned NOT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `ncm` varchar(12) DEFAULT NULL,
  `registro_anvisa` varchar(80) DEFAULT NULL,
  `validade_meses` smallint(5) unsigned DEFAULT NULL,
  `peso_liquido` varchar(40) DEFAULT NULL,
  `peso_bruto` varchar(40) DEFAULT NULL,
  `dimensoes` varchar(60) DEFAULT NULL,
  `unidades_caixa` smallint(5) unsigned DEFAULT NULL,
  `armazenamento` varchar(500) DEFAULT NULL,
  `alergenicos` varchar(500) DEFAULT NULL,
  `avisos` varchar(1000) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `atualizado_por` int(10) unsigned DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`produto_id`),
  CONSTRAINT `fk_pt_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `nome` varchar(160) NOT NULL,
  `embalagem` varchar(120) DEFAULT NULL,
  `categoria_id` int(10) unsigned DEFAULT NULL,
  `chamada` varchar(255) DEFAULT NULL,
  `imagem_id` int(10) unsigned DEFAULT NULL,
  `ficha_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`ficha_json`)),
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `visivel_catalogo` tinyint(1) NOT NULL DEFAULT 1,
  `omie_codigo` varchar(40) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cliente_exclusivo_id` int(10) unsigned DEFAULT NULL,
  `nota10_codigo` varchar(30) DEFAULT NULL,
  `unidade` varchar(6) DEFAULT NULL,
  `cest` varchar(10) DEFAULT NULL,
  `custo` decimal(12,4) DEFAULT NULL COMMENT 'Custo atual (ERP)',
  `grupo_erp` varchar(60) DEFAULT NULL,
  `subgrupo_erp` varchar(60) DEFAULT NULL,
  `cadastrado_erp_em` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `uk_produto_codigo` (`codigo`),
  KEY `idx_prod_categoria` (`categoria_id`),
  KEY `fk_prod_imagem` (`imagem_id`),
  KEY `idx_produtos_exclusivo` (`cliente_exclusivo_id`),
  KEY `idx_produtos_nota10` (`nota10_codigo`),
  CONSTRAINT `fk_prod_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `fk_prod_imagem` FOREIGN KEY (`imagem_id`) REFERENCES `midias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospeccao_empresas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cnpj` char(14) NOT NULL,
  `razao_social` varchar(200) NOT NULL,
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `cnae` varchar(10) NOT NULL,
  `cnae_descricao` varchar(200) DEFAULT NULL,
  `situacao` varchar(20) NOT NULL DEFAULT 'ATIVA',
  `abertura` date DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `bairro` varchar(120) DEFAULT NULL,
  `cidade` varchar(120) NOT NULL,
  `uf` char(2) NOT NULL,
  `cep` char(8) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `porte` varchar(30) DEFAULT NULL,
  `importado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pe_cnpj` (`cnpj`),
  KEY `idx_pe_local` (`uf`,`cidade`),
  KEY `idx_pe_cnae` (`cnae`),
  KEY `idx_pe_abertura` (`abertura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospeccao_envios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cnpj` char(14) NOT NULL,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_penv_cnpj` (`cnpj`),
  KEY `idx_penv_usuario` (`usuario_id`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regiao_ufs` (
  `uf` char(2) NOT NULL,
  `regiao_id` int(10) unsigned NOT NULL,
  `pedido_minimo` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`uf`),
  KEY `fk_ru_regiao` (`regiao_id`),
  CONSTRAINT `fk_ru_regiao` FOREIGN KEY (`regiao_id`) REFERENCES `regioes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regioes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `pedido_minimo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observacao` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roteiro_paradas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `roteiro_id` int(10) unsigned NOT NULL,
  `cliente_id` int(10) unsigned NOT NULL,
  `dia` date DEFAULT NULL,
  `ordem` smallint(6) NOT NULL DEFAULT 0,
  `situacao` varchar(15) NOT NULL DEFAULT 'pendente',
  `observacao` varchar(500) DEFAULT NULL,
  `pedido_id` int(10) unsigned DEFAULT NULL,
  `visitado_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parada` (`roteiro_id`,`cliente_id`),
  KEY `idx_parada_cliente` (`cliente_id`),
  KEY `idx_parada_dia` (`roteiro_id`,`dia`,`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roteiros` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `nome` varchar(120) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `cidades_json` text DEFAULT NULL,
  `raio_km` smallint(6) NOT NULL DEFAULT 15,
  `visitas_dia` tinyint(4) NOT NULL DEFAULT 8,
  `situacao` varchar(15) NOT NULL DEFAULT 'planejado',
  `observacoes` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trajeto_json` mediumtext DEFAULT NULL,
  `km_total` decimal(8,1) DEFAULT NULL,
  `minutos_total` int(11) DEFAULT NULL,
  `dias_json` text DEFAULT NULL,
  `rota_aviso` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_roteiros_usuario` (`usuario_id`,`situacao`,`data_fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `segmentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tabelas_preco` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `omie_codigo` varchar(40) DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarefas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'retorno',
  `prioridade` varchar(10) NOT NULL DEFAULT 'normal',
  `vence_em` date NOT NULL,
  `hora` time DEFAULT NULL,
  `notas` varchar(1000) DEFAULT NULL,
  `concluida_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `simulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tar_usuario` (`usuario_id`,`concluida_em`,`vence_em`),
  KEY `idx_tar_cliente` (`cliente_id`,`concluida_em`),
  CONSTRAINT `fk_tar_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tar_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tentativas_login` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(160) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tl_email` (`email`,`criado_em`),
  KEY `idx_tl_ip` (`ip`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `terceirizacao_produtos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int(10) unsigned NOT NULL,
  `produto_id` int(10) unsigned NOT NULL,
  `nome_rotulo` varchar(160) NOT NULL,
  `codigo_cliente` varchar(40) DEFAULT NULL,
  `preco` decimal(12,2) DEFAULT NULL,
  `rotulos_estoque` int(11) NOT NULL DEFAULT 0,
  `rotulos_minimo` int(11) NOT NULL DEFAULT 0,
  `observacoes` varchar(500) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terc_cliente_produto` (`cliente_id`,`produto_id`),
  KEY `idx_terc_produto` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `terceirizacao_rotulos_mov` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `terceirizacao_produto_id` int(10) unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `saldo_apos` int(11) NOT NULL,
  `ordem_producao_id` int(10) unsigned DEFAULT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_terc_mov` (`terceirizacao_produto_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `titulos_receber` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `documento` varchar(40) DEFAULT NULL,
  `parcela` varchar(10) DEFAULT NULL,
  `nosso_numero` varchar(30) DEFAULT NULL,
  `forma_pagamento` varchar(40) DEFAULT NULL,
  `historico` varchar(255) DEFAULT NULL,
  `lancado_em` date DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `pago_em` date DEFAULT NULL,
  `cancelado_em` date DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `acrescimo` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Juros e multa',
  `valor_pago` decimal(12,2) DEFAULT NULL,
  `situacao` varchar(12) NOT NULL DEFAULT 'aberto' COMMENT 'aberto, pago, cancelado',
  `origem` varchar(20) NOT NULL DEFAULT 'nota10' COMMENT 'nota10 ou omie',
  `nota10_guid` varchar(32) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_titulos_nota10` (`nota10_guid`),
  KEY `idx_titulos_cliente` (`cliente_id`,`situacao`),
  KEY `idx_titulos_vencimento` (`situacao`,`vencimento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trein_modulos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trilha_id` int(10) unsigned NOT NULL,
  `chave` varchar(80) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'setor',
  `titulo` varchar(160) NOT NULL,
  `resumo` varchar(255) DEFAULT NULL,
  `conteudo` mediumtext NOT NULL,
  `tempo_min` int(11) NOT NULL DEFAULT 5,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `editado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`),
  KEY `idx_trmod_trilha` (`trilha_id`,`ordem`),
  CONSTRAINT `fk_trmod_trilha` FOREIGN KEY (`trilha_id`) REFERENCES `trein_trilhas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trein_perguntas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `modulo_id` int(10) unsigned NOT NULL,
  `pergunta` varchar(255) NOT NULL,
  `opcoes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`opcoes_json`)),
  `correta` tinyint(4) NOT NULL,
  `explicacao` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trperg_modulo` (`modulo_id`),
  CONSTRAINT `fk_trperg_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `trein_modulos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trein_progresso` (
  `usuario_id` int(10) unsigned NOT NULL,
  `modulo_id` int(10) unsigned NOT NULL,
  `lido_em` datetime DEFAULT NULL,
  `nota` tinyint(4) DEFAULT NULL,
  `tentativas` int(11) NOT NULL DEFAULT 0,
  `aprovado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`usuario_id`,`modulo_id`),
  KEY `idx_trprog_modulo` (`modulo_id`),
  CONSTRAINT `fk_trprog_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `trein_modulos` (`id`),
  CONSTRAINT `fk_trprog_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trein_trilhas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(60) NOT NULL,
  `funcao_slug` varchar(60) DEFAULT NULL,
  `titulo` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `icone` varchar(10) DEFAULT NULL,
  `vizinhos` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_progresso` (
  `usuario_id` int(10) unsigned NOT NULL,
  `rota` varchar(120) NOT NULL,
  `vezes` int(11) NOT NULL DEFAULT 0,
  `aprendido_em` datetime DEFAULT NULL,
  PRIMARY KEY (`usuario_id`,`rota`),
  CONSTRAINT `fk_tutprog_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorial_telas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rota` varchar(120) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `passos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`passos_json`)),
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `editado_em` datetime DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rota` (`rota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_funcoes` (
  `usuario_id` int(10) unsigned NOT NULL,
  `funcao_id` int(10) unsigned NOT NULL,
  `gestor` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usuario_id`,`funcao_id`),
  KEY `fk_uf_funcao` (`funcao_id`),
  CONSTRAINT `fk_uf_funcao` FOREIGN KEY (`funcao_id`) REFERENCES `funcoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uf_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_perfil` (
  `usuario_id` int(10) unsigned NOT NULL,
  `sobre_mim` varchar(600) DEFAULT NULL,
  `hobbies` varchar(300) DEFAULT NULL,
  `comida` varchar(120) DEFAULT NULL,
  `musica` varchar(120) DEFAULT NULL,
  `filme_serie` varchar(120) DEFAULT NULL,
  `time_futebol` varchar(80) DEFAULT NULL,
  `lugar_favorito` varchar(120) DEFAULT NULL,
  `familia` varchar(300) DEFAULT NULL,
  `pets` varchar(200) DEFAULT NULL,
  `pet_foto` varchar(255) DEFAULT NULL,
  `capa` varchar(255) DEFAULT NULL,
  `instagram` varchar(160) DEFAULT NULL,
  `linkedin` varchar(200) DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `frase` varchar(140) DEFAULT NULL,
  `status_emoji` varchar(16) DEFAULT NULL,
  `status_texto` varchar(80) DEFAULT NULL,
  `status_ate` datetime DEFAULT NULL,
  `cor_perfil` varchar(7) DEFAULT NULL,
  `capa_tema` varchar(30) DEFAULT NULL,
  `interesses` varchar(300) DEFAULT NULL,
  `pergunte_sobre` varchar(200) DEFAULT NULL,
  `blocos` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_permissoes` (
  `usuario_id` int(10) unsigned NOT NULL,
  `permissao` varchar(60) NOT NULL,
  `concedida` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`,`permissao`),
  CONSTRAINT `fk_uperm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `perfil_id` int(10) unsigned NOT NULL,
  `codigo_vendedor` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `apelido` varchar(60) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `data_admissao` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `areas_foco` varchar(120) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `trocar_senha` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_login_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `versao_vista` varchar(20) DEFAULT NULL,
  `cor_primaria` varchar(7) DEFAULT NULL,
  `cor_secundaria` varchar(7) DEFAULT NULL,
  `visto_em` datetime DEFAULT NULL,
  `visto_onde` varchar(60) DEFAULT NULL,
  `ramal` varchar(20) DEFAULT NULL,
  `one_balao` tinyint(1) NOT NULL DEFAULT 1,
  `tutorial_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `tema` varchar(10) NOT NULL DEFAULT 'claro',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `codigo_vendedor` (`codigo_vendedor`),
  KEY `fk_usuario_perfil` (`perfil_id`),
  KEY `idx_usuario_aniversario` (`data_nascimento`),
  KEY `idx_usuarios_visto` (`visto_em`),
  CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas_registradas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `data` date NOT NULL,
  `valor` decimal(14,2) NOT NULL,
  `descricao` varchar(200) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vr_usuario_data` (`usuario_id`,`data`),
  CONSTRAINT `fk_vr_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

