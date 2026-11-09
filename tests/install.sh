#!/usr/bin/env bash
set -Eeuo pipefail
[[ "${DB_NAME:-}" == 'pina_test' && -n "${PINA_CONFIG:-}" ]] || { echo 'Use only the disposable CI test database.' >&2; exit 1; }
PINA_INSTALL_TEMP="$(mktemp -d)"
chmod 0755 "$PINA_INSTALL_TEMP"
umask 077
cleanup(){
  local result=$?
  if [[ "$result" != 0 && -f "$PINA_INSTALL_TEMP/apache-error.log" ]]; then tail -80 "$PINA_INSTALL_TEMP/apache-error.log"; fi
  if [[ -f "$PINA_INSTALL_TEMP/apache.pid" ]]; then sudo kill "$(cat "$PINA_INSTALL_TEMP/apache.pid")" 2>/dev/null || true; fi
  if [[ -n "${tls_pid:-}" ]]; then sudo kill "$tls_pid" 2>/dev/null || true; fi
}; trap cleanup EXIT
mkdir -p "$PINA_INSTALL_TEMP/code" "$PINA_INSTALL_TEMP/htdocs/admin" "$PINA_INSTALL_TEMP/htdocs/.well-known"
git archive HEAD | tar -xf - -C "$PINA_INSTALL_TEMP/code"
printf 'OLD SITE' > "$PINA_INSTALL_TEMP/htdocs/index.html"
printf 'OLD ADMIN' > "$PINA_INSTALL_TEMP/htdocs/admin/index.php"
printf 'CERTIFICATE TOKEN' > "$PINA_INSTALL_TEMP/htdocs/.well-known/token.txt"
php -r '$c=require getenv("PINA_CONFIG");$b=$c["db"];$p=new PDO("mysql:host=".$b["host"].";dbname=".$b["name"],$b["user"],$b["password"]);$p->exec("CREATE DATABASE IF NOT EXISTS pina_install_test CHARACTER SET utf8mb4");'
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout "$PINA_INSTALL_TEMP/key.pem" -out "$PINA_INSTALL_TEMP/cert.crt" -subj '/CN=pina.install.test' -addext 'subjectAltName=DNS:pina.install.test,DNS:smtp.install.test' -addext 'basicConstraints=critical,CA:TRUE' > /dev/null 2>&1
sudo cp "$PINA_INSTALL_TEMP/cert.crt" /usr/local/share/ca-certificates/pinamarket-ci.crt
sudo update-ca-certificates > /dev/null
printf '\n127.0.0.1 pina.install.test smtp.install.test\n' | sudo tee -a /etc/hosts > /dev/null
# Actual FastCGI runs as the hosting account; a separate user must still read static files.
cat > "$PINA_INSTALL_TEMP/php-wrapper" <<'SH'
#!/bin/sh
exec /usr/bin/php-cgi8.3 "$@"
SH
chmod 0755 "$PINA_INSTALL_TEMP/php-wrapper"
mkdir "$PINA_INSTALL_TEMP/fcgid"
cat > "$PINA_INSTALL_TEMP/apache.conf" <<CONF
ServerRoot /etc/apache2
ServerName pina.install.test
Listen 127.0.0.1:8082
PidFile "$PINA_INSTALL_TEMP/apache.pid"
IncludeOptional /etc/apache2/mods-enabled/*.load
User $(id -un)
Group $(id -gn)
ErrorLog "$PINA_INSTALL_TEMP/apache-error.log"
DocumentRoot "$PINA_INSTALL_TEMP/htdocs"
DirectoryIndex index.php index.html
TypesConfig /etc/mime.types
FcgidIPCDir "$PINA_INSTALL_TEMP/fcgid"
FcgidProcessTableFile "$PINA_INSTALL_TEMP/fcgid_shm"
SetEnv HTTPS on
<Directory "$PINA_INSTALL_TEMP/htdocs">
  Options +ExecCGI
  AllowOverride All
  Require all granted
</Directory>
CONF
sudo apache2 -f "$PINA_INSTALL_TEMP/apache.conf" -k start
export PINA_FCGI_WRAPPER="$PINA_INSTALL_TEMP/php-wrapper"
sudo python3 tests/network_fixture.py "$PINA_INSTALL_TEMP/cert.crt" "$PINA_INSTALL_TEMP/key.pem" > "$PINA_INSTALL_TEMP/tls.log" 2>&1 &
tls_pid=$!
for attempt in $(seq 1 20); do if curl --noproxy '*' --silent https://pina.install.test/ > /dev/null; then break; fi; sleep 1; done
export PINA_PASSWORDS_ONLY=1 PINA_REPLACE_SITE=1
export PINA_BASE_URL=https://pina.install.test PINA_PUBLIC_DIR="$PINA_INSTALL_TEMP/htdocs"
export PINA_DB_HOST=' 127.0.0.1 ' PINA_DB_PORT=3306 PINA_DB_NAME=pina_install_test PINA_DB_USER=root
export PINA_SMTP_HOST='  smtp.install.test  ' PINA_SMTP_PORT=465 PINA_SMTP_SECURITY=' SSL '
export PINA_SMTP_USER=mail@pina.market PINA_SMTP_FROM=mail@pina.market PINA_ADMIN_EMAIL=install-admin@example.com PINA_CASH_ADDRESS=''
bash "$PINA_INSTALL_TEMP/code/deploy/install.sh" <<INPUT
local-test-only
smtp-test-password
PinaInstall2026-LongPassword
PinaInstall2026-LongPassword
INPUT
php -r '$c=require $argv[1];if($c["public_dir"]!==$argv[2]||$c["base_url"]!=="https://pina.install.test"||$c["smtp"]["host"]!=="smtp.install.test"||$c["smtp"]["security"]!=="ssl"||$c["cash_enabled"]!==false)exit(1);' "$PINA_INSTALL_TEMP/code/app/config.php" "$PINA_INSTALL_TEMP/htdocs"
[[ "$(stat -c %a "$PINA_INSTALL_TEMP/htdocs")" == 755 ]]
sudo -u nobody test -r "$PINA_INSTALL_TEMP/htdocs/app.js"
sudo -u nobody test -r "$PINA_INSTALL_TEMP/htdocs/.well-known/token.txt"
printf '<?php echo PHP_SAPI;' > "$PINA_INSTALL_TEMP/htdocs/runtime-check.php"
chmod 0644 "$PINA_INSTALL_TEMP/htdocs/runtime-check.php"
[[ "$(curl --noproxy '*' --silent --fail https://pina.install.test/runtime-check.php)" == 'cgi-fcgi' ]]
rm "$PINA_INSTALL_TEMP/htdocs/runtime-check.php"
[[ "$(curl --noproxy '*' --silent -o /dev/null -w '%{http_code}' https://pina.install.test/pina-path.json)" == 403 ]]
grep -Fq "FCGIWrapper $PINA_FCGI_WRAPPER .php" "$PINA_INSTALL_TEMP/htdocs/.htaccess"
[[ ! -e "$PINA_INSTALL_TEMP/htdocs/admin" ]]
[[ "$(cat "$PINA_INSTALL_TEMP/htdocs/.well-known/token.txt")" == 'CERTIFICATE TOKEN' ]]
[[ -f "$PINA_INSTALL_TEMP/code/app/config.php" && -f "$PINA_INSTALL_TEMP/htdocs/index.php" ]]
[[ "$(stat -c %a "$PINA_INSTALL_TEMP/code/app/config.php")" == 600 ]]
curl --noproxy '*' --silent --fail https://pina.install.test/api/health/ | grep -q '"status":"ok"'
active_app="$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")"
[[ "$active_app" == "$PINA_INSTALL_TEMP/code/var/releases/"* ]]
[[ "$(stat -c %a "$active_app")" == 700 ]]
if sudo -u nobody test -r "$active_app/config.php"; then echo 'Private configuration exposed' >&2; exit 1; fi
active_hash="$(sha256sum "$active_app/api.php" | cut -d' ' -f1)"
printf '\n// Source update smoke check\n' >> "$PINA_INSTALL_TEMP/code/app/api.php"
[[ "$(sha256sum "$active_app/api.php" | cut -d' ' -f1)" == "$active_hash" ]]
# Simulate the existing production schema before this release, then verify additive migration.
php -r '$c=require $argv[1];$b=$c["db"];$p=new PDO("mysql:host=".$b["host"].";port=".$b["port"].";dbname=".$b["name"],$b["user"],$b["password"]);$p->exec("ALTER TABLE pm_services DROP COLUMN sort_order");$p->exec("DROP TABLE pm_mail_queue");' "$PINA_INSTALL_TEMP/code/app/config.php"
php -d allow_url_fopen=0 "$PINA_INSTALL_TEMP/code/deploy/setup.php" --update
PINA_CONFIG="$PINA_INSTALL_TEMP/code/app/config.php" php -r 'require $argv[1]."/app/bootstrap.php";require $argv[1]."/app/notifications.php";if(!in_array("sort_order",db()->query("SHOW COLUMNS FROM pm_services")->fetchAll(PDO::FETCH_COLUMN),true)||(int)one("SELECT COUNT(*) n FROM pm_clients")["n"]!==1)exit(1);queue_mail("admin@example.com","Queue transport check","Durable notification after schema migration");' "$PINA_INSTALL_TEMP/code"
PINA_CONFIG="$PINA_INSTALL_TEMP/code/app/config.php" php "$PINA_INSTALL_TEMP/code/deploy/mail-worker.php" | grep -q 'SMTP accepted: 1'
PINA_CONFIG="$PINA_INSTALL_TEMP/code/app/config.php" php "$PINA_INSTALL_TEMP/code/deploy/mail-worker.php" | grep -q 'SMTP accepted: 0'
echo 'PASS: existing-schema migration and queued delivery through real TLS SMTP'

php -d allow_url_fopen=0 "$PINA_INSTALL_TEMP/code/deploy/verify.php" https://pina.install.test
php -r 'putenv("PATH=/nonexistent-pina-test-bin");require $argv[1];verify_site("https://pina.install.test");echo "PASS: PHP HTTPS fallback follows canonical and legacy redirects without curl\n";' "$PINA_INSTALL_TEMP/code/deploy/verify.php"
php -r 'require $argv[1];$r=deployment_http_get("https://127.0.0.1/");if($r["status"]!==0||$r["error"]==="")exit(1);$r=deployment_http_get("https://pina.install.test/unsafe-redirect");if($r["status"]!==0||!str_contains($r["error"],"HTTPS запрещено"))exit(1);echo "PASS: wrong certificate name and HTTP downgrade rejected by both transports\n";' "$PINA_INSTALL_TEMP/code/deploy/verify.php"
next_app="$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")"
[[ "$next_app" != "$active_app" ]]
php -r '$p=json_decode(file_get_contents($argv[1]),true);if($p["app"]!==$argv[2])exit(1);' "$PINA_INSTALL_TEMP/htdocs/pina-path.json" "$next_app"
[[ "$(sha256sum "$active_app/api.php" | cut -d' ' -f1)" == "$active_hash" ]]
curl --noproxy '*' --silent --fail https://pina.install.test/documents/terms/ | grep -q 'Pina.market'
cp "$PINA_INSTALL_TEMP/code/app/config.php" "$PINA_INSTALL_TEMP/original-config.php"
php -r '$f=$argv[1];$c=require $f;$c["base_url"]="https://unavailable.pina.invalid";file_put_contents($f,"<?php return ".var_export($c,true).";");' "$PINA_INSTALL_TEMP/code/app/config.php"
if bash "$PINA_INSTALL_TEMP/code/deploy/install.sh" --update; then echo 'Expected HTTPS failure' >&2; exit 1; fi
[[ "$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")" == "$next_app" ]]
php -r '$p=json_decode(file_get_contents($argv[1]),true);if($p["app"]!==$argv[2])exit(1);' "$PINA_INSTALL_TEMP/htdocs/pina-path.json" "$next_app"
cp "$PINA_INSTALL_TEMP/original-config.php" "$PINA_INSTALL_TEMP/code/app/config.php"
curl --noproxy '*' --silent --fail https://pina.install.test/api/health/ | grep -q '\"status\":\"ok\"'
php -r 'require $argv[1];$r=deployment_http_get("https://unavailable.pina.invalid/");if($r["status"]!==0||!str_contains($r["error"],"curl (код ")||!str_contains($r["error"],"PHP HTTPS:"))exit(1);echo "PASS: both HTTPS transport errors retain diagnostic detail\n";' "$PINA_INSTALL_TEMP/code/deploy/verify.php"
echo 'PASS: actual Apache FastCGI, public files readable under umask 077, private config restricted, clean CLI installation, verified TLS/SMTP, legacy-site replacement, SSL-token preservation repeat update and rollback after failed HTTPS check'
