#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PINA_CODE_DIR="${PINA_CODE_DIR:-$HOME/pinamarket-code}"
PINA_REPOSITORY='https://github.com/gselfman/pinamarket.git'
if command -v git >/dev/null 2>&1; then
  if [[ -d "$PINA_CODE_DIR/.git" ]]; then
    origin="$(git -C "$PINA_CODE_DIR" remote get-url origin)"
    if [[ "${origin%.git}" != "${PINA_REPOSITORY%.git}" ]]; then echo 'В выбранном каталоге другой Git-репозиторий. Задайте отдельный PINA_CODE_DIR.' >&2; exit 1; fi
    if [[ -n "$(git -C "$PINA_CODE_DIR" status --porcelain --untracked-files=no)" ]]; then echo 'Есть изменения исходного кода. Сохраните их перед обновлением.' >&2; exit 1; fi
    git -C "$PINA_CODE_DIR" pull --ff-only
  else
    if [[ -e "$PINA_CODE_DIR" ]]; then echo 'Каталог уже существует. Укажите пустой PINA_CODE_DIR или используйте deploy/install.sh из существующего кода.' >&2; exit 1; fi
    git clone "$PINA_REPOSITORY" "$PINA_CODE_DIR"
  fi
else
  command -v curl >/dev/null || { echo 'Нужен curl либо Git.' >&2; exit 1; }
  command -v tar >/dev/null || { echo 'Нужен tar либо Git.' >&2; exit 1; }
  if [[ -e "$PINA_CODE_DIR" && ! -f "$PINA_CODE_DIR/.pina-source" ]]; then echo 'Существующий каталог не принадлежит установщику. Задайте новый PINA_CODE_DIR.' >&2; exit 1; fi
  stage="$(mktemp -d)"
  trap 'rm -rf -- "$stage"' EXIT
  curl --proto '=https' --tlsv1.2 -fsSL --retry 2 'https://github.com/gselfman/pinamarket/archive/refs/heads/main.tar.gz' -o "$stage/source.tar.gz"
  tar -xzf "$stage/source.tar.gz" --strip-components=1 -C "$stage"
  [[ -f "$stage/deploy/install.sh" && -f "$stage/app/legal.json" ]] || { echo 'Архив исходников неполон.' >&2; exit 1; }
  mkdir -p "$PINA_CODE_DIR"
  for part in app public database deploy dist tests docs .github README.md .gitignore; do cp -a -- "$stage/$part" "$PINA_CODE_DIR/"; done
  date -u +%FT%TZ > "$PINA_CODE_DIR/.pina-source"
fi
cd "$PINA_CODE_DIR"
if [[ -f app/config.php ]]; then exec bash deploy/install.sh --update; else exec bash deploy/install.sh; fi
