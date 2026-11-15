# Teste de carga (30 pessoas ao mesmo tempo)

Só em ambiente de TESTE (apaga e recria o banco "perfeita"). Nunca no servidor de produção.

```bash
# 1. banco de demonstração + usuários até completar 30
mysql -e "DROP DATABASE IF EXISTS perfeita; CREATE DATABASE perfeita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php database/migrar.php && php tests/carga/mais_usuarios.php
# 2. (opcional) banco "crescido": ~20 mil clientes, ~150 mil eventos, ~15 mil pedidos
php tests/carga/crescer_banco.php
# 3. servidor com vários processos (como uma hospedagem) e o teste
PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8000 -t public public/index.php &
node tests/carga/carga.js 180 5 15 normal.json   # dia normal: uma tela a cada 5–15 s por pessoa
node tests/carga/carga.js 120 0 0.3 pico.json    # pico: todo mundo clicando sem parar
```

O resultado mostra, por tela: quantos acessos, mediana, 95% (p95) e o pior tempo, em milissegundos, e os erros.
