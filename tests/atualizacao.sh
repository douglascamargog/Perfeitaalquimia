#!/usr/bin/env bash
# =====================================================================
# Testa a ATUALIZAÇÃO sobre uma cópia de um banco em uso (AMBIENTE DE TESTE, nunca em produção).
#   bash tests/atualizacao.sh /caminho/backup.sql[.gz]            → aplica pelo terminal
#   bash tests/atualizacao.sh /caminho/backup.sql[.gz] --visita   → como na hospedagem: só abre o site (precisa do php -S rodando)
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
if [ "${2:-}" = "--visita" ]; then
  # Como na hospedagem: só os arquivos novos, sem "Aplicar". A 1ª visita ao site aplica sozinha (com backup).
  rm -f storage/cache/banco-em-dia.txt
  CODIGO=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/entrar)
  echo "1ª visita: HTTP $CODIGO"; [ "$CODIGO" = "200" ] || { echo "ATENÇÃO: a 1ª visita falhou."; exit 1; }
  PEND=$(php -r 'require "app/bootstrap.php"; $p = (new App\Core\Instalador())->pendentes(false); echo count($p["migrations"]) + count($p["seeds"]);')
  [ "$PEND" = "0" ] && echo "OK: banco atualizado sozinho na 1ª visita." || { echo "ATENÇÃO: ainda há $PEND pendência(s)."; exit 1; }
else
  php database/migrar.php --atualizacao
fi
DEPOIS=$(contar)
echo "clientes/usuários/eventos/tarefas — antes: $ANTES · depois: $DEPOIS"
[ "$ANTES" = "$DEPOIS" ] && echo "OK: nenhum dado perdido." || { echo "ATENÇÃO: os números mudaram."; exit 1; }
