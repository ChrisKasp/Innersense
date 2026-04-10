#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHON_BIN="${PYTHON_BIN:-}"

if [[ -z "$PYTHON_BIN" ]]; then
  if [[ -x "$REPO_ROOT/.venv/bin/python" ]]; then
    PYTHON_BIN="$REPO_ROOT/.venv/bin/python"
  elif command -v python3 >/dev/null 2>&1; then
    PYTHON_BIN="$(command -v python3)"
  elif command -v python >/dev/null 2>&1; then
    PYTHON_BIN="$(command -v python)"
  else
    echo "Fehler: Kein Python-Interpreter gefunden (python3/python)." >&2
    exit 1
  fi
fi

echo "Deploy via FTP gestartet (Quelle: scripts/deploy_changed_files.py --include-untracked)"
"$PYTHON_BIN" "$REPO_ROOT/scripts/deploy_changed_files.py" --include-untracked
echo "Deploy erfolgreich abgeschlossen."
