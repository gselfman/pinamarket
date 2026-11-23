#!/usr/bin/env bash
# Remote entry point for the existing reg.ru installation. Contains no credentials.
set -Eeuo pipefail
umask 077
export PINA_BASE_URL=https://pina.market
export PINA_PUBLIC_DIR="${PINA_PUBLIC_DIR:-$HOME/www/pina.market}"
export PINA_PHP_BIN="${PINA_PHP_BIN:-/opt/php/8.2/bin/php}"
export PINA_OLD_CODE_DIR="${PINA_OLD_CODE_DIR:-$HOME/pinamarket-code}"
PINA_GIFT_REF="${PINA_GIFT_REF:-codex/gift-cards-redesign}"
PINA_GIFT_MODE="${1:---deploy}"
[[ "$PINA_GIFT_MODE" == --deploy || "$PINA_GIFT_MODE" == --check ]] || { echo 'Используйте --deploy или --check.' >&2; exit 1; }
for tool in curl tar flock; do command -v "$tool" >/dev/null || { printf 'Не найдена команда: %s\n' "$tool" >&2; exit 1; }; done
[[ -x "$PINA_PHP_BIN" ]] || { echo 'Не найден PHP 8.2 в /opt/php/8.2/bin/php.' >&2; exit 1; }
[[ "$PINA_GIFT_REF" =~ ^[a-zA-Z0-9_./-]+$ ]] || exit 1
exec 9>"$HOME/.pinamarket-giftcards-install.lock"
flock -n 9 || { echo 'Установка уже выполняется.' >&2; exit 1; }
PINA_GIFT_STAGE="$(mktemp -d "$HOME/.pina-giftcards-download.XXXXXX")"
trap 'rm -rf -- "$PINA_GIFT_STAGE"' EXIT
curl --proto '=https' --tlsv1.2 --connect-timeout 10 --max-time 60 -fsSL "https://api.github.com/repos/gselfman/pinamarket/commits/$PINA_GIFT_REF" -o "$PINA_GIFT_STAGE/commit.json"
PINA_GIFT_SHA="$("$PINA_PHP_BIN" -r '$d=json_decode(file_get_contents($argv[1]),true);$s=$d["sha"]??"";if(!preg_match("/^[a-f0-9]{40}$/D",$s))exit(1);echo $s;' "$PINA_GIFT_STAGE/commit.json")"
curl --proto '=https' --tlsv1.2 --connect-timeout 10 --max-time 180 -fsSL "https://codeload.github.com/gselfman/pinamarket/tar.gz/$PINA_GIFT_SHA" -o "$PINA_GIFT_STAGE/source.tar.gz"
mkdir "$PINA_GIFT_STAGE/source"
tar -xzf "$PINA_GIFT_STAGE/source.tar.gz" --strip-components=1 -C "$PINA_GIFT_STAGE/source"
[[ -f "$PINA_GIFT_STAGE/source/deploy/giftcards-hosting.sh" && -f "$PINA_GIFT_STAGE/source/app/giftcards-shell.html" ]] || { echo 'Пакет неполон; сайт не изменён.' >&2; exit 1; }
mkdir -p "$HOME/pinamarket-giftcards-releases"
PINA_GIFT_DEST="$(mktemp -d "$HOME/pinamarket-giftcards-releases/$PINA_GIFT_SHA.XXXXXX")"
cp -a "$PINA_GIFT_STAGE/source/." "$PINA_GIFT_DEST/"
printf '%s\n' "$PINA_GIFT_SHA" > "$PINA_GIFT_DEST/.pina-source-sha"
printf 'Загружена версия %s. Старый исходный код сохранён.\n' "$PINA_GIFT_SHA"
bash "$PINA_GIFT_DEST/deploy/giftcards-hosting.sh" "$PINA_GIFT_MODE"
