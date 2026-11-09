#!/usr/bin/env bash
# Installation profile for the confirmed Pina.market hosting account. No secrets here.
set -Eeuo pipefail
umask 077
export PINA_BASE_URL=https://pina.market
export PINA_CODE_DIR="${PINA_CODE_DIR:-$HOME/pinamarket-code}"
export PINA_PUBLIC_DIR="${PINA_PUBLIC_DIR:-$HOME/www/pina.market}"
export PINA_PHP_BIN="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
# Web PHP and CLI PHP are configured separately on reg.ru.
PINA_FCGI_WRAPPER="${PINA_FCGI_WRAPPER:-}"
if [[ -z "$PINA_FCGI_WRAPPER" ]]; then
  for candidate in "$HOME/php-bin-regru-php82/php" "$HOME/php-bin-php82/php" "$HOME/php-bin/pina.market/php"; do
    if [[ -f "$candidate" && -x "$candidate" ]]; then PINA_FCGI_WRAPPER="$candidate"; break; fi
  done
fi
if [[ -z "$PINA_FCGI_WRAPPER" ]]; then
  echo 'Не найден обработчик FastCGI PHP 8.2 в папках php-bin. Пришлите вывод: ls -l "$HOME"/php-bin*/php "$HOME"/php-bin/pina.market/php' >&2
  exit 1
fi
export PINA_FCGI_WRAPPER

export PINA_DB_HOST=127.0.0.1
export PINA_DB_PORT=3306
export PINA_DB_NAME=u3266069_pina_market
export PINA_DB_USER=u3266069_pina_market_sql
export PINA_SMTP_HOST=mail.hosting.reg.ru
export PINA_SMTP_PORT=465
export PINA_SMTP_SECURITY=ssl
export PINA_SMTP_USER=mail@pina.market
export PINA_SMTP_FROM=mail@pina.market
export PINA_ADMIN_EMAIL=mail@pina.market
# An unconfirmed cash address must not enable deposits. Configure it in administration.
export PINA_CASH_ADDRESS=''
export PINA_PASSWORDS_ONLY=1
export PINA_REPLACE_SITE=1
printf 'Pina.market: настройки домена, каталогов, MySQL и SMTP заполнены.\n'
printf 'Администратор: %s\n' "$PINA_ADMIN_EMAIL"
printf 'Нужны пароль MySQL, пароль почты и новый пароль администратора (дважды).\n'
printf 'Существующие файлы сайта будут сохранены перед заменой.\n'
bootstrap="$(mktemp)"
trap 'rm -f -- "$bootstrap"' EXIT
curl --proto '=https' --tlsv1.2 -fsSL https://raw.githubusercontent.com/gselfman/pinamarket/main/deploy/bootstrap.sh -o "$bootstrap"
if bash "$bootstrap"; then
  printf '\nУСТАНОВКА ЗАВЕРШЕНА: HTTPS, база, страницы и ресурсы прошли проверку.\n'
  printf 'Вход: https://pina.market/login/ | Регистрация: https://pina.market/register/\n'
  printf 'Админка: https://pina.market/admin/\n'
else
  result=$?
  printf '\nУСТАНОВКА НЕ ЗАВЕРШЕНА (код %s). Причина указана выше.\n' "$result" >&2
  printf 'Пришлите последние строки вывода без паролей. До успешной проверки сайт не считается опубликованным.\n' >&2
  exit "$result"
fi
