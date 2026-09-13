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
15. **Agent Package Pricing is management-only**: `agent_package_prices` stores an agent's own selling/sub-agent prices with `cost_price` frozen from their tier at save time. It does **not** yet drive live checkout — orders still price through the tier cascade. Wiring it in is a deliberate future change (open #3).
16. **Withdrawals draw from the earnings pool**, never the deposit wallet. Available = `earningsBalance()` (credited earnings − non-rejected withdrawals). Per-method minimums in `config/withdrawals.php` (momo/credit = GHS 20). A request is a `pending` row (already reserved); admin settles via `WithdrawalService`; the agent may cancel while pending (→ `rejected`, frees the reservation).
17. **Referral QR** is a **PNG data URI** (`endroid/qr-code` + GD) cached in `agents.referral_qr`, generated once and **deferred-loaded** (`Inertia::defer` + `<Deferred>` skeleton). Regenerated on demand and auto-invalidated (nulled) when the agent's `slug` changes, since it encodes `/buy/{slug}`.

---

## Built so far (migrated, Pint-clean, tests green)

**DB slices:**

- Identity: `admins`, `agents`, `subagents` – phone/email/username, `email_verified_at`, `last_login_at`. `agents.pricing_tier_id`, `subagents.agent_id` (restrict-on-delete).
- `pricing_tiers` (w/ `is_default`, `is_undeletable`) → `tier_prices` → `base_costs`.
- `wallets` (polymorphic, cached `balance`) + `wallet_transactions` (signed ledger).
- `orders` (polymorphic `seller`, cascade snapshot, status + `upstream_*` tracking; includes `refunded`). Plus `source`, `idempotency_key` (unique per seller), and `payment_status` (`paid|awaiting|failed`).
- `earnings` (per-`(order,earner)`) + `withdrawals` (now w/ `method` `momo|credit` + `destination`).
- `agent_package_prices` (per `agent × network × capacity_gb`: `cost_price` frozen from tier, `selling_price`, `subagent_price`, `is_active`).
- `agents` gained referral fields: `store_name`, `whatsapp_number`, `whatsapp_group_link`, `referral_clicks`, `referral_qr` (PNG data URI cached).
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
- `Cart/CartService` (session-backed Place-Order basket), `Cart/CartCheckoutService` (re-prices each line and dispatches through `OrderDispatchService`), `Cart/OrderFileParser` (CSV natively + XLSX/XLS via `phpoffice/phpspreadsheet`).
- Support helpers: `Support/GhanaMobileNetwork` (prefix→network detect + agent size validation; `mtn/telecel/at`), `Support/DateRange` (shared `?range/from/to` resolver), `Support/QrCodeGenerator` (PNG data-URI QR via `endroid/qr-code` + GD).

**Developer API & Admin Key Minting:**

- Auth by `X-API-Key` via `Http/Middleware/AuthenticateApiKey`.
- `Api/OrderController@store` (`POST /api/create_order` - `/api/developer/purchase`) and `@show`.
- **Admin API Key Mint UI:** `AccountApiKeysController` with one-time raw-key reveal dialog.

**Admin backoffice (superadmin)** under `routes/domain_admin_agents.php` (`admin.*`, protected by `admin.ip`). Layout: `layouts/admin-layout.tsx` + `components/admin/admin-sidebar.tsx`.

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

**Agent portal (D1)** under `routes/domain_admin_agents.php` (`agent.*`, `auth:agent`). Shell: shared `components/app-sidebar.tsx`. Sidebar nav (in order): Dashboard · Orders · Transactions · Packages · Referral Link · My Subagents · Sub-agent Sales · Withdrawals. All list tables use **server-side pagination at 30/page**; filter controls sit in a bar attached to the table; toasts via `Inertia::flash('toast', …)` surfaced by `useFlashToast`.

- **Dashboard** (`DashboardController`): date-range-filtered stats (orders, revenue [gross sales], wallet balance, subagents), recent orders, and the **Place-Order card + Cart panel**.
  - **Place-Order cart** (session-backed, survives refresh): three inputs — **Single**, **Excel/CSV upload**, **Bulk paste** — funnel through `CartService`; `CartController` (store/storeBulk/upload/destroy/checkout) + Form Requests. Checkout re-prices each line and dispatches via `OrderDispatchService` (same cascade as the Developer API). Components in `components/agent/place-order/**`; live network detection from `lib/networks.ts`.
