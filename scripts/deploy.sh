#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$REPO_ROOT/mariadb_migration/server_credentials.conf"

get_conf() {
  local key="$1"
  awk -F= -v k="$key" '
    $0 ~ /^[[:space:]]*#/ {next}
    NF>=2 {
      key=$1; gsub(/[[:space:]]/,"",key);
      if (key==k) {
        $1=""; sub(/^=/,"");
        gsub(/^[[:space:]]+|[[:space:]]+$/,"",$0);
        print; exit;
      }
    }
  ' "$CONFIG_FILE"
}

SSH_HOST="$(get_conf SERVER_HOST)"
SSH_USER="$(get_conf SERVER_USER)"
SERVER_PATH="$(get_conf SERVER_PATH)"
DOCUMENT_ROOT="$(get_conf DOCUMENT_ROOT)"

if [[ -z "$SSH_HOST" || -z "$SSH_USER" ]]; then
  echo "Fehler: SERVER_HOST oder SERVER_USER fehlt in server_credentials.conf" >&2
  exit 1
fi

if [[ -z "$DOCUMENT_ROOT" ]]; then
  DOCUMENT_ROOT="/www/htdocs/$SSH_USER"
fi

if [[ -n "$SERVER_PATH" ]]; then
  if [[ "$SERVER_PATH" == /www/htdocs/* ]]; then
    REMOTE_DIR="$SERVER_PATH"
  elif [[ "$SERVER_PATH" == /* ]]; then
    REMOTE_DIR="${DOCUMENT_ROOT%/}$SERVER_PATH"
  else
    REMOTE_DIR="${DOCUMENT_ROOT%/}/$SERVER_PATH"
  fi
else
  REMOTE_DIR="$DOCUMENT_ROOT"
fi
REMOTE_DIR="${REMOTE_DIR%/}/"

rsync -avz --delete \
  --exclude ".git/" \
  --exclude "node_modules/" \
  --exclude ".env" \
  --exclude "scripts/" \
  --exclude "sql/" \
  --exclude "docs/" \
  ./ "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}"
