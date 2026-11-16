# Gift-card deployment on the existing hosting account

The existing installation profile contains `https://pina.market`, `$HOME/www/pina.market`, PHP `/opt/php/8.2/bin/php`, MySQL host `127.0.0.1`, database `u3266069_pina_market`, user `u3266069_pina_market_sql` and SMTP `mail.hosting.reg.ru:465` with SSL. Passwords are not included. The SSH hostname/login is not present in that profile.

Do not use the old remote bootstrap command for this change: it fetches `main`. Use the reviewed gift-card checkout or the provided archive. Do not run deployment from the prototype directory.

## One-command upgrade of the existing reg.ru site

Run on the existing hosting account, after reviewing this branch:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/gselfman/pinamarket/codex/gift-cards-redesign/deploy/regru-giftcards.sh)
```

To check without changing the website, database or configuration, append `--check`. The bootstrap downloads a fresh checkout pinned to a resolved commit SHA, outside the web root; it never updates the legacy checkout or pulls `main`. It uses the existing active private configuration, including MySQL and SMTP passwords, and discovers the existing FastCGI PHP handler. The DB name/user in the previous profile are retained through that configuration. It does not ask for new administrator passwords or recreate customer accounts.

Before the command, optionally upload the separately supplied private V-DAR JSON to `$HOME/.pina-vdar.json` with permissions `0600`. It contains `merchant_id`, `site_id`, `recipient_login`, and `api_key`. Never upload it into `www`, publish it in Git, or paste it into the shell command. `PINA_GIFT_VDAR_CONFIG` can name another private path. Without this file the site still installs with sales closed; the Pina key and supplier settings can be configured through the staff interface at `https://pina.market/#/admin`.

The first successful switch publishes the new website at the existing root and preserves the old public tree in `$HOME/www/pina.market/V0`. V0 is a **private restoration archive**, not a second working financial website: its directory is mode 0700, `.htaccess` denies access and the parent blocks `/V0`. Its original `.htaccess` is retained as `.pina-original-htaccess`; original private PHP releases are left intact outside the document root. The installer also creates full file, DB and private-config backups outside the web root. ACME certificate files remain at their original `.well-known` location. Failed HTTPS verification restores the original website. A pre-existing unowned V0 causes refusal; repeat successful installs preserve the first V0 and encryption key.

The installer configures the mail and gift-card cron jobs once while preserving unrelated jobs. It copies configuration into an immutable new PHP release. On first installation sales stay closed until signature, legal settings and approved supplier programmes are configured. Repeated upgrades preserve the existing sales setting. No real payment/code is created by the installer.

## Merchant settings in administration

Open `/giftcards/#/admin` using a verified staff account. In gift-card homepage mode `/admin/` redirects there, including existing bookmarks; staff login from the previous login page also opens the new administration. The explicit link `/admin/?legacy=1` retains access to prior operations without starting the V0 archive. A staff email login also opens administration directly. The **Настройки мерчантов V-DAR** section has a separate entry for Pina and each supplier company. Enter Merchant UUID, trading-point UUID, recipient login and the company's own API key. Saving performs only a balance GET to check key access and the returned point/login. Merchant UUID ownership must also be checked by staff in V-DAR: the balance API does not return a merchant UUID and cannot prove it.

Keys are encrypted with AES-256-GCM in `pg_merchant_credentials`; GET responses contain only `hasApiKey`, never the key or ciphertext. A blank key preserves the existing one. Supplier settings cannot reuse Pina's identifiers or another stored merchant. Once orders exist, destinations cannot change through this form; only the key may rotate with unchanged identifiers. Changes are blocked while operations have an uncertain result. Saving settings does not approve a contract or open sales.

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

`--init` preserves existing configuration, generates the code-encryption key once and defaults sales and homepage switching to off. `--pina` asks for Pina’s key, site UUID and recipient login. The supplier can be configured through administration after its company record exists. A server-side alternative is:

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

A settlement is prepared from explicit confirmed activation IDs, with a UUID request key. Each activation can be reserved for only one active settlement. Cancellation before dispatch releases that reservation while preserving historical registry items. Staff mark bank credit only after checking the receiving-bank evidence; the supplier then confirms the actual received amount and reference. This acknowledgement does not falsely complete a technical transfer that was not performed.

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


## Update dated 2026-11-16

The installation package now applies additive migrations 004 (TOTP and audit), 005 (reserved compatibility table; the bank approval workflow is not used) and 006 (underlying purchase declarations). Never edit the checksums of already applied migrations. Existing customers, encryption keys and financial records are retained; no historical customer activity is manufactured.

After updating, staff must enrol their authenticator and retain the ten recovery codes. New sales additionally require approval of the published offer, a current signed issuer contract and configured programme. See OPERATIONS.md and CONTRACT-COVERAGE.md. Bank communication and any approvals take place outside the website. No bank account/role, per-contract bank approval form or daily bank-review sales gate exists.

The hosting installer retains the existing mail/worker schedules and adds the daily audit archive at 03:15 server time. The archive remains searchable and exportable. CLI bank import is documented in OPERATIONS.md; keep statement files and previews outside the public directory.
