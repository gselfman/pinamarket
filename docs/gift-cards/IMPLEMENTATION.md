# Pina Market gift cards

The gift-card programme uses the existing PHP 8.2, MySQL and Apache application. The original account remains available for historical orders. No existing wallet balance is silently converted into gift cards.

## Delivered interfaces

- `prototype/`: an interactive, browser-local demonstration. No bank or V-DAR requests are made. Demo data and scenario controls are labelled. Buyer and supplier accounts, invoice, deferred code creation, activation registry, refund claims, company details, filled Word contract and local document upload can be reviewed here.
- `dist/giftcards/`: the server-backed interface, built from the same visual components and account shell. Its financial actions call `/api/gift/`; it does not use localStorage as the financial record. The demo controller is disabled by `pina-runtime=live`.
- Homepage: buyer proposition, issuer-specific catalogue, purchase steps, buyer account explanation, supplier proposition and enquiry form. Separate refund and guarantee pages use the agreed offer. Registration requires an unchecked acceptance box and records the document version/hash on the server.

Production assets are stored under a content-addressed `r-…` directory. An old release continues using its own assets while a new release is copied. Node is needed only to rebuild assets during development; PHP hosting serves the committed output directly.

## Contract and supplier onboarding

The published template is the agreed framework agreement. One issuer agreement covers many buyers and cards. The supplier enters its own company details, bank details and programme conditions in its account. The server allocates the contract number and fills the Word template with both parties’ details.

An approved PNG signature is configured privately, with a SHA-256 fingerprint. It is embedded in the generated document and is never a public image asset. Before the signature is configured the document explicitly says it is an unsigned draft. A signature image is not presented as a qualified electronic signature.

Generation is a CSRF-protected POST. Downloading an existing document does not create or sign another one. Uploaded supplier documents are limited to PDF/DOCX, stored outside the public directory, hashed and accessible only to authorised company members or staff. Upload does not automatically approve a company. Approval checks the current contract version and a supplier-signed copy.

The current third appendix records the actual underlying purchase and its performance. Its future-operation fields are completed by the issuer when the relevant event occurs; they are not missing buyer-specific agency agreements. See CONTRACT-COVERAGE.md for the current document-to-feature review and launch prerequisites.

## Financial states

Payment: pending → confirmed by staff after bank verification. Code issue: not issued → pending → issued, or unknown. Redemption: not redeemed → pending → redeemed, or unknown. Refund: none → requested → paid after a separately verified bank refund. Settlement: prepared → bank credited → supplier confirms receipt. The technical balance return has its own record.

The customer’s payment is tied to one issuer and order. A card is issued only after a verified bank receipt. Pina’s commission and bank charges do not reduce the customer’s nominal amount. Staff cannot settle an activation twice; the settlement item has a unique activation constraint. Shared bank-reference records prevent reusing the same bank event across the old and new modules.

The base agreement calculation is 0.1% agent remuneration, 0.2% currency-control costs and 0.6% transfer costs, recorded separately. A batch of RUB 1,000,000 therefore has a RUB 991,000 net bank payment before other separately agreed adjustments. V-DAR’s technological commission is recorded separately from these figures; the supplier’s actual platform balance is read from V-DAR.

The refund form remains available after issue and redemption. Code issue, disclosure or redemption is not an automatic legal rejection. The automated cancellation/payment path covers a wholly unused card; claims relating to an already accepted advance, defects or the underlying purchase require a separate decision and remain visible for review. An internal funding dispute does not pause mandatory consumer deadlines.

## V-DAR integration

Primary documentation checked on 2026-11-15:

- https://api.v-dar.ru/api/docs/integrator/codes
- https://api.v-dar.ru/openapi.json

The PHP adapter uses `X-API-Key` only on the server. Pina’s key creates a code with Pina’s `site_id`, a stable `payment_id` and `recipient_login` restricted to the supplier. Supplier activation uses that supplier’s key and site. Pina also checks local card ownership/issuer association; the platform restriction is additional protection.

Verified endpoints: POST `/codes/generate`, POST `/codes/activate`, GET/DELETE `/codes/{code}`, GET `/codes/`, GET `/merchants/balance/{site_id}`. Codes contain 20 uppercase Latin letters/digits. The API’s `active` status means “available to redeem”; `activated` means “redeemed”. DELETE deactivates an unused code and is distinct from supplier activation.

The provider does not document an idempotency guarantee for `payment_id`. A command is persisted and claimed once before an external mutation. Timeout, malformed success, process interruption or an ambiguous provider result remains unknown. Cron does not repeat the POST. Read-only reconciliation matches the original card name and payment ID; no match does not authorise a new issue. Supplier activation likewise remains blocked for review after an uncertain result.

The retrieved OpenAPI has no direct merchant-to-merchant balance-transfer endpoint. Its withdrawal endpoint creates a bank/USDT withdrawal, which must not be silently substituted for the intended internal return. The live system records receipt acknowledgement and queues the platform balance for manual reconciliation. Reverse-code generation/activation is not enabled without confirming that workflow and its fees with the platform. The browser demonstration also keeps bank receipt and manual balance review separate.

## Storage and security

`database/giftcards.sql` adds 19 `pg_*` tables; `giftcards-credentials.sql` adds the twentieth table for encrypted, issuer-specific merchant settings. It does not change the original wallet ledger. Money is stored as integer kopecks; API decimal strings are converted without floating-point financial arithmetic on the server. Codes use AES-256-GCM at rest and a keyed lookup index. Full codes are excluded from registries, ordinary audit records and email notifications.

The module reuses existing server sessions, CSRF protection, rate limits and staff permissions. Supplier membership is checked on every company operation. API keys, encryption key, uploaded contracts, signature and backups belong in private configuration/storage; never in browser scripts or Git. Keep the encryption key backed up with the database: replacing it would make existing codes unreadable.

## Verification status

Browser checks cover buyer checkout, email confirmation simulation, deferred code creation, supplier acceptance, duplicate operations, contract filling/download/upload, offer acceptance, legal pages and 360–1440px widths. A separate production-controller test uses a stub PHP transport and checks that no demo controls or local financial store are used. This is not a real-money acceptance test.

PHP syntax and the V-DAR request adapter are checked on PHP 8.2. The additive schema is checked twice against an isolated MySQL 5.7. GitHub workflows also check PHP and MySQL 8. Real V-DAR credentials, real bank receipts, delivery of authentication emails, uploaded signatures and the final balance-return method still require verification in the configured environment before sales are enabled.
