#!/usr/bin/env bash
set -Eeuo pipefail
[[ "${DB_NAME:-}" == 'pina_test' && -n "${PINA_CONFIG:-}" ]] || { echo 'Use only the disposable CI test database.' >&2; exit 1; }
PINA_INSTALL_TEMP="$(mktemp -d)"
cleanup(){ if [[ -n "${php_pid:-}" ]]; then kill "$php_pid" 2>/dev/null || true; fi; if [[ -n "${tls_pid:-}" ]]; then sudo kill "$tls_pid" 2>/dev/null || true; fi; }; trap cleanup EXIT
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
cat > "$PINA_INSTALL_TEMP/tls-router.php" <<'PHP'
<?php
// This request router is used only behind the local CI TLS terminator.
$_SERVER['HTTPS']='on';$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path!=='/'&&is_file($_SERVER['DOCUMENT_ROOT'].$path))return false;
require $_SERVER['DOCUMENT_ROOT'].'/index.php';
PHP
php -S 127.0.0.1:8082 -t "$PINA_INSTALL_TEMP/htdocs" "$PINA_INSTALL_TEMP/tls-router.php" > "$PINA_INSTALL_TEMP/php.log" 2>&1 &
php_pid=$!
sudo python3 tests/network_fixture.py "$PINA_INSTALL_TEMP/cert.crt" "$PINA_INSTALL_TEMP/key.pem" > "$PINA_INSTALL_TEMP/tls.log" 2>&1 &
tls_pid=$!
for attempt in $(seq 1 20); do if curl --noproxy '*' --silent https://pina.install.test/ > /dev/null; then break; fi; sleep 1; done
bash "$PINA_INSTALL_TEMP/code/deploy/install.sh" <<INPUT
https://pina.install.test
/www/pina.market
$PINA_INSTALL_TEMP/htdocs
127.0.0.1
3306
pina_install_test
root
local-test-only
smtp.install.test
465
ssl
mail@pina.market
smtp-test-password
mail@pina.market
install-admin@example.com
PinaInstall2026-LongPassword
PinaInstall2026-LongPassword
Confirmed CI cash desk
ЗАМЕНИТЬ
INPUT
[[ ! -e "$PINA_INSTALL_TEMP/htdocs/admin" ]]
[[ "$(cat "$PINA_INSTALL_TEMP/htdocs/.well-known/token.txt")" == 'CERTIFICATE TOKEN' ]]
[[ -f "$PINA_INSTALL_TEMP/code/app/config.php" && -f "$PINA_INSTALL_TEMP/htdocs/index.php" ]]
[[ "$(stat -c %a "$PINA_INSTALL_TEMP/code/app/config.php")" == 600 ]]
curl --noproxy '*' --silent --fail https://pina.install.test/api/health/ | grep -q '"status":"ok"'
active_app="$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")"
[[ "$active_app" == "$PINA_INSTALL_TEMP/code/var/releases/"* ]]
active_hash="$(sha256sum "$active_app/api.php" | cut -d' ' -f1)"
printf '\n// Source update smoke check\n' >> "$PINA_INSTALL_TEMP/code/app/api.php"
[[ "$(sha256sum "$active_app/api.php" | cut -d' ' -f1)" == "$active_hash" ]]
bash "$PINA_INSTALL_TEMP/code/deploy/install.sh" --update
php "$PINA_INSTALL_TEMP/code/deploy/verify.php" https://pina.install.test
next_app="$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")"
[[ "$next_app" != "$active_app" ]]
[[ "$(sha256sum "$active_app/api.php" | cut -d' ' -f1)" == "$active_hash" ]]
curl --noproxy '*' --silent --fail https://pina.install.test/documents/terms/ | grep -q 'Pina.market'
cp "$PINA_INSTALL_TEMP/code/app/config.php" "$PINA_INSTALL_TEMP/original-config.php"
php -r '$f=$argv[1];$c=require $f;$c["base_url"]="https://unavailable.pina.invalid";file_put_contents($f,"<?php return ".var_export($c,true).";");' "$PINA_INSTALL_TEMP/code/app/config.php"
if bash "$PINA_INSTALL_TEMP/code/deploy/install.sh" --update; then echo 'Expected HTTPS failure' >&2; exit 1; fi
[[ "$(php -r 'echo require $argv[1];' "$PINA_INSTALL_TEMP/htdocs/pina-path.php")" == "$next_app" ]]
cp "$PINA_INSTALL_TEMP/original-config.php" "$PINA_INSTALL_TEMP/code/app/config.php"
curl --noproxy '*' --silent --fail https://pina.install.test/api/health/ | grep -q '\"status\":\"ok\"'
echo 'PASS: clean CLI installation, verified TLS/SMTP, legacy-site replacement, SSL-token preservation repeat update and rollback after failed HTTPS check'
