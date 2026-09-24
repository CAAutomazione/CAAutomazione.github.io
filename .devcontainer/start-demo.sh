#!/usr/bin/env bash
set -euo pipefail
cd /workspace/portale-clienti
if [ ! -f config.php ]; then
cat > config.php <<'PHP'
<?php
return [
 'db_dsn'=>'mysql:host=db;dbname=portal_demo;charset=utf8mb4',
 'db_user'=>'portal_demo', 'db_password'=>'demo-only-password',
 'base_url'=>'http://localhost:8000',
 'site_url'=>'https://caautomazione.github.io/',
 'privacy_url'=>'https://caautomazione.github.io/privacy/',
 'storage_path'=>'/private-documents',
 'max_upload_bytes'=>30*1024*1024,
 'capacity_bytes'=>10*1024*1024*1024,
 'alert_remaining_bytes'=>2*1024*1024*1024,
 'privacy_version'=>'2026-09-23',
 'admin_email'=>'caniatoa@libero.it',
 'mail_from'=>'demo-admin@example.invalid',
 'smtp_host'=>'not-configured.invalid', 'smtp_port'=>587,
 'smtp_user'=>'', 'smtp_password'=>'',
 'cron_secret'=>'demo-only',
 'allow_http_preview'=>true,
];
PHP
fi
# Compatibilità con i Codespaces creati prima dell'aggiunta del ruolo operatore.
if grep -q "'admin_email'=>'demo-admin@example.invalid'" config.php; then
  sed -i "s/'admin_email'=>'demo-admin@example.invalid'/'admin_email'=>'caniatoa@libero.it'/" config.php
fi
composer install --no-dev --no-interaction --prefer-dist
ready=0
for attempt in $(seq 1 45); do
  if php -r 'require "src/Core.php"; try { Portal\Core::db(); exit(0); } catch (Throwable $e) { exit(1); }'; then
    ready=1
    break
  fi
  sleep 2
done
if [ "$ready" != 1 ]; then
  echo 'Database dimostrativo non raggiungibile dopo 90 secondi.' >&2
  exit 1
fi
php tests/demo-seed.php
exec php -S 0.0.0.0:8000 -t public
