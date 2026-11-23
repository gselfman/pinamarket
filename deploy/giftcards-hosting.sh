#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PINA_GIFT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PINA_PHP_BIN="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
export PINA_PUBLIC_DIR="${PINA_PUBLIC_DIR:-$HOME/www/pina.market}"
export PINA_BASE_URL="${PINA_BASE_URL:-https://pina.market}"
cd "$PINA_GIFT_ROOT"
unset PINA_CONFIG
# This upgrade never runs the old bootstrap, resets passwords, or recreates pm_* data.
case "${1:---check}" in
 --check) exec "$PINA_PHP_BIN" deploy/giftcards-hosting.php --check ;;
 --deploy) ;;
 *) echo 'Используйте --check или --deploy.' >&2; exit 1 ;;
esac
"$PINA_PHP_BIN" deploy/giftcards-hosting.php --prepare
mkdir -p var/giftcards-backups
chmod 700 var/giftcards-backups
"$PINA_PHP_BIN" deploy/giftcards-backup.php "$PINA_GIFT_ROOT/var/giftcards-backups"
"$PINA_PHP_BIN" deploy/giftcards-migrate.php --apply
"$PINA_PHP_BIN" deploy/giftcards-preflight.php
export PINA_REPLACE_SITE=1
if [[ -d "$PINA_PUBLIC_DIR/V0" ]]; then
 bash deploy/install.sh --update
else
 bash deploy/install.sh --update --archive-v0
fi
"$PINA_PHP_BIN" deploy/giftcards-verify.php
if ! PINA_CODE_DIR="$PINA_GIFT_ROOT" bash deploy/giftcards-cron.sh; then
 echo 'Сайт установлен. Cron не настроен: добавьте показанные задания в панели хостинга.' >&2
fi
printf '\nУСТАНОВЛЕНО: %s/\nСтарый сайт сохранён: %s/V0 (закрытый архив).\n' "$PINA_BASE_URL" "$PINA_PUBLIC_DIR"
printf 'Рабочий исходный код и настройки: %s\n' "$PINA_GIFT_ROOT"
printf 'Вход в управление: %s/admin/\n' "$PINA_BASE_URL"
printf 'Продажи откроются после настройки торговых точек и подтверждения программы. Договоры проверяются отдельно.\n'
