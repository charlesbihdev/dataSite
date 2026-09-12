# DataSite — Progress & Handoff

> **Fresh agent? Read in this order (all in `datasiteAdmin/` root):**
> 1. `AGENTS.md` — how to behave in this repo (workflow, guardrails).
> 2. `ENGINEERING_PRINCIPLES.md` — **binding** style/rules (tokens, Tailwind-only, ≤300 lines, modularity, Laravel/Inertia way, DBH is a supplier not a code donor).
> 3. `ARCHITECTURE.md` — system design + the DBH upstream contract (§1 3-domain firewall, §2/§3/§4 pricing/dispatch/settlement).
> 4. This file — what's built, what's decided, what's next.
> 5. `CLAUDE.md` — the always-loaded pointer that re-states the above as binding.
>
> Domain background: `../DATABUNDLESHUB_EXPLANATION_AND_DIFFERENCES.md`.
> Dev logins (local only): `../LOCAL_LOGIN_CREDENTIALS.md`.
> Do NOT re-derive decisions already logged here.

Stack: Laravel 13 · Inertia v3 · React 19 · Tailwind v4 · shadcn/ui · Fortify · PHPUnit · SQLite (dev).
App lives in `datasiteAdmin/`. It's a 4-tier telecom-bundle reseller platform
(Superadmin → Agent → Subagent → end customer) that buys wholesale from **Databundleshub via its API**.

---

## Before you touch code — activate the matching skill

Skills live in `.claude/skills/**` (and mirrored in `.agents/skills/**`). **Activate the relevant one
BEFORE editing, not when stuck** (ENGINEERING_PRINCIPLES §0):

| Working on… | Skill |
| :-- | :-- |
| React pages/forms/`<Link>`/`useForm`/navigation | `inertia-react-development` |
| Laravel PHP (controllers, models, migrations, requests, services, jobs) | `laravel-best-practices` |
| Tailwind classes / theme tokens | `tailwindcss-development` |
| Calling backend routes/actions from the frontend | `wayfinder-development` |
| Auth: login, registration, 2FA, passkeys, reset | `fortify-development` |
| Writing/fixing tests | `testing-best-practices` |
| Matching conventions in an unfamiliar area | `infer-conventions` |

House rules that bite most often: **every colour is a token in `resources/css/app.css`** (no raw hex/
palette, `bg-brand`/`text-success`/etc. only); **Tailwind utilities only** (no CSS/`<style>`/inline
`style`); **≤300 lines/file** (split into `components/common` or `components/<feature>` or a service);
**Wayfinder** for URLs (`@/actions/*`, `@/routes/*`); **`useForm`/`<Form>`** for posts; **file downloads
use a native `<a>`**, not `router`/`window.location`. Run `vendor/bin/pint --format agent` after PHP edits.

---

## Key decisions (settled — don't relitigate)

1. **Separate tables per account type** (`admins`, `agents`, `subagents`) — NOT one `users` table.
   End customers are **guests** (no table; phone captured on the order).
2. **Login = phone** for agents/subagents (email + username also stored). **Admins log in by email**
   (username alt; phone optional). Multi-guard auth — NOT wired yet.
