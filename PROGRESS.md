# Progress & Handoff Context

This file serves as a durable memory bank across sessions. If you are a fresh agent picking up this
project, **read this first**.

## The App (`datasiteAdmin`)

A multi-tier B2B data reselling platform (Admin -> Agent -> Subagent -> Customer) built on Laravel 11,
Inertia (React), and Tailwind. It connects upstream to Databundleshub (DBH) to fulfill orders.

Key architecture rules:

1. **Pint-clean**: Code must pass `vendor/bin/pint`.
2. **Compact files**: Controller/component max length ~300 lines. Break things up.
3. **No magic**: Strong typing, simple DTOs, explicit DB transactions (`DB::transaction`).
4. **Tailwind utilities only**: No `@apply` or custom CSS classes unless absolutely unavoidable.
5. **Radix UI**: Radix primitives via `components/ui` (shadcn-like).
6. **Testing**: 100% green on `php artisan test`. No new feature without a test.

## Key technical decisions

1. **DB decimal precision**: `decimal(12,4)` for raw calculations, cast to `decimal:2` in models.
2. **Wallet concurrency**: Pessimistic locking (`lockForUpdate()`) strictly enforced in `Wallet::credit()` / `debit()`.
3. **Order status**: `pending` (unpaid) -> `processing` (paid, at DBH) -> `completed` / `failed`.
4. **Profit split**: Calculated synchronously at order time, pending earnings frozen in `earnings`
   table. On `completed`, earnings transition to `credited` (and update wallets). On `failed`, `reversed`.
5. **Upstream polling**: No webhooks from DBH. A queued job polls `purchase-status` until terminal,
   then triggers `OrderSettlementService`.
