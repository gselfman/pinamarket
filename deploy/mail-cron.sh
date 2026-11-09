#!/usr/bin/env bash
set -Eeuo pipefail
PINA_MAIL_CODE="${PINA_CODE_DIR:-$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)}"
PINA_MAIL_PHP="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
# Hosting paths are inserted into crontab as shell literals; reject control/special syntax.
if [[ "$PINA_MAIL_CODE$PINA_MAIL_PHP" == *[!a-zA-Z0-9_./-]* ]]; then echo 'Недопустимый путь для Cron.' >&2; exit 1; fi
PINA_MAIL_LINE="* * * * * $PINA_MAIL_PHP $PINA_MAIL_CODE/deploy/mail-worker.php >> $PINA_MAIL_CODE/var/mail-worker.log 2>&1 # pina-market-mail"
printf 'Фоновая отправка писем (каждую минуту):\n%s\n' "$PINA_MAIL_LINE"
command -v crontab >/dev/null || exit 1
PINA_MAIL_TMP="$(mktemp -d)"
trap 'rm -rf -- "$PINA_MAIL_TMP"' EXIT
if ! LC_ALL=C crontab -l > "$PINA_MAIL_TMP/current" 2> "$PINA_MAIL_TMP/error"; then
 if ! grep -q 'no crontab for' "$PINA_MAIL_TMP/error"; then cat "$PINA_MAIL_TMP/error" >&2; exit 1; fi
fi
awk '!/ # pina-market-mail$/' "$PINA_MAIL_TMP/current" > "$PINA_MAIL_TMP/next"
printf '%s\n' "$PINA_MAIL_LINE" >> "$PINA_MAIL_TMP/next"
crontab "$PINA_MAIL_TMP/next"
printf 'Cron для писем настроен. Остальные задания сохранены.\n'
