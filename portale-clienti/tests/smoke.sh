#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
cat > config.php <<'PHP'
<?php
return ['db_dsn'=>'mysql:host=127.0.0.1;dbname=portal_test;charset=utf8mb4','db_user'=>'root','db_password'=>'testpass','base_url'=>'http://localhost:8000','storage_path'=>sys_get_temp_dir().'/portal-test-docs','max_upload_bytes'=>31457280,'capacity_bytes'=>null,'alert_remaining_bytes'=>2147483648,'admin_email'=>'admin@example.invalid','mail_from'=>'admin@example.invalid','smtp_host'=>'localhost','smtp_port'=>587,'smtp_user'=>'','smtp_password'=>'','cron_secret'=>'test-only'];
PHP
id=$(php tests/seed.php)
php -S localhost:8000 -t public > server-test.log 2>&1 & server=$!
trap 'kill "$server" 2>/dev/null || true; rm -f config.php server-test.log' EXIT
for i in {1..20}; do curl -fsS http://localhost:8000/ >/dev/null && break || sleep 1; done
status=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 302 ] || { echo "Expected unauthenticated redirect, got $status"; exit 1; }
login(){ local email="$1" cookie="$2" token;curl -s -c "$cookie" -b "$cookie" http://localhost:8000/ > login.html;token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' login.html | head -1);curl -s -o /dev/null -c "$cookie" -b "$cookie" -d "_csrf=$token" --data-urlencode "email=$email" --data-urlencode 'password=test-password-1234' 'http://localhost:8000/?action=login';}
login bob@example.invalid bob.cookie
status=$(curl -s -b bob.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 404 ] || { echo "Cross-company access: $status"; exit 1; }
login alice@example.invalid alice.cookie
status=$(curl -s -b alice.cookie -o delivered.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 200 ] && [ "$(cat delivered.txt)" = 'private test document' ] || { echo "Authorized download failed: $status"; exit 1; }
php -r 'require "src/Core.php"; $d=Portal\Core::row("SELECT first_download_at FROM documents WHERE id=1"); $e=Portal\Core::row("SELECT COUNT(*) n FROM download_events WHERE document_id=1"); if (!$d["first_download_at"] || $e["n"]!=1) exit(1);'
echo 'Authorization and archive smoke tests passed.'
