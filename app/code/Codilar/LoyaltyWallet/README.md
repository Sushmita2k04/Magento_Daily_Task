# Codilar_LoyaltyWallet

Loyalty points and store wallet (BRD-01) for Magento Open Source 2.4.9, INR only, registered customers only.
Two independent immutable ledgers (points, wallet money) with balance projections, FIFO lots for expiry, and a
reconciliation command. Everything is configured under Stores > Configuration > Sales > Loyalty Points and Store
Wallet; there is no admin management screen by design (CLI and configuration only).

### Commands
| Command | Purpose |
|---|---|
| `codilar:points:expire [--dry-run]` | Expire points past their date (cron 02:00; safe to repeat) |
| `codilar:loyalty:sweep` | Release stuck wallet reservations, accrue missed points, finish missed reversals (cron 02:30) |
| `codilar:wallet:adjust` | Manual credit/debit; reason and operator mandatory |
| `codilar:wallet:reconcile [--customer-id]` | Read-only ledger vs balance vs lot check; non-zero exit on drift |

### Operating notes
* Disabling the module stops accrual, conversion and wallet use, but cancellations and refunds still reverse points
  and return wallet money, so ledgers stay consistent.
* A customer with any points or wallet history cannot be deleted (`Plugin\PreventCustomerDeletion`).
* Run `codilar:wallet:reconcile` after any manual database work; it is the BR-06 safety net.
* Rates, expiry, minimum conversion, maximum redemption share and the refund-to-wallet switch are store
  configuration (BR-07). `max_redemption_percent` ships as 50 as a placeholder pending business confirmation.

## Conventions
* Money is a **decimal string, scale 4** (`Model\Decimal`). Floats are rejected on input and never used for
  arithmetic; combine amounts with bcmath only.
* Ledgers are append-only. `PointsLedgerRepository` / `WalletLedgerRepository` validate every entry
  (`Model\Validator\*`) before inserting, and the ledger resource models refuse updates and deletes.
* Balance changes: open a transaction, `BalanceLocker::lock()`, check the idempotency key, append ledger rows,
  `set*Balance()`, commit.

## Ledger immutability: why there are no database triggers
Application-level protection (above) is in place. Database-level enforcement was evaluated and not added:
* Magento 2.4.9 declarative schema supports neither triggers nor CHECK constraints, so they would need raw SQL
  in patches that `setup:upgrade` does not manage or verify.
* Triggers need extra privileges (or `log_bin_trust_function_creators`) that often do not exist on managed
  hosting, and would fail deployments there.
* Magento uses one DB user for everything, so privilege-based protection (revoking UPDATE/DELETE) is not possible.
* The `unsigned` balance columns do not reject negatives on this server (non-strict mode clamps instead); the
  application guard in `BalanceLocker` is the real protection.

The additional safeguard is reconciliation (`codilar:wallet:reconcile`, Stage 3), which recomputes every
balance from ledger sums alone and reports drift, including any row altered outside the application.

## Tests
* Unit (includes controller security and a template-escaping scan):
  `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Codilar/LoyaltyWallet/Test/Unit`
* DB (real tables, each test rolled back; not Magento integration-framework tests):
  `vendor/bin/phpunit -c app/code/Codilar/LoyaltyWallet/Test/Db/phpunit.xml`
  `ConcurrencyDbTest` is the exception: it starts real parallel PHP processes, commits rows for customer 990100 and
  removes them afterwards. `DbTestCase` resets the sales AUTO_INCREMENT counters after every test.
* Live storefront check (AC-07 cache isolation, form keys, identity injection, GraphQL totals). Creates and removes
  two throw-away customers; dev stores only:
  `php app/code/Codilar/LoyaltyWallet/Test/Http/storefront_check.php http://magento.local`
* Code style: `vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml --severity=6 app/code/Codilar/LoyaltyWallet`
* After a DB test run delete `Test/Db/.phpunit.result.cache`.

## Wallet services and commands (Stage 3)

- `Model\Wallet\WalletService`: `credit()`, `debit()` and `clawback()`. Each locks the customer, honours the
  idempotency key, appends the ledger row and updates the balance in one transaction. A debit that would take the
  balance below zero is refused. A clawback takes what the wallet holds and records any shortfall as a `WRITE_OFF`
  row (amount 0, shortfall in `memo_amount`), so the balance is never negative (BR-03).
- `bin/magento codilar:wallet:adjust --customer-id --type credit|debit --amount --reason --operator [--request-key]`
  posts a manual adjustment. Reason and operator are mandatory.
- `bin/magento codilar:wallet:reconcile [--customer-id]` is read-only. It compares balances, lots and running
  `balance_after` values with the ledgers and exits non-zero on any discrepancy.

