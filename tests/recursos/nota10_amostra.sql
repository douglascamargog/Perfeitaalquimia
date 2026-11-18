-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: localhost    Database: nota10
-- AMOSTRA FICTÍCIA PARA OS TESTES AUTOMÁTICOS DO ONE (estrutura do Nota10, dados inventados)
-- ------------------------------------------------------
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `nota10` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `nota10`;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `usuarios` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  nome VARCHAR(15),
  senha VARCHAR(15),
  Numero INT,
  Telefones VARCHAR(30),
  carteiras VARCHAR(100),
  Ativo SMALLINT,
  Acesso LONGTEXT,
  IdCanal INT,
  Vendedor INT,
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` (`id`,`nome`,`Numero`,`Vendedor`,`Ativo`) VALUES (1,'MARIA VENDAS',1,1,1),(2,'JOAO EXTERNO',2,1,1),(3,'CAIXA',3,0,1);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `apoio`
--

DROP TABLE IF EXISTS `apoio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `apoio` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  codigo INT,
  tabela VARCHAR(15),
  descricao VARCHAR(30),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `apoio` WRITE;
/*!40000 ALTER TABLE `apoio` DISABLE KEYS */;
INSERT INTO `apoio` (`id`,`codigo`,`tabela`,`descricao`) VALUES (1,'7','SEGMENTOS','Farmácia de manipulação'),(2,'8','SEGMENTOS','Loja de produtos naturais');
/*!40000 ALTER TABLE `apoio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `clientes` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  Numero INT,
  transferido SMALLINT,
  TipoCliente VARCHAR(6),
  TipoConsumidor VARCHAR(1),
  Chave_GUID VARCHAR(32),
  Chave VARCHAR(15),
  Nome VARCHAR(60),
  Razao VARCHAR(60),
  Endereco VARCHAR(100),
  EndLog VARCHAR(10),
  EndNum VARCHAR(20),
  EndComp VARCHAR(20),
  Cidade VARCHAR(50),
  Estado VARCHAR(2),
  Bairro VARCHAR(50),
  CEP VARCHAR(9),
  Telefones VARCHAR(30),
  Celular_1 VARCHAR(30),
  Celular_2 VARCHAR(30),
  Fax VARCHAR(30),
  Contato VARCHAR(50),
  Profissao VARCHAR(20),
  CPF_CNPJ VARCHAR(20),
  RG_IE VARCHAR(20),
  InscricaoMunicipal VARCHAR(20),
  TipoContribuinte VARCHAR(1),
  DatNas VARCHAR(10),
  data_SINTEGRA VARCHAR(10),
  Obs VARCHAR(100),
  Marcacao SMALLINT,
  Representante INT,
  Entrada VARCHAR(19),
  Ultima VARCHAR(10),
  Vezes SMALLINT,
  Convenio VARCHAR(15),
  Status SMALLINT,
  Ativo VARCHAR(1),
  MotivoStatus VARCHAR(30),
  Situacao VARCHAR(15),
  dataDeb VARCHAR(10),
  dataPag VARCHAR(10),
  Refer VARCHAR(95),
  Desconto DECIMAL(19,5),
  email VARCHAR(250),
  email_NFe VARCHAR(250),
  email_fin VARCHAR(250),
  Corresp VARCHAR(1),
  Taxa DECIMAL(19,5),
  taxaentrega DECIMAL(19,5),
  PerICMSst DECIMAL(19,5),
  ComissaoVendedor DECIMAL(19,5),
  PodeTerConta SMALLINT,
  WebSite VARCHAR(100),
  SUFRAMA VARCHAR(15),
  Total DECIMAL(19,5),
  CodPais VARCHAR(5),
  NomePais VARCHAR(50),
  CodMunic VARCHAR(7),
  Ocorrencias LONGTEXT,
  CFOP VARCHAR(10),
  Saldo_Pendura DECIMAL(19,5),
  Saldo_Fidelidade DECIMAL(19,5),
  IsentoICMS SMALLINT,
  IsentoIPI SMALLINT,
  CodigoAntigo VARCHAR(10),
  ContatoComplementar_1 VARCHAR(35),
  ContatoComplementar_2 VARCHAR(35),
  TelContatoComplementar_1 VARCHAR(50),
  TelContatoComplementar_2 VARCHAR(50),
  Imagem VARCHAR(50),
  CodigoRegiao INT,
  LimiteCredito DECIMAL(19,5),
  ValorAtraso DECIMAL(19,5),
  DiasAtraso INT,
  IdCanal INT,
  IdSegmento INT,
  Grupo VARCHAR(5),
  Especial SMALLINT,
  Transportadora1 INT,
  Mensalidade DECIMAL(19,5),
  Recorrencia INT,
  entrega_cpf_cnpj VARCHAR(20),
  entrega_razao VARCHAR(50),
  entrega_Endereco VARCHAR(100),
  entrega_EndLog VARCHAR(10),
  entrega_EndNum VARCHAR(20),
  entrega_EndComp VARCHAR(20),
  entrega_Cidade VARCHAR(50),
  entrega_Estado VARCHAR(2),
  entrega_Bairro VARCHAR(50),
  entrega_CEP VARCHAR(9),
  entrega_codmunic VARCHAR(7),
  NumeroMatriz INT,
  CodigoExterno VARCHAR(20),
  GuidExterno VARCHAR(50),
  checked SMALLINT,
  verificado SMALLINT,
  carteira INT,
  segmento1 INT,
  segmento2 INT,
  CST VARCHAR(3),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` (`id`,`Numero`,`TipoCliente`,`Nome`,`Razao`,`CPF_CNPJ`,`RG_IE`,`Endereco`,`EndNum`,`EndComp`,`Bairro`,`Cidade`,`Estado`,`CEP`,`Telefones`,`Celular_1`,`Celular_2`,`Contato`,`email`,`email_NFe`,`email_fin`,`Representante`,`Entrada`,`Ativo`,`LimiteCredito`,`Desconto`,`ComissaoVendedor`,`IdSegmento`,`Obs`,`Ocorrencias`,`entrega_Endereco`,`entrega_EndNum`,`entrega_Cidade`,`entrega_Estado`,`DatNas`) VALUES (1,101,'C','Empório Amostra Um','Empório Amostra Um Ltda','11.222.333/0001-81','0961234567','Rua das Flores','120','Sala 2','Centro','Porto Alegre','RS','90010-000','(51) 3333-1111','(51) 99999-1111','(51) 98888-1111','Ana Compras','contato@amostra1.test','nfe@amostra1.test','fin@amostra1.test',1,'2019-03-10 10:00:00','S',5000,5,3,7,'Entregar pela manhã;','Cliente antigo.\nPrefere boleto 28 dias.','Av. Entrega','50','Canoas','RS','15/08/2005'),(2,102,'C','Empório Amostra Um (duplicado)','Empório Amostra Um Ltda','11222333000181',NULL,NULL,NULL,NULL,NULL,'Porto Alegre','RS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,'S',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,103,'C','D\'Ávila Naturais','D\'Ávila Naturais ME','',NULL,NULL,NULL,NULL,NULL,'Gravataí','RS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'N',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,104,'C','Farmácia Amostra Filial','Farmácia Amostra Filial Ltda','22.333.444/0001-90',NULL,NULL,NULL,NULL,NULL,'Caxias do Sul','RS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,'S',NULL,NULL,NULL,8,'Entregar em: Rua do Depósito, 55 - Caxias do Sul',NULL,NULL,NULL,NULL,NULL,NULL),(5,201,'F','Ervas Fornecedor','Ervas Fornecedor SA','33.444.555/0001-00',NULL,NULL,NULL,NULL,NULL,'Curitiba','PR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,301,'T','Transportes Rápidos','Transportes Rápidos Ltda','44.555.666/0001-11',NULL,NULL,NULL,NULL,NULL,'Porto Alegre','RS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'S',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contatos`
--

DROP TABLE IF EXISTS `contatos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `contatos` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  Sequencia SMALLINT,
  NumeroCliente INT,
  Nome VARCHAR(35),
  Telefone VARCHAR(35),
  Celular VARCHAR(11),
  Email VARCHAR(250),
  Aniversario VARCHAR(4),
  Obs VARCHAR(50),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `contatos` WRITE;
/*!40000 ALTER TABLE `contatos` DISABLE KEYS */;
INSERT INTO `contatos` (`id`,`Sequencia`,`NumeroCliente`,`Nome`,`Celular`,`Email`,`Aniversario`) VALUES (1,1,101,'Ana Compras','51999991111','ana@amostra1.test','1503'),(2,2,101,'Bruno Dono',NULL,NULL,'0212');
/*!40000 ALTER TABLE `contatos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tabelaprecos`
--

DROP TABLE IF EXISTS `tabelaprecos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `tabelaprecos` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  Nome VARCHAR(40),
  Numero INT,
  Ativo SMALLINT,
  Padrao SMALLINT,
  Fixo SMALLINT,
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tabelaprecos` WRITE;
INSERT INTO `tabelaprecos` VALUES (1,0,'LOJISTA',1,1,1,0,'2025-01-01 00:00:00.000000'),(2,0,'DISTRIBUIDOR',2,1,0,0,'2025-01-01 00:00:00.000000');
UNLOCK TABLES;

--
-- Table structure for table `formapgto`
--

DROP TABLE IF EXISTS `formapgto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `formapgto` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  Codigo INT,
  Nome VARCHAR(20),
  FormaNFe INT,
  tPag INT,
  tBand INT,
  CNPJ VARCHAR(20),
  IdentIntermediario VARCHAR(60),
  cor INT,
  DiasVencParcela01 SMALLINT,
  DiasVencParcela02 SMALLINT,
  DiasVencParcela03 SMALLINT,
  DiasVencParcela04 SMALLINT,
  DiasVencParcela05 SMALLINT,
  DiasVencParcela06 SMALLINT,
  DiasVencParcela07 SMALLINT,
  DiasVencParcela08 SMALLINT,
  DiasVencParcela09 SMALLINT,
  DiasVencParcela10 SMALLINT,
  DiasVencParcela11 SMALLINT,
  DiasVencParcela12 SMALLINT,
  PorcentagemParcela01 DECIMAL(19,5),
  PorcentagemParcela02 DECIMAL(19,5),
  PorcentagemParcela03 DECIMAL(19,5),
  PorcentagemParcela04 DECIMAL(19,5),
  PorcentagemParcela05 DECIMAL(19,5),
  PorcentagemParcela06 DECIMAL(19,5),
  PorcentagemParcela07 DECIMAL(19,5),
  PorcentagemParcela08 DECIMAL(19,5),
  PorcentagemParcela09 DECIMAL(19,5),
  PorcentagemParcela10 DECIMAL(19,5),
  PorcentagemParcela11 DECIMAL(19,5),
  PorcentagemParcela12 DECIMAL(19,5),
  NumeroParcelas SMALLINT,
  Ativo SMALLINT,
  ExigirCliente SMALLINT,
  DiasParaCredito SMALLINT,
  TaxaDesconto DECIMAL(19,5),
  GravarMovCaixa SMALLINT,
  GravarContasPagRec SMALLINT,
  CreditoCliente SMALLINT,
  DebitoCliente SMALLINT,
  SangriaReforcoCaixa SMALLINT,
  TeclaAtalho VARCHAR(1),
  CodigoPDV VARCHAR(5),
  DescontoVenda DECIMAL(19,5),
  PerguntarParcelas SMALLINT,
  MaximoParcelas SMALLINT,
  indPres INT,
  indIntermed INT,
  PrazoMaximo SMALLINT,
  teftokenapi VARCHAR(150),
  teftokenjwt VARCHAR(100),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `formapgto` WRITE;
/*!40000 ALTER TABLE `formapgto` DISABLE KEYS */;
INSERT INTO `formapgto` (`id`,`Codigo`,`Nome`,`Ativo`) VALUES (1,1,'BOLETO 28 DD',1),(2,2,'PIX',1);
/*!40000 ALTER TABLE `formapgto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produtos`
--

DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `produtos` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  CodigoProduto VARCHAR(30),
  vCodigoProduto INT,
  transferido SMALLINT DEFAULT 0,
  CodigoBarras VARCHAR(13),
  Nome VARCHAR(120),
  Fabricante VARCHAR(30),
  Resumo VARCHAR(20),
  cor INT,
  Grupo INT,
  SubGrupo INT,
  Custo DECIMAL(19,5) DEFAULT 0,
  CustoExtra DECIMAL(19,5) DEFAULT 0,
  CustoVariavelPercentual DECIMAL(19,5) DEFAULT 0,
  CustoVariavelValor DECIMAL(19,5) DEFAULT 0,
  CustoTotal DECIMAL(19,5) DEFAULT 0,
  Quant DECIMAL(19,5) DEFAULT 0,
  Minimo INT DEFAULT 0,
  Pesar SMALLINT DEFAULT 0,
  Fator DECIMAL(19,5),
  UltimoFornecedor INT DEFAULT 0,
  UltimoICMS DECIMAL(19,5),
  UltimoIPI DECIMAL(19,5),
  UltimaQuantCompra DECIMAL(19,5),
  dataUltimaCompra VARCHAR(10),
  dataUltimoInventario VARCHAR(10),
  UltimoPrecoCompra DECIMAL(19,5),
  BaixarPro SMALLINT DEFAULT 1,
  PesoBruto DECIMAL(19,5),
  PesoLiquido DECIMAL(19,5),
  PodeFracionar SMALLINT DEFAULT 1,
  PerguntarPreco SMALLINT DEFAULT 0,
  Aliquota_ICMS DECIMAL(19,5),
  CustoST DECIMAL(19,5),
  cod_iss VARCHAR(5),
  produtocorvendabalcao INT,
  RedutorBcIcms DECIMAL(19,5),
  Aliquota_IPI DECIMAL(19,5),
  dataUsuarioCadastro VARCHAR(10),
  dataUsuarioAlteracao VARCHAR(10),
  UsuarioCadastro INT,
  UsuarioAlteracao INT,
  ImprimirNaConta SMALLINT DEFAULT 1,
  ConsiderarIngrediente SMALLINT DEFAULT 0,
  ConsiderarObservacao SMALLINT DEFAULT 0,
  ConsiderarServico SMALLINT DEFAULT 0,
  ConsiderarCombo SMALLINT DEFAULT 0,
  ConsiderarTerceiro SMALLINT DEFAULT 0,
  Ativo SMALLINT DEFAULT 1,
  ForaDeLista SMALLINT DEFAULT 0,
  UnidadeCompra VARCHAR(8),
  UnidadeVenda VARCHAR(8),
  Escala DECIMAL(19,5),
  Validade VARCHAR(10),
  Imagem VARCHAR(30),
  Componentes INT,
  Fideliza SMALLINT DEFAULT 1,
  Obs LONGTEXT,
  Prioridade INT,
  NCM VARCHAR(8),
  CEST VARCHAR(7),
  codigofornecedor VARCHAR(30),
  precominimo DECIMAL(19,5),
  TipoItem INT,
  origem INT,
  AliquotaIBPT DECIMAL(19,5),
  FCP SMALLINT DEFAULT 0,
  CodigoInterno VARCHAR(15),
  indEscala VARCHAR(1),
  cnpjfab VARCHAR(20),
  CodProdutoANP VARCHAR(9),
  DescProdutoANP VARCHAR(95),
  cProdAnvisa VARCHAR(13),
  CFOP VARCHAR(4),
  CST VARCHAR(3),
  cClassTrib VARCHAR(6),
  IdCanal INT,
  IdExterno INT,
  IdExterno2 INT,
  Seq_Alteracao SMALLINT,
  Marcacao SMALLINT,
  Imposto VARCHAR(4),
  cIndOp VARCHAR(6),
  pISS DECIMAL(19,5),
  CodTribNac VARCHAR(6),
  CodigoNBS VARCHAR(9),
  dataCadastro timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `produtos` WRITE;
/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
INSERT INTO `produtos` (`id`,`CodigoProduto`,`Nome`,`CodigoBarras`,`NCM`,`Custo`,`Minimo`,`Ativo`,`Grupo`) VALUES (1,'PA001','Colágeno Amostra 300g','7890000000011','21069030',20.5,10,1,1),(2,'PA999','Produto Antigo Amostra',NULL,NULL,NULL,NULL,0,NULL);
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `precos`
--

DROP TABLE IF EXISTS `precos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `precos` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  Id_Tabela INT,
  transferido SMALLINT,
  CodigoProduto VARCHAR(30),
  Seq_Alteracao SMALLINT,
  ValorPreco DECIMAL(19,5),
  ValorLiquido DECIMAL(19,5),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `precos` WRITE;
/*!40000 ALTER TABLE `precos` DISABLE KEYS */;
INSERT INTO `precos` (`id`,`Id_Tabela`,`CodigoProduto`,`ValorPreco`) VALUES (1,1,'PA001',49.9),(2,2,'PA001',39.9);
/*!40000 ALTER TABLE `precos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notas`
--

DROP TABLE IF EXISTS `notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `notas` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  GUID_Nota VARCHAR(32),
  Pedido VARCHAR(10),
  vPedido INT,
  Data VARCHAR(10),
  Hora VARCHAR(8),
  NumeroCliente INT,
  transferido SMALLINT,
  CFOP VARCHAR(5),
  Emitente VARCHAR(20),
  IdCanal INT,
  CPF_CNPJ VARCHAR(20),
  NaturezaOperacao VARCHAR(50),
  dataSaida VARCHAR(19),
  dataEntrega VARCHAR(10),
  Nota_E INT,
  Nota_S INT,
  Modelo VARCHAR(3),
  Serie INT,
  Terminal VARCHAR(20),
  Pessoas INT,
  Gaveta SMALLINT,
  Usuario INT,
  Entregador INT,
  ComissaoVendedor DECIMAL(19,5),
  TipoFreteEmitente VARCHAR(1),
  origem INT,
  CodigoVendedor INT,
  CodigoTransportadora INT,
  TipoOperacao VARCHAR(1),
  TotalMercadoria DECIMAL(19,5),
  TotalDesconto DECIMAL(19,5),
  TotalDescontoItens DECIMAL(19,5),
  DescontoPercentual DECIMAL(19,5),
  BaseCalculoICMS DECIMAL(19,5),
  BaseCalculo_ST DECIMAL(19,5),
  BaseCalculo_IPI DECIMAL(19,5),
  ValorICMS DECIMAL(19,5),
  Valor_ST DECIMAL(19,5),
  ValorServico DECIMAL(19,5),
  Percentual_ISS DECIMAL(19,5),
  Percentual_ICMS DECIMAL(19,5),
  Valor_ISS DECIMAL(19,5),
  OutrasDespesas DECIMAL(19,5),
  TotalNota DECIMAL(19,5),
  FormaPgto INT,
  TrocoPara DECIMAL(19,5),
  TrocoEnviado DECIMAL(19,5),
  ValorFrete DECIMAL(19,5),
  ValorSeguroEnvio DECIMAL(19,5),
  ValorIPI DECIMAL(19,5),
  Base_IPI DECIMAL(19,5),
  Valor_II DECIMAL(19,5),
  BaseII DECIMAL(19,5),
  Impressoes SMALLINT,
  Obs LONGTEXT,
  ObsInterna LONGTEXT,
  ValorPago DECIMAL(19,5),
  UF_Destino VARCHAR(2),
  Turno SMALLINT,
  Itens SMALLINT,
  Codigo_CRT INT,
  data_NF VARCHAR(10),
  Valor_PIS DECIMAL(19,5),
  Base_PIS DECIMAL(19,5),
  Valor_COFINS DECIMAL(19,5),
  Base_COFINS DECIMAL(19,5),
  Descricao LONGTEXT,
  NaoAutoRecalcular SMALLINT,
  PedidoCancelado SMALLINT,
  TotalAcrescimo DECIMAL(19,5),
  Volumes SMALLINT,
  Especie VARCHAR(20),
  Marca VARCHAR(20),
  Numeracao VARCHAR(20),
  PesoLiquido DECIMAL(19,5),
  PesoBruto DECIMAL(19,5),
  TabelaPrecoVenda INT,
  TotalPecas DECIMAL(19,5),
  AlterarEstoque SMALLINT,
  TotalCustoExtra DECIMAL(19,5),
  Prevenda INT,
  HoraNota VARCHAR(5),
  Status_NF VARCHAR(1),
  PrefNFE VARCHAR(50),
  ArquivoXML VARCHAR(100),
  ArquivoPDF VARCHAR(100),
  dataAutorizacao VARCHAR(20),
  data_Autoriza_NFE VARCHAR(50),
  ProtocoloCancela_NFE VARCHAR(20),
  Seq_Alteracao SMALLINT,
  Autorizacao VARCHAR(20),
  dataAutoriza_Canc VARCHAR(20),
  XML LONGTEXT,
  NotaComplementar SMALLINT,
  fechamento VARCHAR(20),
  fechamentodia VARCHAR(20),
  RefChaveNFe VARCHAR(50),
  RefNota INT,
  IpiNaBase INT,
  DifalBaseDupla INT,
  DifalPorDentro INT,
  StReduzAntesMVA INT,
  ValorDifal DECIMAL(19,5),
  vFCPUFDest DECIMAL(19,5),
  vICMSUFDest DECIMAL(19,5),
  vICMSUFRemet DECIMAL(19,5),
  vTotTrib DECIMAL(19,5),
  pTotTrib DECIMAL(19,5),
  vICMSDeson DECIMAL(19,5),
  vICMSSubstituto DECIMAL(19,5),
  vICMSSTRet DECIMAL(19,5),
  vICMSSTDest DECIMAL(19,5),
  vFCP DECIMAL(19,5),
  vFCPST DECIMAL(19,5),
  vFCPSTRet DECIMAL(19,5),
  vIPIDevol DECIMAL(19,5),
  Editor VARCHAR(30),
  DiNumero VARCHAR(12),
  DiDataRegistro VARCHAR(10),
  DiLocal VARCHAR(50),
  DiUf VARCHAR(2),
  DiDataDesembaraco VARCHAR(10),
  DiNumeroAdicao SMALLINT,
  DiSequencia SMALLINT,
  vCredICMSSN DECIMAL(19,5),
  DiViaTransp SMALLINT,
  DiAFRMM DECIMAL(19,5),
  DiIntermedio SMALLINT,
  CodigoRastreio VARCHAR(100),
  CodigoExterno VARCHAR(50),
  ReferenciaExterna VARCHAR(50),
  Marcacao SMALLINT,
  NumeroRomaneio INT,
  StatusPedido VARCHAR(1),
  StatusEdicao VARCHAR(50),
  externo_id VARCHAR(30),
  verificado SMALLINT,
  dataAlteracaoItens VARCHAR(19),
  finNFe INT,
  cMunFG VARCHAR(7),
  cMunFGIBS VARCHAR(7),
  tpNFDebito INT,
  tpNFCredito INT,
  tpEnteGov INT,
  pRedutor DECIMAL(19,5),
  tpOperGov INT,
  vBCIBSCBS DECIMAL(19,5),
  vIS DECIMAL(19,5),
  vIBSUF DECIMAL(19,5),
  vIBSdif DECIMAL(19,5),
  vIBSMun DECIMAL(19,5),
  vIBS DECIMAL(19,5),
  vCBS DECIMAL(19,5),
  vCredPresIBS DECIMAL(19,5),
  vCredPresCondSusIBS DECIMAL(19,5),
  vCredPresCBS DECIMAL(19,5),
  vICMSPrevisto DECIMAL(19,5),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

INSERT INTO `notas` (`id`, `GUID_Nota`, `Pedido`, `Data`, `NumeroCliente`, `CFOP`, `CodigoVendedor`, `TotalNota`, `Nota_S`, `Serie`, `PedidoCancelado`, `Status_NF`, `StatusPedido`, `ValorFrete`, `NaturezaOperacao`) VALUES
	(1, 'g0000000000000000000000000000001', '5001', '2024-05-10', 101, '5102', 1, 1000, 12001, '1', 0, 'A', 'F', 35.5, 'Venda de mercadoria'),
	(2, 'g0000000000000000000000000000002', '5002', '15/02/2025', 104, '6102', 2, 2000, 12002, NULL, 0, 'A', 'F', NULL, NULL),
	(3, 'g0000000000000000000000000000003', '5003', '2025-06-01', 101, '5910', 1, 300, NULL, NULL, 0, 'A', 'F', NULL, NULL),
	(4, 'g0000000000000000000000000000004', '5004', '2025-07-01', 999, '5102', 3, 50, NULL, NULL, 0, NULL, NULL, NULL, NULL),
	(5, 'g0000000000000000000000000000005', '5005', '2025-08-01', 104, '5102', 2, 700, NULL, NULL, 1, NULL, 'C', NULL, NULL);

--
-- Table structure for table `itensnota`
--

DROP TABLE IF EXISTS `itensnota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `itensnota` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  GUID_Nota VARCHAR(32),
  GUID_Item VARCHAR(32),
  transferido SMALLINT,
  NumeroItem INT,
  CodigoProduto VARCHAR(30),
  ValorUnitario DECIMAL(19,5),
  ValorTotalItem DECIMAL(19,5),
  ValorDesconto DECIMAL(19,5),
  ValorDescontoProRata DECIMAL(19,5),
  ValorFreteProRata DECIMAL(19,5),
  ValorSeguroProRata DECIMAL(19,5),
  ValorDespesaProRata DECIMAL(19,5),
  Quantidade DECIMAL(19,5),
  PorcentagemICMS DECIMAL(19,5),
  ValorICMS DECIMAL(19,5),
  BaseICMS DECIMAL(19,5),
  Percentual_IPI DECIMAL(19,5),
  ValorIPI DECIMAL(19,5),
  Base_IPI DECIMAL(19,5),
  CSTIPI VARCHAR(2),
  CENQIPI VARCHAR(3),
  CodigoVendedor INT,
  PER_PIS DECIMAL(19,5),
  CST_PIS VARCHAR(2),
  Valor_PIS DECIMAL(19,5),
  Base_PIS DECIMAL(19,5),
  BaseII DECIMAL(19,5),
  Valor_II DECIMAL(19,5),
  PER_II DECIMAL(19,5),
  RedBcII DECIMAL(19,5),
  PER_COFINS DECIMAL(19,5),
  CST_COFINS VARCHAR(2),
  Base_COFINS DECIMAL(19,5),
  Valor_COFINS DECIMAL(19,5),
  RedutorBCICMS DECIMAL(19,5),
  IVA DECIMAL(19,5),
  comanda INT,
  IncideServico SMALLINT,
  IncideDesconto SMALLINT,
  ValorServico DECIMAL(19,5),
  CST VARCHAR(3),
  CSOSN VARCHAR(3),
  PerICMSST DECIMAL(19,5),
  BaseST DECIMAL(19,5),
  ValorST DECIMAL(19,5),
  CFOP VARCHAR(5),
  CustoProduto DECIMAL(19,5),
  CustoExtra DECIMAL(19,5),
  Cancelado SMALLINT,
  DescricaoProduto VARCHAR(120),
  Obs LONGTEXT,
  Unidade VARCHAR(8),
  PesoBruto DECIMAL(19,5),
  PesoLiquido DECIMAL(19,5),
  xPed VARCHAR(15),
  nItemPed INT,
  Seq_Alteracao SMALLINT,
  FCI VARCHAR(36),
  pCredSN DECIMAL(19,5),
  vCredICMSSN DECIMAL(19,5),
  vTotTrib DECIMAL(19,5),
  pTotTrib DECIMAL(19,5),
  vICMSDeson DECIMAL(19,5),
  DiNumeroAdicao SMALLINT,
  DiSequencia SMALLINT,
  ModBC VARCHAR(1),
  ModBCST VARCHAR(1),
  MotDesICMS INT,
  vICMSSTDeson DECIMAL(19,5),
  motDesICMSST INT,
  pFcpUfDest DECIMAL(19,5),
  vFcpUfDest DECIMAL(19,5),
  vbcFCPUFDest DECIMAL(19,5),
  ValorDifal DECIMAL(19,5),
  vBcUfDest DECIMAL(19,5),
  pIcmsInterPart INT,
  vICMSUFDest DECIMAL(19,5),
  vICMSUFRemet DECIMAL(19,5),
  vICMSSubstituto DECIMAL(19,5),
  pST DECIMAL(19,5),
  vBCSTRet DECIMAL(19,5),
  vICMSSTRet DECIMAL(19,5),
  vBCSTDest DECIMAL(19,5),
  vICMSSTDest DECIMAL(19,5),
  vBCFCPST DECIMAL(19,5),
  pFCPST DECIMAL(19,5),
  vFCPST DECIMAL(19,5),
  vIcmsOp DECIMAL(19,5),
  pDif DECIMAL(19,5),
  vIcmsDif DECIMAL(19,5),
  vBCFCP DECIMAL(19,5),
  pFCP DECIMAL(19,5),
  vFCP DECIMAL(19,5),
  pRedBCST DECIMAL(19,5),
  vBCFCPSTRet DECIMAL(19,5),
  pFCPSTRet DECIMAL(19,5),
  pRedBCEfet DECIMAL(19,5),
  vBCEfet DECIMAL(19,5),
  pICMSEfet DECIMAL(19,5),
  vICMSEfet DECIMAL(19,5),
  pBCOp DECIMAL(19,5),
  vFCPSTRet DECIMAL(19,5),
  Lote VARCHAR(40),
  Validade VARCHAR(10),
  datafabricacao VARCHAR(10),
  ItensNoLote DECIMAL(19,5),
  cBenef VARCHAR(10),
  ncm VARCHAR(10),
  vIPIDevol DECIMAL(19,5),
  pIPIDevol DECIMAL(19,5),
  CSTIS VARCHAR(3),
  cClassTribIS VARCHAR(6),
  vBCIS DECIMAL(19,5),
  pIS DECIMAL(19,5),
  pISEspec DECIMAL(19,5),
  uTribIS VARCHAR(6),
  qTribIS DECIMAL(19,5),
  vIS DECIMAL(19,5),
  CST_IBSCBS VARCHAR(3),
  cClassTribIBSCBS VARCHAR(6),
  vBcIBSCBS DECIMAL(19,5),
  pIBSUF DECIMAL(19,5),
  vIBSUF DECIMAL(19,5),
  pIBSdif DECIMAL(19,5),
  vIBSdif DECIMAL(19,5),
  pAliqEfet DECIMAL(19,5),
  pRedAliqUF DECIMAL(19,5),
  vRedAliqUF DECIMAL(19,5),
  pAliqEfetUF DECIMAL(19,5),
  pRedAliqMun DECIMAL(19,5),
  vRedAliqMun DECIMAL(19,5),
  pAliqEfetMun DECIMAL(19,5),
  pIBSMun DECIMAL(19,5),
  vIBSMun DECIMAL(19,5),
  vIBS DECIMAL(19,5),
  vBCCBS DECIMAL(19,5),
  pCBS DECIMAL(19,5),
  vCBS DECIMAL(19,5),
  cCredPresIBS VARCHAR(2),
  vCredPresCondSusIBS DECIMAL(19,5),
  pCredPresIBS DECIMAL(19,5),
  vCredPresIBS DECIMAL(19,5),
  pCredPresCBS DECIMAL(19,5),
  vCredPresCBS DECIMAL(19,5),
  indDeduzDeson INT,
  indBemMovelUsado INT,
  nve LONGTEXT,
  pFCPDif DECIMAL(19,5),
  vFCPDif DECIMAL(19,5),
  vFCPEfet DECIMAL(19,5),
  vUnComLiq DECIMAL(19,5),
  vProdLiq DECIMAL(19,5),
  ValorTotalItemFinal DECIMAL(19,5),
  refnfe VARCHAR(50),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `itensnota` WRITE;
/*!40000 ALTER TABLE `itensnota` DISABLE KEYS */;
INSERT INTO `itensnota` (`id`,`GUID_Nota`,`GUID_Item`,`NumeroItem`,`CodigoProduto`,`DescricaoProduto`,`Quantidade`,`ValorUnitario`,`ValorTotalItem`,`CustoProduto`,`Lote`,`Validade`,`CFOP`,`ncm`) VALUES (1,'g0000000000000000000000000000001','i1',1,'PA001','COLAGENO 300G',10,49.9,499,20.5,'L2405','2026-05-01','5102','21069030'),(2,'g0000000000000000000000000000001','i2',2,'PA999','PRODUTO ANTIGO',5,100.2,501,NULL,NULL,NULL,NULL,NULL),(3,'g0000000000000000000000000000002','i3',1,'XX123','ITEM SEM CADASTRO',20,100,2000,NULL,NULL,NULL,NULL,NULL),(4,'gzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz','i4',1,'PA001',NULL,1,1,1,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `itensnota` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contaspagrec`
--

DROP TABLE IF EXISTS `contaspagrec`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `contaspagrec` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  Emitente VARCHAR(20),
  DataVencimento VARCHAR(10),
  DataLancamento VARCHAR(19),
  dataPagamento VARCHAR(10),
  dataCancelamento VARCHAR(10),
  dataConciliacao VARCHAR(10),
  Tipo VARCHAR(1),
  ValorBruto DECIMAL(19,5),
  Desconto DECIMAL(19,5),
  Acrescimo DECIMAL(19,5),
  ValorLiquido DECIMAL(19,5),
  CustoTaxaBancaria DECIMAL(19,5),
  Titulo VARCHAR(10),
  NumeroParcela INT,
  Historico VARCHAR(50),
  Centro VARCHAR(10),
  Documento VARCHAR(20),
  FormaPgto SMALLINT,
  NossoNumero DECIMAL(19,5),
  NossoNumeroDV INT,
  Cancelado SMALLINT,
  StatusBanco SMALLINT,
  ChaveGUID VARCHAR(32),
  CredorDevedor INT,
  GUID_Nota VARCHAR(32),
  dataEnvioBanco VARCHAR(10),
  SiglaConta  VARCHAR(10),
  SaldoFinal DECIMAL(19,5),
  Usuario INT,
  UsuarioAlteracao INT,
  origem INT,
  Terminal VARCHAR(20),
  MensagemRetorno VARCHAR(30),
  ArquivoBoleto VARCHAR(100),
  Seq_Alteracao SMALLINT,
  vIBSMunCalculado DECIMAL(19,5),
  vIBSUFCalculado DECIMAL(19,5),
  vCBSCalculado DECIMAL(19,5),
  vIBSMunRetido DECIMAL(19,5),
  vIBSUFRetido DECIMAL(19,5),
  vCBSRetido DECIMAL(19,5),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `contaspagrec` WRITE;
/*!40000 ALTER TABLE `contaspagrec` DISABLE KEYS */;
INSERT INTO `contaspagrec` (`id`,`ChaveGUID`,`Tipo`,`CredorDevedor`,`Documento`,`NumeroParcela`,`DataLancamento`,`DataVencimento`,`dataPagamento`,`ValorBruto`,`ValorLiquido`,`GUID_Nota`,`Cancelado`) VALUES (1,'t1','R',101,'12001',1,'2024-05-10','2024-06-07','2024-06-10',1000,1000,'g0000000000000000000000000000001',0),(2,'t2','R',104,'12002',1,'2025-02-15','2025-03-15',NULL,2000,2000,'g0000000000000000000000000000002',0),(3,'t3','P',201,'NF-E 555',NULL,NULL,'2025-03-01',NULL,800,800,NULL,0);
/*!40000 ALTER TABLE `contaspagrec` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `followup`
--

DROP TABLE IF EXISTS `followup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `followup` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  Pedido VARCHAR(10),
  data VARCHAR(19),
  cliente INT,
  proximocontato VARCHAR(19),
  usuario INT,
  cor INT,
  resumo VARCHAR(120),
  datafechamento VARCHAR(19),
  detalhes LONGTEXT,
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `followup` WRITE;
/*!40000 ALTER TABLE `followup` DISABLE KEYS */;
INSERT INTO `followup` (`id`,`Pedido`,`data`,`cliente`,`proximocontato`,`usuario`,`resumo`,`datafechamento`,`detalhes`) VALUES (1,'','2025-05-02 09:00:00',101,'2099-01-10 10:00:00',1,'Ligar para oferecer o colágeno novo','','Pediu catálogo; vai comprar no mês que vem.'),(2,NULL,'2024-01-02 09:00:00',104,'2024-01-10 10:00:00',2,'Retorno feito','2024-01-10 11:00:00',NULL);
/*!40000 ALTER TABLE `followup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comandas`
--

DROP TABLE IF EXISTS `comandas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
CREATE TABLE `comandas` (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transferido SMALLINT,
  comanda INT,
  CodigoProduto VARCHAR(30),
  PrecoUnitario DECIMAL(19,5),
  dataHora VARCHAR(19),
  Quantidade DECIMAL(19,5),
  TerminalFechamento VARCHAR(20),
  Obs VARCHAR(50),
  ChaveGUID VARCHAR(32),
  Usuario INT,
  Nometerminal VARCHAR(20),
  dataAlteracao timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `comandas` WRITE;
/*!40000 ALTER TABLE `comandas` DISABLE KEYS */;
INSERT INTO `comandas` (`id`,`comanda`,`CodigoProduto`,`PrecoUnitario`,`Quantidade`) VALUES (1,10,'PA001',10,1);
/*!40000 ALTER TABLE `comandas` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `tr_comandas` BEFORE INSERT ON `comandas` FOR EACH ROW BEGIN
  SET NEW.Obs = 'x';
END */;;
DELIMITER ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
-- Dump completed on 2026-11-18 10:00:00
