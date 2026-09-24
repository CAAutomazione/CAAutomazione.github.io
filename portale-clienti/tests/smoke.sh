#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
cat > config.php <<'PHP'
<?php
return ['db_dsn'=>'mysql:host=127.0.0.1;dbname=portal_test;charset=utf8mb4','db_user'=>'root','db_password'=>'testpass','base_url'=>'http://localhost:8000','storage_path'=>sys_get_temp_dir().'/portal-test-docs','max_upload_bytes'=>31457280,'capacity_bytes'=>null,'alert_remaining_bytes'=>2147483648,'admin_email'=>'caniatoa@libero.it','mail_from'=>'admin@example.invalid','smtp_host'=>'localhost','smtp_port'=>587,'smtp_user'=>'','smtp_password'=>'','privacy_version'=>'2026-09-23','cron_secret'=>'test-only-secret-at-least-thirty-two-chars','setup_secret'=>'test-only-setup-at-least-thirty-two-chars'];
PHP
id=$(php tests/seed.php)
php -S localhost:8000 -t public > server-test.log 2>&1 & server=$!
trap 'kill "$server" 2>/dev/null || true; rm -f config.php server-test.log' EXIT
for i in {1..20}; do curl -fsS http://localhost:8000/ >/dev/null && break || sleep 1; done
php -r 'require "src/Core.php"; if (!Portal\Core::rateLimit("test","limited-identity",2,60) || !Portal\Core::rateLimit("test","limited-identity",2,60) || Portal\Core::rateLimit("test","limited-identity",2,60)) exit(1);'
curl -s -D security-headers.txt -o /dev/null http://localhost:8000/
grep -qi 'Content-Security-Policy:.*script-src' security-headers.txt && grep -qi 'X-Frame-Options: DENY' security-headers.txt || { echo 'Security headers missing'; exit 1; }
php -r 'require "src/Core.php"; Portal\Core::db()->exec("ALTER TABLE documents DROP COLUMN manual_access, DROP COLUMN max_downloads, DROP COLUMN retention_days");'
status=$(curl -s -o upgrade.html -w '%{http_code}' http://localhost:8000/)
[ "$status" = 503 ] && grep -q 'Aggiornamento del database necessario' upgrade.html && ! grep -q 'Warning:' upgrade.html || { echo 'Outdated database does not show a clean upgrade notice'; exit 1; }
php -r 'require "src/Core.php"; foreach (explode("\n",file_get_contents("sql/004-document-access.sql")) as $line) { $line=trim($line); if ($line!=="" && !str_starts_with($line,"--")) Portal\Core::db()->exec($line); }'
status=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/?action=setup)
[ "$status" = 404 ] || { echo 'Setup available after administrator exists'; exit 1; }
status=$(curl -s -o /dev/null -w '%{http_code}' -X POST 'http://localhost:8000/?action=jobs')
[ "$status" = 404 ] || { echo 'Unauthenticated job request accepted'; exit 1; }
status=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'X-Portal-Job-Key: test-only-secret-at-least-thirty-two-chars' 'http://localhost:8000/?action=jobs')
[ "$status" = 204 ] || { echo "Authenticated job request failed: $status"; exit 1; }
status=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 401 ] || { echo "Expected unauthenticated rejection, got $status"; exit 1; }
login(){ local email="$1" cookie="$2" token;curl -s -c "$cookie" -b "$cookie" http://localhost:8000/ > login.html;token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' login.html | head -1);curl -s -o /dev/null -c "$cookie" -b "$cookie" -d "_csrf=$token" --data-urlencode "email=$email" --data-urlencode 'password=test-password-1234' -d 'privacy_read=1&remember_privacy=1' 'http://localhost:8000/?action=login';}
login bob@example.invalid bob.cookie
status=$(curl -s -b bob.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 404 ] || { echo "Cross-company access: $status"; exit 1; }
login carla@example.invalid carla.cookie
status=$(curl -s -b carla.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 404 ] || { echo "Cross-plant access: $status"; exit 1; }
curl -s -c first.cookie -b first.cookie http://localhost:8000/ > first-login.html
first_token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' first-login.html | head -1)
status=$(curl -s -c first.cookie -b first.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$first_token" --data-urlencode 'email=alice@example.invalid' --data-urlencode 'password=test-password-1234' 'http://localhost:8000/?action=login')
[ "$status" = 303 ] || { echo "Privacy acknowledgement not required on first login: $status"; exit 1; }
curl -s -b first.cookie http://localhost:8000/ > failed-login.html
grep -q 'login-error.*informativa privacy' failed-login.html || { echo 'Login error missing from sign-in form'; exit 1; }
login alice@example.invalid alice.cookie
curl -s -c remembered.cookie -b remembered.cookie http://localhost:8000/ > remembered-login.html
remembered_token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' remembered-login.html | head -1)
status=$(curl -s -c remembered.cookie -b remembered.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$remembered_token" --data-urlencode 'email=alice@example.invalid' --data-urlencode 'password=test-password-1234' 'http://localhost:8000/?action=login')
[ "$status" = 303 ] || { echo "Remembered privacy acknowledgement not honored: $status"; exit 1; }
status=$(curl -s -b alice.cookie -o delivered.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 200 ] && [ "$(cat delivered.txt)" = 'private test document' ] || { echo "Authorized download failed: $status"; exit 1; }
php -r 'require "src/Core.php"; $d=Portal\Core::row("SELECT first_download_at FROM documents WHERE id=1"); $e=Portal\Core::row("SELECT COUNT(*) n FROM download_events WHERE document_id=1"); if (!$d["first_download_at"] || $e["n"]!=1) exit(1);'
php -r 'require "src/Core.php"; $n=Portal\Core::row("SELECT recipient_email,subject FROM notification_queue WHERE kind=? ORDER BY id DESC LIMIT 1",["download"]); if (!$n || $n["recipient_email"]!=="caniatoa@libero.it" || !str_contains($n["subject"],"Alice")) exit(1);'
php -r 'require "src/Core.php"; Portal\Core::exec("UPDATE documents SET max_downloads=1 WHERE id=1"); Portal\Core::exec("UPDATE portal_settings SET value_int=2 WHERE setting_key=?",["max_downloads"]);'
php -r 'require "src/Core.php"; $d=Portal\Core::row("SELECT max_downloads FROM documents WHERE id=1");if($d["max_downloads"]!=1)exit(1);'
curl -s -b alice.cookie 'http://localhost:8000/?tab=archive' > limited.html
grep -q 'Download: 1 / 1' limited.html && grep -q 'Non accessibile' limited.html || { echo 'Download limit not visible to client'; exit 1; }
status=$(curl -s -b alice.cookie -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 404 ] || { echo "Download limit bypassed: $status"; exit 1; }
php -r 'require "src/Core.php"; Portal\Core::exec("UPDATE portal_settings SET value_int=0 WHERE setting_key=?",["max_downloads"]); Portal\Core::exec("UPDATE portal_settings SET value_int=1 WHERE setting_key=?",["retention_days"]); Portal\Core::exec("UPDATE documents SET max_downloads=0,retention_days=1,created_at=UTC_TIMESTAMP()-INTERVAL 10 DAY WHERE id=1");'
status=$(curl -s -b alice.cookie -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=view&id=$id")
[ "$status" = 404 ] || { echo "Document retention bypassed: $status"; exit 1; }
php -r 'require "src/Core.php"; Portal\Core::exec("UPDATE portal_settings SET value_int=0 WHERE setting_key=?",["retention_days"]); Portal\Core::exec("UPDATE documents SET retention_days=0,created_at=UTC_TIMESTAMP() WHERE id=1");'
php -r 'require "src/Core.php"; Portal\Core::exec("UPDATE documents SET revoked_at=UTC_TIMESTAMP() WHERE id=1");'
status=$(curl -s -b alice.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 404 ] || { echo "Revoked document still available: $status"; exit 1; }
php -r 'require "src/Core.php"; try { Portal\Core::exec("INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES(?,?,?,?,?)", ["Eve","Other","other@example.invalid","hash","admin"]); exit(1); } catch (PDOException $e) { exit(0); }'
login operator@example.invalid operator.cookie
curl -s -b operator.cookie 'http://localhost:8000/?tab=documents' > operator-documents.html
operator_token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' operator-documents.html | head -1)
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$operator_token&id=$id&accessible=1" 'http://localhost:8000/?action=set_access')
[ "$status" = 303 ] || { echo 'Manual re-enable failed'; exit 1; }
status=$(curl -s -b alice.cookie -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=view&id=$id")
[ "$status" = 200 ] || { echo 'Manual access does not restore document'; exit 1; }
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$operator_token&id=$id&accessible=0" 'http://localhost:8000/?action=set_access')
[ "$status" = 303 ] || { echo 'Manual disable failed'; exit 1; }
status=$(curl -s -b alice.cookie -o /dev/null -w '%{http_code}' "http://localhost:8000/?action=view&id=$id")
[ "$status" = 404 ] || { echo 'Manual access block bypassed'; exit 1; }
curl -s -b operator.cookie http://localhost:8000/?tab=accounts > operator.html
if grep -q 'Nuovo account cliente' operator.html; then echo 'Operator can see customer management'; exit 1; fi
operator_token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' operator.html | head -1)
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$operator_token&name=Forbidden" 'http://localhost:8000/?action=company')
[ "$status" = 403 ] || { echo "Operator created a company: $status"; exit 1; }
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' 'http://localhost:8000/?tab=accounts')
[ "$status" = 200 ] || exit 1
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' 'http://localhost:8000/?tab=archive')
[ "$status" = 200 ] || exit 1
folder_file_a=$(mktemp)
folder_file_b=$(mktemp)
printf 'first folder test' > "$folder_file_a"
printf 'second folder test' > "$folder_file_b"
folder_client=$(php -r 'require "src/Core.php"; echo Portal\Core::row("SELECT id FROM users WHERE email=?",["alice@example.invalid"])["id"];')
folder_plant=$(php -r 'require "src/Core.php"; echo Portal\Core::row("SELECT plant_id FROM user_plants WHERE user_id=?",[(int)$argv[1]])["plant_id"];' "$folder_client")
php -r 'require "src/Core.php"; Portal\Core::exec("UPDATE portal_settings SET value_int=3 WHERE setting_key=?",["max_downloads"]); Portal\Core::exec("UPDATE portal_settings SET value_int=7 WHERE setting_key=?",["retention_days"]);'
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' \
  -F "_csrf=$operator_token" -F "recipient_id=$folder_client" -F "plant_id=$folder_plant" \
  -F 'upload_mode=folder' -F 'expected_files=2' \
  -F "files[]=@$folder_file_a;filename=Prova/primo.txt;type=text/plain" \
  -F "files[]=@$folder_file_b;filename=Prova/secondo.txt;type=text/plain" \
  'http://localhost:8000/?action=upload')
rm -f "$folder_file_a" "$folder_file_b"
[ "$status" = 303 ] || { echo "Folder upload failed: $status"; exit 1; }
php -r 'require "src/Core.php"; $d=Portal\Core::row("SELECT max_downloads,retention_days FROM documents WHERE title LIKE ? LIMIT 1",["Prova / %"]);if (!$d || $d["max_downloads"]!=3 || $d["retention_days"]!=7)exit(1); Portal\Core::exec("UPDATE portal_settings SET value_int=0 WHERE setting_key=?",["max_downloads"]); Portal\Core::exec("UPDATE portal_settings SET value_int=0 WHERE setting_key=?",["retention_days"]); $d=Portal\Core::row("SELECT max_downloads,retention_days FROM documents WHERE title LIKE ? LIMIT 1",["Prova / %"]);if ($d["max_downloads"]!=3 || $d["retention_days"]!=7)exit(1);'
php -r 'require "src/Core.php"; $docs=Portal\Core::row("SELECT COUNT(*) n FROM documents WHERE recipient_id=?",[(int)$argv[1]]);$mail=Portal\Core::row("SELECT COUNT(*) n FROM notification_queue WHERE kind=?",["new_document"]);if($docs["n"]<3||$mail["n"]!=1)exit(1);' "$folder_client"
upload_sample=$(mktemp)
printf 'single upload test' > "$upload_sample"
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' -F "_csrf=$operator_token" -F "recipient_id=$folder_client" -F "plant_id=$folder_plant" -F 'title=Titolo da ignorare' -F "file=@$upload_sample;filename=Rapporto prova.txt;type=text/plain" 'http://localhost:8000/?action=upload')
[ "$status" = 303 ] || { echo "Default filename upload failed: $status"; exit 1; }
status=$(curl -s -b operator.cookie -o /dev/null -w '%{http_code}' -F "_csrf=$operator_token" -F "recipient_id=$folder_client" -F "plant_id=$folder_plant" -F 'custom_title=1' -F 'title=Relazione cliente' -F "file=@$upload_sample;filename=Rapporto prova.txt;type=text/plain" 'http://localhost:8000/?action=upload')
rm -f "$upload_sample"
[ "$status" = 303 ] || { echo "Custom title upload failed: $status"; exit 1; }
php -r 'require "src/Core.php"; foreach (["Rapporto prova.txt", "Relazione cliente"] as $title) if (Portal\Core::row("SELECT COUNT(*) n FROM documents WHERE title=?",[$title])["n"]!=1) exit(1);'
login caniatoa@libero.it admin.cookie
curl -s -b admin.cookie 'http://localhost:8000/?tab=archive' > admin-archive.html
if grep -q 'Documenti consegnati' admin-archive.html; then echo 'Duplicate delivered-documents table in archive'; exit 1; fi
if ! grep -q 'Seleziona i file della cartella' admin-archive.html || ! grep -q 'data-folder="[^"]*Prova"' admin-archive.html; then echo 'Folder files missing from archive'; exit 1; fi
if ! grep -q 'archive-select-visible' admin-archive.html; then echo 'Archive result selection missing'; exit 1; fi

alice_id=$(php -r 'require "src/Core.php"; echo Portal\Core::row("SELECT id FROM users WHERE email=?",["alice@example.invalid"])["id"];')
curl -s -b admin.cookie http://localhost:8000/ > admin.html
token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' admin.html | head -1)
curl -s -o /dev/null -c admin.cookie -b admin.cookie -d "_csrf=$token&id=$alice_id" 'http://localhost:8000/?action=impersonate'
status=$(curl -s -b admin.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 403 ] || { echo "Impersonation counted as customer download: $status"; exit 1; }
curl -s -o /dev/null -c admin.cookie -b admin.cookie -d "_csrf=$token" 'http://localhost:8000/?action=stop_impersonation'
curl -s -o /dev/null -c admin.cookie -b admin.cookie -d "_csrf=$token&id=$alice_id" 'http://localhost:8000/?action=remove_user'
status=$(curl -s -b alice.cookie -o denied.txt -w '%{http_code}' "http://localhost:8000/?action=download&id=$id")
[ "$status" = 401 ] || { echo "Removed account still has access: $status"; exit 1; }
php -r 'require "src/Core.php"; $u=Portal\Core::row("SELECT privacy_accepted_at FROM users WHERE email=?",["alice@example.invalid"]); $e=Portal\Core::row("SELECT COUNT(*) n FROM access_events WHERE user_id=(SELECT id FROM users WHERE email=?)",["alice@example.invalid"]); if (!$u["privacy_accepted_at"] || $e["n"]!=2) exit(1);'
php -r '$p=new PDO("mysql:host=127.0.0.1;charset=utf8mb4","root","testpass");$p->exec("CREATE DATABASE portal_setup_test CHARACTER SET utf8mb4");$p->exec("USE portal_setup_test");foreach(explode("\n",file_get_contents("sql/schema.sql")) as $line){$line=trim($line);if($line!=="")$p->exec($line);}'
sed -i 's/dbname=portal_test;/dbname=portal_setup_test;/' config.php
kill "$server" 2>/dev/null || true
wait "$server" 2>/dev/null || true
php -S localhost:8000 -t public > server-test.log 2>&1 & server=$!
for i in {1..20}; do curl -fsS http://localhost:8000/ >/dev/null && break || sleep 1; done
curl -s -c setup.cookie -b setup.cookie 'http://localhost:8000/?action=setup' > setup.html
setup_token=$(sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' setup.html | head -1)
[ -n "$setup_token" ] || { echo 'One-time setup form missing'; head -c 400 setup.html; exit 1; }
status=$(curl -s -c setup.cookie -b setup.cookie -o /dev/null -w '%{http_code}' -d "_csrf=$setup_token" --data-urlencode 'setup_secret=test-only-setup-at-least-thirty-two-chars' --data-urlencode 'first_name=Test' --data-urlencode 'last_name=Admin' --data-urlencode 'password=test-password-1234' 'http://localhost:8000/?action=setup')
[ "$status" = 303 ] || { echo "One-time setup failed: $status"; exit 1; }
status=$(curl -s -o /dev/null -w '%{http_code}' 'http://localhost:8000/?action=setup')
[ "$status" = 404 ] || { echo 'Setup remains open after activation'; exit 1; }
echo 'Authorization and archive smoke tests passed.'
