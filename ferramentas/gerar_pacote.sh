#!/usr/bin/env bash
# =====================================================================
# Gera o .zip para enviar à hospedagem (HostGator ou servidor próprio).
#
#   bash ferramentas/gerar_pacote.sh  [pasta-de-saida]
#
# O pacote leva:
#   - o código do sistema (o que está no git)
#   - a pasta referencias/ (Carteira V4 e Central de Televendas, usadas na instalação)
#   - config/config.php PRONTO PARA EDITAR (só preencher os dados do banco) com um token de instalação novo
# NÃO leva: .git, logs, config.php do seu computador.
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/.."
SAIDA="${1:-$(pwd)/pacote}"
DATA=$(date +%Y-%m-%d)
TMP=$(mktemp -d)
DESTINO="$TMP/one"
mkdir -p "$DESTINO"

# 1. Código (somente arquivos versionados no git)
git archive HEAD | tar -x -C "$DESTINO"

# 2. Arquivos de referência (não vão para o git por terem dados de clientes)
cp referencias/*.html "$DESTINO/referencias/" 2>/dev/null || echo "Aviso: referencias/*.html não encontrados — a instalação ficará sem as lojas e a Academia."

# 3. config.php para preencher, com token de instalação aleatório
TOKEN=$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)
sed -e "s/'troque-esta-senha'/'COLOQUE-AQUI-A-SENHA-DO-BANCO'/" \
    -e "s/'nome'    => 'perfeita'/'nome'    => 'COLOQUE-AQUI-O-NOME-DO-BANCO'/" \
    -e "s/'usuario' => 'perfeita'/'usuario' => 'COLOQUE-AQUI-O-USUARIO-DO-BANCO'/" \
    -e "s/'instalar_token' => ''/'instalar_token' => '$TOKEN'/" \
    config/config.example.php > "$DESTINO/config/config.php"
echo "$TOKEN" > "$TMP/token.txt"

# 4. Zip
mkdir -p "$SAIDA"
ARQUIVO="$SAIDA/one-perfeita-alquimia-$DATA.zip"
rm -f "$ARQUIVO"
(cd "$TMP" && zip -qr "$ARQUIVO" one)
echo "Pacote: $ARQUIVO ($(du -h "$ARQUIVO" | cut -f1))"
echo "Token de instalação: $TOKEN"
rm -rf "$TMP"
