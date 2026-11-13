#!/usr/bin/env bash
# =====================================================================
# Testa a ATUALIZAÇÃO sobre uma cópia de um banco em uso (AMBIENTE DE TESTE, nunca em produção).
#   bash tests/atualizacao.sh /caminho/backup.sql[.gz]
# Restaura o backup no banco "perfeita", aplica as migrations/seeds pendentes (sem dados de demonstração)
# e confere que nada foi perdido (clientes, usuários, eventos e pedidos antes = depois).
# =====================================================================
set -eu
cd "$(dirname "$0")/.."
DUMP=${1:?Informe o arquivo de backup}
mysql -uroot -e "DROP DATABASE IF EXISTS perfeita; CREATE DATABASE perfeita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if [[ "$DUMP" == *.gz ]]; then gunzip -c "$DUMP" | mysql -uroot perfeita; else mysql -uroot perfeita < "$DUMP"; fi
contar() { mysql -uroot perfeita -N -e "SELECT CONCAT((SELECT COUNT(*) FROM clientes),'/',(SELECT COUNT(*) FROM usuarios),'/',(SELECT COUNT(*) FROM cliente_eventos),'/',(SELECT COUNT(*) FROM tarefas))"; }
ANTES=$(contar)
php database/migrar.php --atualizacao
DEPOIS=$(contar)
echo "clientes/usuários/eventos/tarefas — antes: $ANTES · depois: $DEPOIS"
[ "$ANTES" = "$DEPOIS" ] && echo "OK: nenhum dado perdido." || { echo "ATENÇÃO: os números mudaram."; exit 1; }
