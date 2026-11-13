#!/usr/bin/env bash
# =====================================================================
# Roda os testes automáticos do One (Playwright) num AMBIENTE DE TESTE.
# ATENÇÃO: apaga e recria o banco "perfeita" antes de cada teste. Nunca rode no servidor de produção.
#
#   bash tests/rodar.sh              → todos os testes
#   bash tests/rodar.sh pedidos one  → só os testes escolhidos (nome sem "teste_" e sem ".js")
#
# Precisa de: PHP 8, MySQL/MariaDB (usuário root sem senha no ambiente de teste), Node e Playwright.
# Os testes usam o horário de Brasília (o mesmo do sistema).
# =====================================================================
set -u
cd "$(dirname "$0")/.."
BANCO=perfeita
mkdir -p tests/saida

# Servidor do PHP (sobe se não estiver rodando)
if ! curl -s -o /dev/null http://127.0.0.1:8000/entrar; then
  nohup php -S 127.0.0.1:8000 -t public public/index.php > tests/saida/php.log 2>&1 &
  sleep 1
fi

# Banco e testes no mesmo fuso do sistema (America/Sao_Paulo)
mysql -uroot -e "SET GLOBAL time_zone='-03:00'"
export TZ=America/Sao_Paulo

recriar_banco() {
  rm -f storage/omie_fake/pedidos.json
  mysql -uroot -e "DROP DATABASE IF EXISTS $BANCO; CREATE DATABASE $BANCO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  php database/migrar.php > tests/saida/instalacao.log 2>&1
}

if [ $# -gt 0 ]; then LISTA="$*"; else LISTA=$(ls tests/teste_*.js | sed 's#tests/teste_##; s#\.js$##'); fi
FALHAS=0
for t in $LISTA; do
  recriar_banco
  if node "tests/teste_$t.js" tests/saida > "tests/saida/$t.txt" 2>&1; then
    printf "%-12s ok  (%s verificações)\n" "$t" "$(grep -c '^OK' "tests/saida/$t.txt")"
  else
    printf "%-12s FALHOU — veja tests/saida/%s.txt\n" "$t" "$t"
    grep -E "FALHA|Error" "tests/saida/$t.txt" | head -5 | sed 's/^/             /'
    FALHAS=$((FALHAS + 1))
  fi
done

mysql -uroot -e "SET GLOBAL time_zone='SYSTEM'"
recriar_banco   # deixa o ambiente com a demonstração limpa
[ $FALHAS -eq 0 ] && echo "Tudo certo." || echo "$FALHAS teste(s) com falha."
exit $FALHAS
