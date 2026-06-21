#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 https://github.com/<owner>/IliasTraxEventBridge.git"
  exit 1
fi

REMOTE_URL="$1"

git remote remove origin 2>/dev/null || true
git remote add origin "$REMOTE_URL"

git push -u origin main
git push origin v0.1.0 v0.1.1 v0.1.2 v0.1.3 v0.1.4 v0.1.5 v0.2.0 v0.2.1 v0.3.0 v0.3.1
git push origin --tags

echo "Dépôt poussé vers $REMOTE_URL"
