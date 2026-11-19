#!/usr/bin/env bash
# Run from the reviewed gift-card checkout on the existing hosting account.
# Default is read-only. --deploy publishes; it never pulls main or runs a remote installer.
set -Eeuo pipefail
umask 077
PINA_GIFT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PINA_PHP_BIN="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
cd "$PINA_GIFT_ROOT"
if [[ ! -x "$PINA_PHP_BIN" ]]; then printf 'Не найден PHP 8.2+: %s\n' "$PINA_PHP_BIN" >&2; exit 1; fi
if [[ "${1:---check}" == '--check' ]]; then
  "$PINA_PHP_BIN" deploy/giftcards-preflight.php
  exit
fi
if [[ "${1:-}" != '--deploy' ]]; then printf 'Используйте --check или --deploy\n' >&2; exit 1; fi
PINA_GIFT_BACKUPS="$PINA_GIFT_ROOT/var/giftcards-backups"
mkdir -p "$PINA_GIFT_BACKUPS"
chmod 700 "$PINA_GIFT_BACKUPS"
"$PINA_PHP_BIN" deploy/giftcards-backup.php "$PINA_GIFT_BACKUPS"
"$PINA_PHP_BIN" deploy/giftcards-migrate.php --apply
"$PINA_PHP_BIN" deploy/giftcards-preflight.php
bash deploy/install.sh --update
"$PINA_PHP_BIN" deploy/giftcards-verify.php
printf '\nПакет опубликован. Проверьте карточную программу и существующий кабинет.\n'
printf 'Cron выполняет только поставленные в очередь команды; неизвестный результат не повторяется.\n'
printf 'Задание Cron раз в минуту: %s %s/deploy/giftcards-worker.php\n' "$PINA_PHP_BIN" "$PINA_GIFT_ROOT"