6. **No intake queue**: The Developer API (`POST /api/create_order`) handles requests synchronously,
   reserving funds and attempting DBH dispatch in real-time, relying on DBH's quick response for the
   initial `processing` state. (Decision #15).
7. **Idempotency**: Client `Idempotency-Key` (or derived) stored on `orders.idempotency_key` (unique per seller).
8. **Pricing Model**: Prices are derived via `Pricing/PriceQuote`. The seller specifies phone, network, capacity; the engine resolves the cost from their assigned `PricingTier`.
9. **Default Fallbacks**: If a specific network isn't configured, `PriceQuote` falls back to the `default` network bands. If a new agent is created without a tier, they are automatically assigned the `is_default` tier ("Standard").
10. **Two order dimensions**: `orders.status` = *fulfillment* (`pending → processing → completed / failed / refunded`); `orders.payment_status` = *payment* (`paid | awaiting | failed`), orthogonal. Also `orders.source` (`portal | api | storefront`) records origin.
11. **Payment invariant (BINDING)**: `payment_status = paid` ⟺ the money is currently held. Every wallet **debit and the paid flag are set in the same `DB::transaction`** (`OrderDispatchService::createAndReserve` + `redispatch`); a prepaid **reversal refunds the wallet and flips paid → awaiting** in one commit. So a retry re-debits only when not paid. Maintain this everywhere money moves.
12. **Verify-before-fulfill**: money must be secured before the upstream call. Agents = wallet debit (instant, paid on creation). Storefront customers = gateway payment that must be verified first — order starts `awaiting`, verify → `paid` → `OrderDispatchService::fulfillPaid()` dispatches. `PaymentVerifier` is the gateway seam (currently returns `pending` — real verify lands with storefront checkout).
13. **Dual-gateway routing** (`Services/Payments/PaymentGatewayResolver`): both gateways can be active. Public checkout → Paystack preferred; agent top-up → Paystack < GHS 1,500, Moolre ≥ 1,500 (constant `AGENT_TOPUP_MOOLRE_THRESHOLD_GHS`), with fallback. Credentials in `payment_gateways` (secrets encrypted).
14. **DB-driven mail**: SMTP settings live in `email_configs` (not `.env`); `Services/Mail/DbMailConfigurator` applies them to the mailer in `AppServiceProvider::boot()`. Mail is sent via Notifications.

---

## Built so far (migrated, Pint-clean, tests green)

**DB slices:**

- Identity: `admins`, `agents`, `subagents` – phone/email/username, `email_verified_at`, `last_login_at`. `agents.pricing_tier_id`, `subagents.agent_id` (restrict-on-delete).
- `pricing_tiers` (w/ `is_default`, `is_undeletable`) → `tier_prices` → `base_costs`.
- `wallets` (polymorphic, cached `balance`) + `wallet_transactions` (signed ledger).
- `orders` (polymorphic `seller`, cascade snapshot, status + `upstream_*` tracking; includes `refunded`). Plus `source`, `idempotency_key` (unique per seller), and `payment_status` (`paid|awaiting|failed`).
- `earnings` (per-`(order,earner)`) + `withdrawals`.
- `dbh_config`, `login_logs`, `api_keys`.
- `payment_gateways` (one row per gateway `paystack|moolre`; `secret_key`/`webhook_secret` encrypted; topup min/max, `charge_percent`, moolre creds).
- `email_configs` (single-row SMTP; `smtp_password` encrypted), `registration_configs` (fee + `is_enabled`).

**Services (`app/Services/`):**

- `Databundleshub/UpstreamClient` (X-API-Key, `create_order`, `purchase-status`). Poller: `Jobs/PollUpstreamOrderStatus`.
- `Orders/OrderDispatchService` (`dispatch`, `fulfillPaid`, `redispatch`), `OrderSettlementService` (`settle`, `reverse`, `refund`, `completeManually`), `ProfitSplit`, `NewOrderData` DTO.
- `Orders/OrderListPresenter` (segment-scoped list/stats/filters for the two order pages) and `Orders/OrderBulkService` (all bulk actions).
- `Payments/PaymentGatewayResolver` (routing) + `Payments/PaymentVerifier` (gateway-verify seam, currently `pending`).
- `Mail/DbMailConfigurator` (DB SMTP → mailer at boot).
- `Pricing/CostFloor`, `Pricing/PriceQuote` (handles tier → cascade, including `default` fallback).
- `Accounts/AccountsPresenter`.
- `Withdrawals/WithdrawalService`.

**Developer API & Admin Key Minting:**

- Auth by `X-API-Key` via `Http/Middleware/AuthenticateApiKey`.
- `Api/OrderController@store` (`POST /api/create_order` - `/api/developer/purchase`) and `@show`.
- **Admin API Key Mint UI:** `AccountApiKeysController` with one-time raw-key reveal dialog.

**Admin backoffice (superadmin)** under `routes/domain_admin.php` (`admin.*`, protected by `admin.ip`). Layout: `layouts/admin-layout.tsx` + `components/admin/admin-sidebar.tsx`.

- **Overview**: Money chain, orders headline, revenue-by-network, recent orders.
- **Agent Orders** (`/admin/orders/agent`) & **Regular Orders** (`/admin/orders/regular`) — two pages, one `orders` table (agent = source portal/api; regular = storefront). Shared `OrdersPage` component + `OrderDetailDialog`, rendered by `OrdersController@agent`/`@regular` via `OrderListPresenter`. `/admin/orders` redirects to agent; sidebar has both.
  - **Both**: filters (status, network, date, debounced search across ref/phone/seller/upstream), multi-select + bulk bar (`OrderBulkService`, one `POST /admin/orders/bulk`), detail dialog.
  - **Agent actions**: poll (single), refund (single), and bulk **Sync status** / **Retry dispatch** / **Apply status** (5 statuses, money-routed through settlement). Toolbar **Export CSV** (`orders/export`, honours filters). XLSX intentionally skipped (needs `phpoffice/phpspreadsheet`).
  - **Regular actions**: **Verify payment** (gateway-branched: paid→dispatch, failed→mark failed, pending→stays), **Mark verified** (manual confirm+dispatch), **Delete** (awaiting only) — all single + bulk. Awaiting queue via the Payment filter; row ✓ quick-verify icon.
- **Pricing**: DBH-inspired **Network Tabbed Pricing** (MTN, Telecel, AT, Default Fallback). Bulk "Clone from MTN" / "Reset network" per tab. Tier CRUD (protects `is_undeletable`).
- **Accounts**: Tabbed agents/subagents. Insight cards. Actions: **create** (auto-selects `is_default` tier), add/deduct funds, **reset password** modal, manage API keys, toggle status, delete (guarded), CSV export.
- **Payments** (`/admin/payment-config`): Paystack + Moolre credentials (encrypted secrets, keep-on-blank), topup limits, charge %, and a live **routing summary** from `PaymentGatewayResolver`. Webhook URLs shown (receiver routes not built yet).
- **Top-ups**, **Ledger**, **Withdrawals**.
- **Settings**: DBH connection, admins, IP allowlist (stub), plus **Email** (DB-driven SMTP + "send test" via `TestEmailNotification`) and **Registration** (fee + open toggle) config cards.

**Seeders & Dev Data:**

- `DatabaseSeeder.php` (Production-safe): Creates the `Super Admin` account, the undeletable `Standard` tier, and sets up baseline DBH production pricing (1-100GB bands at 4.00 base / 4.50 sell across all networks).
- `DemoDataSeeder.php` (Local dev only): Generates fake agents (Agent Mensah), wallets, dummy orders, API keys. Completely separated from prod setup.

**Tests (`php artisan test`):**
Upstream client, order dispatch, pricing floor, withdrawal workflow, settings connection, dashboard, `AccountManagementTest`, `AccountApiKeyManagementTest`, `MultiGuardAuthTest`, `DeveloperApiOrderTest`. **85 tests green** as of this session.

---

## Next / open (not built)

1. **Wallet top-ups (real money in)** — DataSite's own Paystack/MoMo init + webhook (may port DBH's `PaymentInitController`/`CallbackController`/webhook handlers — logic only, not UI).
2. **Agent & Subagent portals** + **storefront (D3, forced-light)** — reuse the shared sidebar shell; per-role nav is data, not a forked sidebar.
3. **Wire real order placement** from a portal through `OrderDispatchService` (admin currently views/re-polls; the Developer API can now create live orders, but no _portal_ UI does yet).
