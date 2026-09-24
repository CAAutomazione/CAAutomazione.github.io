#!/usr/bin/env bash
set -euo pipefail
credentials=$(docker compose -f .devcontainer/docker-compose.yml exec -T app cat /private-documents/.demo-admin-login)
demo_email=$(printf '%s\n' "$credentials" | sed -n 's/^Email: //p')
demo_password=$(printf '%s\n' "$credentials" | sed -n 's/^Password: //p')
[ -n "$demo_email" ] && [ -n "$demo_password" ]
cookie_file=$(mktemp)
trap 'rm -f "$cookie_file"' EXIT
login_page=$(curl -fsS -c "$cookie_file" -b "$cookie_file" http://127.0.0.1:8000/)
csrf=$(printf '%s\n' "$login_page" | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1)
[ -n "$csrf" ]
status=$(curl -s -o /dev/null -w '%{http_code}' -c "$cookie_file" -b "$cookie_file" \
  --data-urlencode "_csrf=$csrf" --data-urlencode "email=$demo_email" \
  --data-urlencode "password=$demo_password" -d 'privacy_read=1&remember_privacy=1' \
  'http://127.0.0.1:8000/?action=login')
[ "$status" = 303 ] || { echo "Demo login rejected: $status"; exit 1; }
curl -fsS -b "$cookie_file" http://127.0.0.1:8000/ | grep -q 'Amministrazione' || { echo 'Demo session not retained'; exit 1; }
echo 'Demo admin login and session verified.'
