#!/usr/bin/env bash
set -Eeuo pipefail
PINA_APACHE_TEMP="$(mktemp -d)"
trap 'if [[ -f "$PINA_APACHE_TEMP/apache.pid" ]]; then sudo kill "$(cat "$PINA_APACHE_TEMP/apache.pid")" 2>/dev/null || true; fi' EXIT
cp -R app public "$PINA_APACHE_TEMP/"
chmod -R a+rX "$PINA_APACHE_TEMP"
# Old hosting directories must not shadow application routes.
mkdir -p "$PINA_APACHE_TEMP/public/register" "$PINA_APACHE_TEMP/public/login" "$PINA_APACHE_TEMP/public/documents/terms" "$PINA_APACHE_TEMP/public/api/health"
printf 'LEGACY PAGE' > "$PINA_APACHE_TEMP/public/register/index.html"
cat > "$PINA_APACHE_TEMP/apache.conf" <<CONF
ServerRoot /etc/apache2
ServerName 127.0.0.1
Listen 127.0.0.1:8083
PidFile "$PINA_APACHE_TEMP/apache.pid"
IncludeOptional /etc/apache2/mods-enabled/*.load
IncludeOptional /etc/apache2/mods-enabled/*.conf
User www-data
Group www-data
ErrorLog "$PINA_APACHE_TEMP/error.log"
DocumentRoot "$PINA_APACHE_TEMP/public"
<Directory "$PINA_APACHE_TEMP/public">
  AllowOverride All
  Require all granted
</Directory>
CONF
sudo apache2 -f "$PINA_APACHE_TEMP/apache.conf" -k start
for attempt in $(seq 1 20); do if curl --silent --fail http://127.0.0.1:8083/api/health/ > /dev/null; then break; fi; sleep 1; done
if ! PINA_TEST_URL=http://127.0.0.1:8083 node tests/routes.mjs; then cat "$PINA_APACHE_TEMP/error.log"; exit 1; fi
