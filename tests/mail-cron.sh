set -euo pipefail
pina_cron_test=$(mktemp -d)
trap 'rm -rf "$pina_cron_test"' EXIT
cat > "$pina_cron_test/crontab" <<'STUB'
#!/bin/bash
if [[ "$1" == '-l' ]]; then cat "$PINA_CRON_TEST_STORE"; else cp "$1" "$PINA_CRON_TEST_STORE"; fi
STUB
chmod +x "$pina_cron_test/crontab"
export PINA_CRON_TEST_STORE="$pina_cron_test/store"
printf '0 1 * * * /some/backup\n' > "$PINA_CRON_TEST_STORE"
PATH="$pina_cron_test:$PATH" bash deploy/mail-cron.sh >/dev/null
PATH="$pina_cron_test:$PATH" bash deploy/mail-cron.sh >/dev/null
[[ $(wc -l < "$PINA_CRON_TEST_STORE") == 2 ]]
grep -q '/some/backup' "$PINA_CRON_TEST_STORE"
grep -q 'pina-market-mail' "$PINA_CRON_TEST_STORE"
echo 'PASS: cron preserves existing jobs and installs only once'