- **Orders** (`OrdersController`): the agent's own orders, filters (status · network · debounced search · header date-range), `StatusBadge`, **View → `OrderDetailDialog`** modal. Mirrors the admin orders page arrangement.
- **Transactions** (`/transactions`, `WalletController`): the wallet's signed ledger with columns Type · **Source (USER/ADMIN)** · Amount · Order ID · **Payment Src (Paystack/Wallet)** · Status · Balances · Code · Date · **View → `TransactionDetailDialog`**. Filters: Source · Payment Source · Type · Search · header date-range. (Was the "Wallet & Top-ups" stub — renamed slug + route.)
- **Packages** (`PackagesController`): agent sets a **selling + optional sub-agent price** per package. Select package → **cost auto-fills from their tier** (`PriceQuote`, frozen on save) → profit/margin computed. Stats via SQL aggregates; toggle/delete via row menu. **Management-only — does NOT drive live checkout pricing yet** (see decision #15).
- **Referral Link** (`ReferralController`): the customer `/buy/{slug}` storefront link with **PNG QR** (`QrCodeGenerator`, cached in `agents.referral_qr`, **deferred-loaded** behind a skeleton, **Download PNG** + **Regenerate**; auto-invalidated when the slug changes), copy/WhatsApp share, **Performance** (clicks/sales/revenue/conversion), **Referral Contact Details** form (store name, WhatsApp number/group), and the **active packages customers will see**.
- **My Subagents** (`SubagentsController`): recruitment link (`/register?ref=slug`) + copy, roster table (Store vs Account status, wallet, Visit-store action).
- **Sub-agent Sales** (`SubagentSalesController`): orders sold through the agent's sub-agents, the **agent margin** per order (`seller_cost − agent_cost`), filters (sub-agent · status · network · search), 30-day margin KPI. Scoped so one agent never sees another's.
- **Withdrawals** (`WithdrawalController`): payout of **matured earnings** (the earnings pool via `earningsBalance()`, not the deposit wallet). Request form (method momo/credit w/ per-method minimums from `config/withdrawals.php`, amount, destination) + **history** (paginated, agent-cancellable while pending) shown side-by-side. `StoreWithdrawalRequest`; settlement still via admin `WithdrawalService`.
- **Settings → Profile**: added **phone / username / storefront-handle (slug)** fields; **Security & Appearance nav removed**, **Delete-account section removed** (routes still exist, just unlinked).
- Shared UI added: `common/Amount` (signed, colored money cell — reused by admin ledger too), `ui/textarea` primitive, `components/agent/{order,transaction}-detail-dialog.tsx`.

**Seeders & Dev Data:**

- `DatabaseSeeder.php` (Production-safe): Creates the `Super Admin` account, the undeletable `Standard` tier, and sets up baseline DBH production pricing (1-100GB bands at 4.00 base / 4.50 sell across all networks).
- `DemoDataSeeder.php` (Local dev only): Generates fake agents (Agent Mensah), wallets, dummy orders, API keys. Completely separated from prod setup.
- `AgentPortalDemoSeeder.php` (Local dev only): Fills ONE agent's whole portal (target via `SEED_AGENT`, else newest) — funded wallet + orders + matching wallet ledger, a sub-agent, sub-agent sales, **earnings derived via `ProfitSplit`** (real margin, not invented), a pending withdrawal, a package price, and referral clicks/contact. Idempotent, skips in production. Run: `php artisan db:seed --class=AgentPortalDemoSeeder`.

**Tests (`php artisan test`):**
Admin/core: upstream client, order dispatch, pricing floor, withdrawal workflow, settings connection, dashboard, `AccountManagementTest`, `AccountApiKeyManagementTest`, `MultiGuardAuthTest`, `DeveloperApiOrderTest`. Agent portal: `PlaceOrderCartTest`, `AgentOrdersTest`, `AgentWalletTest`, `AgentSubagentsTest`, `AgentSubagentSalesTest`, `AgentWithdrawalTest`, `AgentPackagesTest`, `AgentReferralTest`, updated `Settings/ProfileUpdateTest`. **142 tests green** as of this session.

---

## Next / open (not built)

1. **Wallet top-ups (real money in)** — DataSite's own Paystack/MoMo init + webhook (may port DBH's `PaymentInitController`/`CallbackController`/webhook handlers — logic only, not UI). Until then the Transactions "Payment Src" column derives **every top-up as "Paystack"** and top-ups are admin-funded only.
2. **Subagent portal (D2)** + **storefronts** — the **D2 agent buy page (`/buy/{slug}`) and D3 subagent storefront are NOT built**: the referral QR/link and `referral_clicks` counter point at a page that doesn't exist yet (clicks aren't incremented live). Subagents can't log in and sell yet.
3. **Wire Package pricing into live checkout** — `agent_package_prices` is management-only; make `PriceQuote`/`OrderDispatchService` prefer an agent's saved package price (and the sub-agent price for their sub-agents), falling back to the tier cascade. Own change + fresh money-path tests.
4. **Agent-facing profile deletion / 2FA / appearance** — routes still exist but are unlinked from the settings nav; decide whether to restore or delete.

## Notes / conventions established this session

- **Agent portal is DONE** (dashboard, cart-based order placement, orders, transactions, packages, referral link, subagents, sub-agent sales, withdrawals, profile). Order placement now flows from the portal cart through `OrderDispatchService`.
- **Deps added:** `phpoffice/phpspreadsheet` (Excel cart upload), `endroid/qr-code` (+ `bacon/bacon-qr-code` transitive) for PNG QR.
- **Revenue vs Earnings:** dashboard **Revenue** = gross sales (`customer_price` of delivered); withdrawals **Total Earnings** = profit/commission (the withdrawable pool). Different metrics — labelled to say so.
