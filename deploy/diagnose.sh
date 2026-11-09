#!/usr/bin/env bash
# Read-only hosting report. Does not read configuration, passwords or customer data.
set -uo pipefail
DOMAIN="${1:-pina.market}"
if [[ ! "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]]; then echo 'Укажите только домен без https:// и пути.' >&2; exit 1; fi
printf '=== Pina.market: диагностика хостинга ===\nДата UTC: %s\nПользователь: %s\nДомашний каталог: %s\nТекущий каталог: %s\n' "$(date -u +%FT%TZ)" "$(id -un)" "$HOME" "$PWD"
printf '\n=== Каталоги сайтов (до 3 уровней, без содержимого файлов) ===\n'
for parent in "$HOME/www" "$HOME/data/www" "$HOME/public_html" "$HOME/htdocs"; do
  [[ -d "$parent" ]] || continue
  printf '\nКорень: %s -> %s\n' "$parent" "$(readlink -f "$parent")"
  find "$parent" -maxdepth 3 \( -type d -o -type l \) -not -path '*/.git*' -print 2>/dev/null | head -80
  while IFS= read -r candidate; do
    printf '\nКандидат домена: %s\nПолный путь: %s\n' "$candidate" "$(readlink -f "$candidate")"
    [[ -w "$candidate" ]] && echo 'Запись: доступна' || echo 'Запись: недоступна'
    [[ -w "$(dirname "$candidate")" ]] && echo 'Родительский каталог: запись доступна' || echo 'Родительский каталог: запись недоступна'
    for name in index.php index.html .htaccess .user.ini .pina-installed; do
      [[ -e "$candidate/$name" ]] && stat -c '%A %U:%G %s байт %n' "$candidate/$name"
    done
    if [[ -r "$candidate/.htaccess" ]]; then
      if grep -Eiq '^[[:space:]]*RewriteEngine[[:space:]]+On' "$candidate/.htaccess"; then echo 'RewriteEngine On: найден'; else echo 'RewriteEngine On: не найден'; fi
    fi
  done < <(find "$parent" -maxdepth 2 \( -type d -o -type l \) -iname "$DOMAIN" -print 2>/dev/null)
done
printf '\n=== PHP CLI и необходимые расширения ===\n'
declare -A seen=()
for phpbin in /opt/php/{8.5,8.4,8.3,8.2}/bin/php "$(command -v php 2>/dev/null || true)"; do
  [[ -x "$phpbin" && -z "${seen[$phpbin]:-}" ]] || continue
  seen[$phpbin]=1
  printf '\n%s\n' "$phpbin"
  "$phpbin" -r 'echo "PHP ".PHP_VERSION."; SAPI ".PHP_SAPI.PHP_EOL;foreach(["pdo_mysql","mbstring","openssl","session","json"] as $e)echo $e.": ".(extension_loaded($e)?"OK":"ОТСУТСТВУЕТ").PHP_EOL;'
done
printf '\n=== Доступные команды ===\n'
for name in bash curl git tar openssl; do command -v "$name" || true; done
printf '\n=== Свободное место ===\n'
df -h "$HOME"
printf '\n=== Ответы домена (без cookies, паролей и данных пользователей) ===\n'
if command -v curl >/dev/null; then
  for path in / /login/ /register/ /documents/terms/ /api/health/; do
    printf '%s ' "https://$DOMAIN$path"
    curl -sS -L --max-time 10 -o /dev/null -w 'HTTP %{http_code}; адрес %{url_effective}\n' "https://$DOMAIN$path" || true
  done
fi
printf '\n=== Конец отчёта. Файлы и настройки не изменялись. ===\n'
