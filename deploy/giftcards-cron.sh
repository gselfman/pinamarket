#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PINA_CRON_ROOT="${PINA_CODE_DIR:-$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)}"
PINA_CRON_PHP="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
[[ "$PINA_CRON_ROOT$PINA_CRON_PHP" != *[!a-zA-Z0-9_./-]* ]] || { echo 'Недопустимый путь для Cron.' >&2; exit 1; }
PINA_CRON_MAIL="* * * * * $PINA_CRON_PHP $PINA_CRON_ROOT/deploy/mail-worker.php >> $PINA_CRON_ROOT/var/mail-worker.log 2>&1 # pina-market-mail"
PINA_CRON_GIFT="* * * * * $PINA_CRON_PHP $PINA_CRON_ROOT/deploy/giftcards-worker.php >> $PINA_CRON_ROOT/var/giftcards-worker.log 2>&1 # pina-market-giftcards"
printf '%s\n%s\n' "$PINA_CRON_MAIL" "$PINA_CRON_GIFT"
command -v crontab >/dev/null || exit 1
PINA_CRON_TEMP="$(mktemp -d)"
trap 'rm -rf -- "$PINA_CRON_TEMP"' EXIT
if ! LC_ALL=C crontab -l > "$PINA_CRON_TEMP/current" 2> "$PINA_CRON_TEMP/error"; then
 if ! grep -q 'no crontab for' "$PINA_CRON_TEMP/error"; then cat "$PINA_CRON_TEMP/error" >&2; exit 1; fi
fi
awk '!/ # pina-market-(mail|giftcards)$/' "$PINA_CRON_TEMP/current" > "$PINA_CRON_TEMP/next"
printf '%s\n%s\n' "$PINA_CRON_MAIL" "$PINA_CRON_GIFT" >> "$PINA_CRON_TEMP/next"
crontab "$PINA_CRON_TEMP/next"
echo 'Cron настроен; посторонние задания сохранены.'
