#!/usr/bin/env bash
# =====================================================================
# Gera o .zip para enviar à hospedagem (HostGator ou servidor próprio).
#
#   bash ferramentas/gerar_pacote.sh                  → pacote de INSTALAÇÃO (primeira vez)
#   bash ferramentas/gerar_pacote.sh --atualizacao    → pacote de ATUALIZAÇÃO (sistema já em uso)
#   (opcional: pasta de saída como último argumento)
#
# Pacote de ATUALIZAÇÃO: só o código (+ vendor/). NÃO leva config.php, referencias/ nem mídias,
# então extrair por cima na hospedagem não mexe nas senhas nem nos dados.
#
# O pacote de INSTALAÇÃO leva:
#   - o código do sistema (o que está no git) + vendor/ (SDK da Anthropic, via composer)
#   - a pasta referencias/ (Carteira V4 e Central de Televendas, usadas na instalação)
#   - config/config.php PRONTO PARA EDITAR (só preencher os dados do banco) com um token de instalação novo
# NÃO leva: .git, logs, config.php do seu computador.
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/.."
TIPO=instalacao
if [ "${1:-}" = "--atualizacao" ]; then TIPO=atualizacao; shift; fi
SAIDA="${1:-$(pwd)/pacote}"
DATA=$(date +%Y-%m-%d)
VERSAO="$(date +%Y.%m.%d)-$(git rev-parse --short HEAD)"
TMP=$(mktemp -d)
DESTINO="$TMP/one"
mkdir -p "$DESTINO"

# 1. Código (somente arquivos versionados no git)
git archive HEAD | tar -x -C "$DESTINO"

# Biblioteca da IA (SDK oficial da Anthropic) — instalada aqui, vai pronta dentro do zip
# (na hospedagem não é preciso rodar nada). Sem o composer, o zip sai sem ela e só a busca real fica indisponível.
if command -v composer >/dev/null 2>&1; then
  (cd "$DESTINO" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet)
  find "$DESTINO/vendor" -type d \( -name .git -o -name tests -o -name Tests -o -name docs \) -prune -exec rm -rf {} +
else
  echo "Aviso: composer não encontrado — o pacote sai sem vendor/ (a busca de contatos real com IA não vai funcionar)."
fi

# Número da versão (aparece no rodapé do menu e em Administração › Atualização)
echo "$VERSAO" > "$DESTINO/VERSAO"

if [ "$TIPO" = "atualizacao" ]; then
  rm -f "$DESTINO/config/config.php"   # nunca sobrescrever as senhas da hospedagem
  mkdir -p "$SAIDA"
  ARQUIVO="$SAIDA/one-atualizacao-$VERSAO.zip"
  rm -f "$ARQUIVO"
  (cd "$TMP" && zip -qr "$ARQUIVO" one)
  echo "Pacote de ATUALIZAÇÃO: $ARQUIVO ($(du -h "$ARQUIVO" | cut -f1)) · versão $VERSAO"
  rm -rf "$TMP"
  exit 0
fi

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
ARQUIVO="$SAIDA/one-instalacao-$VERSAO.zip"
rm -f "$ARQUIVO"
(cd "$TMP" && zip -qr "$ARQUIVO" one)
echo "Pacote de INSTALAÇÃO: $ARQUIVO ($(du -h "$ARQUIVO" | cut -f1)) · versão $VERSAO"
echo "Token de instalação: $TOKEN"
rm -rf "$TMP"