## Storefront (Stage 4)

- Private customer-data section `codilar_loyalty` (`CustomerData\Balance`): spendable points and the formatted
  wallet balance of the logged-in customer. It is fetched by the browser, never written into a cached page (NFR-02).
- Header widget: `view/frontend/templates/header/balance.phtml` plus a Knockout component. It is attached to the
  theme's header through the named block `codilar.header.top` and a `getChildHtml()` call in
  `samyakkHeadertop.phtml`. The theme has no minicart, so the component requests its own section on load.
- Account page `/loyalty/account` (guests are sent to login): balances, next expiry, convert form, and points and
  wallet history (10 per page, cursor links `?pb=` / `?wb=`). The customer always comes from the session.
- Convert form posts to `/loyalty/account/convert` with the form key and a per-page-view request key, so a double
  submit converts once. The key is prefixed with the customer ID before use.
- Displayed points exclude points past expiry that the daily run has not closed yet; the ledger is unchanged.

## Checkout and orders (Stage 5)

- Quote total `codilar_wallet` (after `grand_total`): amount = least of the requested amount (or all), the wallet
  balance, `max_redemption_percent` of the order total before wallet, and the order total. Cart page box applies or
  removes it (`/loyalty/wallet/apply`, `/loyalty/wallet/remove`). Totals are Magento floats; this module converts at
  the boundary with `Decimal::fromFloat()` and does its own arithmetic in decimal strings.
- Placement: `RedeemWallet` (submit_before) debits under the customer lock and stamps the order; if another order
  spent the balance first, no order is placed. `ReleaseWallet` (submit_failure) posts `REDEMPTION_RELEASE` unless the
  order was saved. `AccruePoints` (submit_success) awards points and never blocks the order.
- `codilar:loyalty:sweep` (cron 02:30) releases debits whose order was never saved and accrues points for orders
  that missed them. Both are idempotent.
- Invoice: the wallet-paid amount is taken off the earliest invoices first. Credit memo: the wallet gets its
  proportional share back (`CREDIT_REFUND`, credited inside the refund transaction), the rest goes through the
  payment method. Config switch: `refund_wallet_portion_to_wallet`.
- Order, invoice and credit memo pages and emails show a "Store Wallet" line.
- The redemption ledger row cannot carry the order ID (the order does not exist yet and the ledger is immutable);
  it carries the order's increment ID.

## Cancellation and refunds (Stage 6)

- `Model\Order\ReversalProcessor::process($order)` works out from the order's current state what should be true and
  does only the difference, so it is safe to call after every save, from the sweeper, or by hand.
  - Cancelled order: the wallet amount that was not invoiced goes back (`CREDIT_CANCELLATION`, key `cancel:o<id>`),
    then all earned points are reversed.
  - Refunded credit memos: earned points are reversed in proportion to the refunded share of the subtotal after
    discounts (cumulative, so partial refunds add up exactly; a full refund reverses everything).
- `Model\Points\ReversalService` reverses one order's lot: unspent points are removed (`REVERSAL`), converted points
  are clawed back at the value they were converted at (`CLAWBACK`, clipped at a zero wallet, shortfall recorded as
  `WRITE_OFF`, BR-03), expired points need nothing. Progress is kept on the lot (`points_reversal_handled`,
  `points_clawed`).
- `ProcessOrderReversals` runs after the order / credit memo transaction has committed and never throws, so it
  cannot block a cancel or refund. `codilar:loyalty:sweep` (nightly) repeats anything it missed.
- Wallet restore runs before the clawback, so a customer who got their wallet money back is not written off for it.

## Known limits and decisions (Stage 7)

* **React checkout.** On this store `hyva_react_checkout/general/enable` is 1 at store scope, so `/checkout/` goes to
  the React checkout. Its GraphQL cart already reports the grand total reduced by the wallet, but it shows no
  "Store Wallet" line and has no apply control; wallet is applied on the Luma cart page. A line item in the React
  app needs a change in that app, not in this module.
* **Third-party email leak (not this module).** `mageplaza/module-smtp` block `mp.smtp.script` prints the logged-in
  customer's email and name into the page HTML. With the full page cache on, another customer can be served that
  copy. The storefront check reports it. This module never puts a balance in cacheable HTML.
* Magento totals are floats; this module converts at the boundary (`Decimal::fromFloat`) and does its own arithmetic
  in decimal strings. Amounts shown to customers are rounded half-up to 2 places.
* No REST/GraphQL API for the wallet (Phase 1). Guest checkout and non-INR currencies are unsupported by design.