3. **Tier by id:** `pricing_tiers` is a lookup list; agents point to it via `pricing_tier_id`.
4. **Pricing = per-GB** (`tier_prices`: tier × network × GB-range × `price_per_gb`). Our own rates;
   DBH cost is only a **floor guardrail** (can't price below cost — enforced in `CostFloor` + requests).
5. **Two money pools** (DBH model): **deposit wallet** (top-up → spend on buys) vs **earnings**
   (profit/commissions → withdraw). Kept separate; you buy with deposit, withdraw from earnings.
6. **Orders freeze the full price cascade** (`customer_price`, `seller_cost`, `agent_cost`,
   `base_cost`, `channel`) so profit-sharing is pure subtraction, immune to later price edits.
7. **DBH = fulfillment pipe only.** Connection-config table (`dbh_config`), no price cache. Superadmin
   sets `base_cost` by hand; actual cost reconciles from each order's `upstream_cost`. DBH is
   **poll-based — it pushes no callbacks to us**. (ARCHITECTURE §2/§4.)
8. **Profit-sharing:** `$order->seller` is polymorphic; if seller is a Subagent we fetch its parent
   Agent and split three ways (subagent profit + agent commission + platform), else agent + platform.
   Isolated in `Services/Orders/ProfitSplit`. Platform profit is not stored (no superadmin earner row).
9. **Admin never copies DBH's UI.** DBH's Blade/Bootstrap/jQuery is a *feature reference* only; we
   rebuild each screen blue/white, biizz-style, with our own components. DBH concepts that don't map
   are cut (e.g. no VTU config — single API; no agent "roles" — agent vs subagent is structural).
10. **Manual wallet funding is signed:** admin "add funds" credits on positive, debits on negative
    (guarded by `Wallet::debit` overdraw check). Same shape as DBH's payment adjustment.
11. **Account delete is guarded:** blocked if the account has orders (or, for an agent, subagents) —
    suspend instead. Bulk delete keeps the blocked ones and reports the count.

---

## Built so far (migrated, Pint-clean, tests green)

**DB slices:**
- Identity: `admins`, `agents`, `subagents` — phone/email/username, `email_verified_at`,
  `last_login_at`, Fortify 2FA columns. `agents.pricing_tier_id`, `subagents.agent_id` (restrict-on-delete), `slug`.
- `pricing_tiers` · `tier_prices` (tier×network×min/max GB×price_per_gb) · `base_costs` (admin cost floor).
- `wallets` (polymorphic, cached `balance`) + `wallet_transactions` (signed ledger).
- `orders` (polymorphic `seller`, cascade snapshot, status + `upstream_*` tracking).
- `earnings` (per-`(order,earner)`, shop_profit|commission) + `withdrawals`.
- `dbh_config` (base_url + `encrypted` api_key + is_active). `login_logs` (polymorphic audit).

**Models/traits:** `Admin/Agent/Subagent` (Authenticatable + 2FA), `PricingTier`, `TierPrice`,
`BaseCost`, `Wallet` (guarded `credit()/debit()` — DB txn + `lockForUpdate`), `WalletTransaction`,
`Order`, `Earning`, `Withdrawal`, `DbhConfig`, `LoginLog`. Traits: `Concerns/HasWallet`, `HasOrders`, `HasEarnings`.

**Services (`app/Services/`):**
- `Databundleshub/UpstreamClient` (X-API-Key, `create_order`, `purchase-status`) + `UpstreamOrderResult`
  DTO + `UpstreamException` (transport-only). Poller: `Jobs/PollUpstreamOrderStatus` (`$tries`-capped,
  re-polls via `release()` while processing — DBH pushes no callbacks).
- `Orders/OrderDispatchService` (`createAndReserve` → freeze cascade + debit wallet + pending earnings in a
  txn; `sendUpstream` → call DBH, route to settle/reverse/poll), `OrderSettlementService` (idempotent
  `settle()`/`reverse()`), `ProfitSplit`, `NewOrderData` DTO.
- `Pricing/CostFloor` (per-GB cost guardrail). `Accounts/AccountsPresenter` (rows/stats/analytics).
- `Withdrawals/WithdrawalService` (pending→approved/rejected→paid state machine).

**Theme:** blue/white tokens applied in `resources/css/app.css` (`--brand*` + semantic
`--success/--warning/--danger/--info`); `--primary`/`--ring` point at brand. Reusable frontend primitives
in `components/common/`: `PageHeader`, `StatTile`, `StatusBadge` (all status colour decided here),
`DataTable` (house-style: rounded bordered card, shaded header band, roomy hover rows), `Pagination`,
`EmptyState`; helpers in `lib/format.ts` (`cedis`, `gb`).

**Admin backoffice (superadmin) — the 8 agreed sections, all live** under `routes/domain_admin.php`
(`admin.*`, open in local only; TODO guard + IP allowlist when multi-guard auth lands). Layout:
`layouts/admin-layout.tsx` + `components/admin/admin-sidebar.tsx`; pages in `pages/admin/*`.
- **Overview** (`DashboardController`): money chain (revenue − supplier = gross = platform + reseller,
  all off actual `upstream_cost`), orders headline (done+processing; failed in red), agent/subagent
  counts, revenue-by-network, recent orders.
- **Orders** (`OrdersController`): filters (status/network/search), detail dialog, re-poll action.
- **Pricing** (`PricingController`): base cost + tier price side-by-side, floor-guardrail validation.
- **Accounts** (`AccountsController` + `components/admin/accounts/*`): agents/subagents tabbed by
  `?type`, search + status filter, DBH-style stat cards (Total, Active 30d, Total Balances, Total
  Orders, Pending Orders, Total Revenue), per-tab insight cards (performance / wallet spread / busiest).
  Actions: **create** (with opening balance), **add/deduct funds**, **reset password**, suspend/activate,
  **delete (guarded)**, **bulk** reset/suspend/activate/delete, and **CSV export** (GET download).
- **Top-ups**, **Ledger** (search), **Withdrawals** (state transitions), **Settings** (DBH connection
  upsert, admins list, IP-allowlist placeholder).

**Tests (`php artisan test`):** upstream client, order dispatch, pricing floor, withdrawal workflow,
settings connection, dashboard, and `AccountManagementTest` (create/opening balance, funds ±/overdraw,
reset, delete guard, bulk suspend, CSV export, search). Full suite green. `phpunit.xml` carries a test `APP_KEY`.

**Dev data:** `DemoDataSeeder` (local only) — superadmin + one agent + one subagent (funded wallets),
tier, base costs + tier prices, and a spread of orders/top-ups/withdrawals. NOTE: order creation is
**not** idempotent (re-seeding adds 6 more orders); make it `firstOrCreate` before relying on totals.

---

## Next / open (not built)

1. **Auth wiring** — multi-guard login per domain (Fortify is still global). Agent registration scoped
   to the admin/agents domain; role enforcement (only agent may invite subagent) + reserved-slug guard;
   then lock `/admin` behind the admin guard + IP allowlist. Flagged in `routes/domain_admin.php`.
2. **Wallet top-ups (real money in)** — DataSite's own Paystack/MoMo init + webhook (may port DBH's
   `PaymentInitController`/`CallbackController`/webhook handlers — logic only, not UI).
3. **Agent & Subagent portals** + **storefront (D3, forced-light)** — reuse the shared sidebar shell;
   per-role nav is data, not a forked sidebar.
4. **Wire real order placement** from a portal through `OrderDispatchService` (admin currently views/
   re-polls; nothing creates a live order yet outside the seeder).
5. **Make `DemoDataSeeder` order creation idempotent** (see note above).
