# Gift-card deployment on the existing hosting account

The existing installation profile contains `https://pina.market`, `$HOME/www/pina.market`, PHP `/opt/php/8.2/bin/php`, MySQL host `127.0.0.1`, database `u3266069_pina_market`, user `u3266069_pina_market_sql` and SMTP `mail.hosting.reg.ru:465` with SSL. Passwords are not included. The SSH hostname/login is not present in that profile.

Do not use the old remote bootstrap command for this change: it fetches `main`. Use the reviewed gift-card checkout or the provided archive. Do not run deployment from the prototype directory.

## Prepare a separate checkout

After the code has been approved for publication, obtain branch `codex/gift-cards-redesign`. Preserve the current production checkout and its configuration. If using the same hosting account, copy `app/config.php` from the existing checkout to the new checkout, preserving restrictive permissions. Keep `public_dir` and `base_url` pointed at the intended domain; a separate staging domain must use its own database and SMTP settings.

The package already includes built browser assets. Rebuilding them is optional during development:

```bash
node deploy/build-giftcards.mjs
```

PHP hosting does not need Node, npm or a build service.

## Configure privately

Run from the new checkout. Values containing secrets are entered interactively and are not command arguments:

```bash
/opt/php/8.2/bin/php deploy/giftcards-configure.php --init
/opt/php/8.2/bin/php deploy/giftcards-configure.php --pina
/opt/php/8.2/bin/php deploy/giftcards-configure.php --enable-preview
```

`--init` preserves existing configuration, generates the code-encryption key once and defaults sales and homepage switching to off. `--pina` asks for Pina’s key, site UUID and recipient login. The supplier key is configured after its company UUID exists:

```text
php deploy/giftcards-configure.php --issuer COMPANY_UUID
```

Add the approved PNG signature with `--signature`. Use `--legal` for the bank-approved payment instruction and approved offer date. Manual balance reconciliation has been selected for this release; `--manual-balance-return` selects that mode for an older configuration. The agreed mode is manual balance reconciliation. No automatic reverse transfer is performed.

## Back up and migrate

`deploy/giftcards-release.sh` defaults to `--check` and does not publish. The additive migration can be inspected without applying it:

```bash
/opt/php/8.2/bin/php deploy/giftcards-migrate.php
```

For the actual update:

```bash
bash deploy/giftcards-release.sh --deploy
```

The release script creates a verified full JSON backup of all `pm_*` and `pg_*` tables, applies the restartable migration, checks required PHP extensions/private configuration and invokes the existing installer in update mode. The installer backs up public files, creates an immutable private PHP release, copies versioned static assets, switches the active release pointer and checks HTTPS routes, API mode, release ID and asset checksums. A failed installer check restores the previous pointer and assets. A separate private archive includes the encryption key/configuration, approved signature and uploaded documents. An additive migration remains in place after code rollback; do not drop financial tables or restore a database over later transactions as a routine rollback.

The module initially opens at `/giftcards/`. The old homepage/account remains available. After the programme is accepted, `--switch-home` followed by the normal release process switches the main homepage to gift cards. New legacy deposits/service purchases are then blocked, while historical operations remain readable and can be completed or refunded through their original process.

## Supplier approval and operations

The supplier uses its own account to fill details, prepare/download the contract and upload the signed copy. Upload alone does not open sales. Staff review the current contract, configure the supplier’s V-DAR site/key, approve the signed document and agree the programme before approval.

Operational actions can be issued through the authenticated web interface or the server-side staff tool. The tool takes an action name, a private JSON file and an existing staff email:

```text
php deploy/giftcards-admin.php ACTION/ /PRIVATE/PATH/request.json STAFF_EMAIL
```

Supported actions: `bank-confirm/`, `document-approve/`, `issuer-approve/`, `settlement-prepare/`, `settlement-bank-credit/`, `refund-cancel-code/`, `refund-paid/`, `balance-reconcile/`, `retry-safe-command/`. The JSON structure is the corresponding `/api/gift/admin/…` request. Keep these files private; do not paste keys or signed contracts into GitHub issues.

Issuer approval requires its current signed contract, configured site/key and programme fields: `nominals` (integer kopecks), HTTPS `rules`, `expires_seconds` up to 31536000, `issue_deadline`, `request_deadline`, `use_country`, `redemption`, `refund`, and `partial_redemption: false`. The supplier’s signed terms and configured programme must agree. A card is redeemed for its full nominal in this version.

A settlement is prepared from explicit confirmed activation IDs, with a UUID request key. Each activation can belong to only one settlement. Staff mark bank credit only after checking the receiving-bank evidence; the supplier then confirms the actual received amount and reference. This acknowledgement does not falsely complete a technical transfer that was not performed.

## Background work and recovery

Run the gift-card worker once per minute and retain the existing mail-queue worker:

```text
/opt/php/8.2/bin/php /ABSOLUTE/PATH/TO/CHECKOUT/deploy/giftcards-worker.php
```

The worker processes at most 20 queued commands per run. `sending` and `unknown` commands are never automatically retried. For an uncertain issue:

```text
php deploy/giftcards-reconcile.php CARD_UUID
```

This performs read-only provider queries and restores the one matching original issue when it can be proved. Ambiguous activation or cancellation requires reconciliation against provider operations by an authorised operator. A browser status or a missing response is not a basis for a second financial mutation.

Before enabling sales, verify actual email login, an authorised test bank receipt, issue restricted to the intended supplier, activation with the other merchant key, platform fees/actual balance, registry, bank settlement, supplier acknowledgement, the manual balance-reconciliation process and both unused/used-card claim paths. Use `--enable-sales` only after these checks and then publish the configuration through the release process. The command refuses missing signature, bank/legal settings, keys or an unconfirmed return method.

To stop new sales use `--disable-sales` and publish configuration. To restore the previous homepage use `--legacy-home` and publish. Keep existing paid orders, claims, code records and uploaded documents available for fulfilment and reconciliation.
