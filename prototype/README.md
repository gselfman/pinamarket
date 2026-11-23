# Interactive Pina Market prototype

Serve this directory with a static HTTP server and open `index.html` over HTTP. No build is required. Main routes: `#/`, `#/business`, `#/account`, `#/merchant`, `#/merchant/company`, `#/legal/refund`, `#/legal/guarantees`.

All suppliers, orders, payments and codes in this directory are demonstration data. Scenario controls simulate bank receipt and supplier events. Customer codes are created only after a confirmed demo payment and an explicit issue request. Company details and cards are stored in this browser’s localStorage; an uploaded test contract is kept in IndexedDB and can be downloaded again. Reset clears both. No real API keys, bank payment, email or signature are used.

The account layout, selectors, escaping, modal/focus lifecycle, delegated actions, original logo, stylesheet and Manrope typography are adapted from the existing `dist/app.js` and `dist/style.css`. The old wallet model is replaced with issuer-specific orders and separate issue/redemption/refund/settlement states. The prototype does not connect to the production PHP account or its user database.

`live-app.js` is the production controller and is not loaded by this demonstration. `deploy/build-giftcards.mjs` creates the separate server-backed assets and sets `pina-runtime=live`. Deployment and remaining environment checks are described in `docs/gift-cards/DEPLOYMENT.md`.
