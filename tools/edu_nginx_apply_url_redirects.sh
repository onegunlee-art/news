#!/usr/bin/env bash
# Apply EDU URL redirects to whichever nginx config serves edu.thegist.co.kr
# Usage (on EC2): sudo bash tools/edu_nginx_apply_url_redirects.sh
# CI calls via ssh after scp of aws/thegist-edu-url-redirects.inc → /tmp/edu-url-snippet.inc

set -euo pipefail

SNIPPET="${1:-/tmp/edu-url-snippet.inc}"
MARKER="return 302 /edu"

find_edu_conf() {
  local f
  for dir in /etc/nginx/sites-enabled /etc/nginx/sites-available; do
    [ -d "$dir" ] || continue
    while IFS= read -r f; do
      if grep -q 'server_name.*edu\.thegist\.co\.kr' "$f" 2>/dev/null; then
        # Prefer sites-available real file over symlink name noise
        if [ -L "$f" ]; then
          readlink -f "$f"
        else
          echo "$f"
        fi
        return 0
      fi
    done < <(grep -rl 'server_name.*edu\.thegist\.co\.kr' "$dir" 2>/dev/null || true)
  done
  return 1
}

if [ ! -f "$SNIPPET" ]; then
  echo "ERROR: snippet not found: $SNIPPET" >&2
  exit 1
fi

CONF="$(find_edu_conf || true)"
if [ -z "$CONF" ]; then
  echo "WARN: no nginx config with server_name edu.thegist.co.kr — create from docs/GIST_EDU_NGINX_TEMPLATE.md"
  exit 0
fi

echo "EDU nginx config: $CONF"

if grep -q "$MARKER" "$CONF"; then
  echo "EDU URL redirects already present — nothing to do"
  exit 0
fi

BACKUP="${CONF}.bak-$(date +%Y%m%d%H%M%S)"
cp "$CONF" "$BACKUP"
echo "Backup: $BACKUP"

python3 << PY
import re
from pathlib import Path
conf = Path("$CONF")
snippet = Path("$SNIPPET").read_text(encoding="utf-8").rstrip() + "\n\n"
text = conf.read_text(encoding="utf-8")
m = re.search(r"^(\s+)location / \{", text, re.MULTILINE)
if not m:
    raise SystemExit("ERROR: could not find 'location / {' in $CONF")
idx = m.start()
conf.write_text(text[:idx] + snippet + text[idx:], encoding="utf-8")
print("Inserted redirect snippet before location /")
PY

nginx -t
systemctl reload nginx
echo "OK: nginx reloaded with EDU URL redirects"
