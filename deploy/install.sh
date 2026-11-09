#!/usr/bin/env bash
set -Eeuo pipefail
ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PINA_PHP_BIN="${PINA_PHP_BIN:-}"
if [[ -z "$PINA_PHP_BIN" ]]; then
  for candidate in /opt/php/8.4/bin/php /opt/php/8.3/bin/php /opt/php/8.2/bin/php /opt/php/8.5/bin/php "$(command -v php || true)"; do
    if [[ -n "$candidate" && -x "$candidate" ]] && "$candidate" -r 'exit(PHP_VERSION_ID>=80200?0:1);' 2>/dev/null; then PINA_PHP_BIN="$candidate"; break; fi
  done
fi
if [[ -z "$PINA_PHP_BIN" ]]; then echo 'Не найден PHP CLI 8.2+. В панели reg.ru выберите PHP 8.2+; задайте PINA_PHP_BIN=/путь/к/php и повторите.' >&2; exit 1; fi
cd "$ROOT_DIR"
exec "$PINA_PHP_BIN" deploy/setup.php "$@"
