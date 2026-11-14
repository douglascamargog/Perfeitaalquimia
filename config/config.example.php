<?php
/**
 * MODELO de configuração.
 *
 * Como usar: copie este arquivo para config/config.php e preencha os dados.
 * O config.php fica FORA do git (tem senhas). Este modelo pode ser versionado.
 */
return [
    // Nome do sistema (aparece no menu, no login e nos títulos das páginas)
    'app_nome' => 'One',
    // Nome da empresa (aparece embaixo do nome do sistema)
    'empresa_nome' => 'Perfeita Alquimia',

    // Endereço base do sistema. Deixe '' para detectar sozinho.
    // Exemplo se ficar numa subpasta: '/sistema'
    'url_base' => '',

    // true = mostra erros detalhados na tela (só no seu computador!). Em produção: false.
    'depurar' => false,

    'fuso_horario' => 'America/Sao_Paulo',

    // Banco de dados MySQL/MariaDB
    'banco' => [
        'host'    => 'localhost',
        'porta'   => 3306,
        'nome'    => 'perfeita',
        'usuario' => 'perfeita',
        'senha'   => 'troque-esta-senha',
    ],

    // Ambiente de demonstração: mostra a faixa "dados simulados" e os logins de teste na tela de login
    'modo_demo' => true,

    // Senha para a tela /instalar (cria as tabelas e os dados de demonstração pelo navegador).
    // Deixe '' para DESLIGAR a tela de instalação (recomendado depois de instalar).
    'instalar_token' => '',

    // Integrações externas. 'fake' = dados simulados do protótipo; 'omie' = API real (Fase 2).
    'integracoes' => [
        'erp' => 'fake',
        'omie' => [
            'app_key'    => '',
            'app_secret' => '',
            'url'        => 'https://app.omie.com.br/api/v1/',
        ],
        // Ligue Talk: o modo (manual | simulado | api) fica em Configurações › Ligue Talk.
        // Para o modo "api": dados da API v1 do Ligue Talk (company_id e token X-LTK-VND, pedidos ao Ligue Talk). Nunca no git.
        'ligue_talk' => [
            'url'        => 'https://sistema.liguetalk.com.br/index.php/API_control',
            'company_id' => '',
            'token'      => '',
        ],
        // Busca de contatos com IA (Claude, da Anthropic). Chave criada em console.anthropic.com › API Keys.
        // Com a chave vazia, a busca funciona só no modo demonstração (Configurações › busca_ia.provedor = fake).
        'anthropic' => [
            'chave'  => '',
            'modelo' => 'claude-opus-5-5',
        ],
        // Loja virtual Tray (painel do e-commerce no Marketing). Dados do aplicativo liberado pela Tray.
        'tray' => [
            'api'             => '',   // ex.: https://www.sualoja.com.br/web_api
            'consumer_key'    => '',
            'consumer_secret' => '',
            'code'            => '',
        ],
    ],
];
