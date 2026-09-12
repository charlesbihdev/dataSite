# DataSite — Architecture

> Read alongside `ENGINEERING_PRINCIPLES.md` (binding rules) and
> `../DATABUNDLESHUB_EXPLANATION_AND_DIFFERENCES.md` (domain model). This file is the source of
> truth for **how the system is split and how it talks to its supplier**.

## 1. One codebase, one DB, three host-bound domains

DataSite is a **modular monolith**: a single Laravel 13 + Inertia app on a single database. The
tier separation the business needs (see below) is achieved by **domain-bound routing**, not by
separate apps. Shared logic (pricing, wallets, commissions, upstream supplier client) is written
once and reused by every surface.

### The three surfaces (URL-truncation firewall)

Each tier's **registration entry point lives on its own domain**, so a lower tier cannot truncate a
URL to discover/self-promote into a higher tier — the higher tier's routes simply **do not exist**
on their host (404), not merely "forbidden".

Three **separate registrable domains** (NOT subdomains of one parent). Distinct domain names mean
there is no shared parent to guess and nothing in the URL links the tiers — the strongest form of
the firewall.

Three **separate registrable domains** (NOT subdomains of one parent). Distinct names mean there is
no shared parent to guess and nothing in the URL links the tiers.

**DOMAIN 1 — `datasite.com`** (admin + agents live here)

| URL | Who | What |
| :-- | :-- | :-- |
| `/admin` | Superadmin | Backoffice (IP-locked) |
| `/register` | Public | **Become an agent** — open, no fee |
| `/login` | Agent | Log in |
| `/dashboard` | Agent | Manage sales, wallet, orders |

**DOMAIN 2 — `dataagentshub.com`** (agent's shop + where subagents live)

| URL | Who | What |
| :-- | :-- | :-- |
| `/{agentSlug}` | Customer | Buy bundles from that agent **+ Become a subagent** |
| `/login` | Subagent | Log in |
| `/dashboard` | Subagent | Manage sales, wallet, orders |

**DOMAIN 3 — `databundlestore.com`** (subagent's shop — buy only)

| URL | Who | What |
| :-- | :-- | :-- |
| `/{subagentSlug}` | Customer | Buy bundles — **no "become" anything** |

> Domain names are placeholders until confirmed; they live in `config/surfaces.php` (env-driven).

**Who can climb the ladder** — each "become" step is on a different domain, so you can't chop a URL
to reach the tier above you:

```
Visitor on datasite.com (D1)      → can become AGENT      ✅
Customer on an AGENT shop (D2)    → can become SUBAGENT   ✅
Customer on a SUBAGENT shop (D3)  → can become nothing    ⛔
```

### Routing layout — one file per domain

Dead simple: `web.php` binds each domain to one route file. That file holds exactly the URLs from
that domain's table above. No nested folders. (Split a file later only if it nears the 300-line cap.)

```
config/surfaces.php   # ['admin_agents' => env(...), 'agent_store' => env(...), 'subagent_store' => env(...)]
routes/web.php        # dispatcher only: 3 domains -> 3 files
routes/domain_admin_agents.php    # DOMAIN 1: /admin, /register, /login, /dashboard (agent)
routes/domain_agent_store.php     # DOMAIN 2: /{agentSlug} (+become subagent), subagent /login, /dashboard
routes/domain_subagent_store.php  # DOMAIN 3: /{subagentSlug}
```

```php
// routes/web.php — the ONLY place domains are referenced
Route::domain(config('surfaces.admin_agents'))->middleware('surface:admin_agents')
    ->group(base_path('routes/domain_admin_agents.php'));   // admin.ip allowlist applied inside
Route::domain(config('surfaces.agent_store'))->middleware('surface:agent_store')
    ->group(base_path('routes/domain_agent_store.php'));
Route::domain(config('surfaces.subagent_store'))->middleware('surface:subagent_store')
    ->group(base_path('routes/domain_subagent_store.php'));
// Any unknown Host → rejected.
```

- "Become an agent" exists only in `domain_admin_agents.php`, "become a subagent" only in
  `domain_agent_store.php` — so on the wrong domain those routes simply don't exist (404), not "forbidden".
  That's the truncation firewall.
- `surface:{name}` middleware stamps the active domain (for Inertia layout selection) and rejects an
  authenticated user whose role may not use that domain. Superadmin also IP-allowlisted on D1.

### Recruitment rules (enforced server-side, keyed to link owner — not just domain)

- A **buyer on an Agent's storefront link** may be offered "become a reseller under {Agent}" →
  creates a `subagent` with `agent_id = agent.id`. This flow lives on **D2** (the agent's referral
  domain), so the buyer never sees agent-signup.
- A **buyer on a Subagent's storefront link** gets **no** recruitment CTA — chain stops at subagent.
- Enforced in the registration action + a policy: only `role=agent` can mint subagent invites;
  `subagent` can never create children.

### Deployment (cPanel)

Point all three domains' document roots at the **same** app `public/` folder (main domain + 2
addon/parked domains). Laravel's domain routing does the rest — one deploy, one DB. cPanel limits
we design around: queues run via a cron-hit HTTP endpoint (the Databundleshub
`/internal/run-purchase-queue` pattern), and Inertia SSR is **off** (client-render only). *(A VPS
with Forge/Ploi would lift both limits, if ever available.)*

---

## 2. Upstream Supplier — Databundleshub API

DataSite does **not** fulfill bundles itself. It is **one agent account on Databundleshub** holding
a prepaid float, and it places every order through Databundleshub's public API. Databundleshub's
price to our account = our pricing floor, **`base_cost`**.

### Contract (verified against `../Databundleshub/routes/api.php`)

- **Base URL:** `https://<databundleshub-host>/api` (env `DATABUNDLESHUB_API_URL`).
- **Auth:** header `X-API-Key: <key>` (env `DATABUNDLESHUB_API_KEY`). *(Bearer / `?api_key=` also
  accepted upstream; we standardize on `X-API-Key`.)*
- **Place order:** `POST /create_order` (alias `/developer/purchase`).
  Body: `phoneNumber` (`0XXXXXXXXX`), `capacity` (whole GB), and an **idempotency key** (we send our
  own order reference). Idempotent — re-posting the same key returns the existing result.
- **Poll status:** `GET /developer/purchase-status?request_id={requestId}` (also
  `GET /check_order_status`). **Databundleshub does NOT push callbacks to us — status is poll-only.**
- **Catalog / prices:** `GET /get_pricing` (public) and `GET /developer/data-packages` (keyed) —
  OPTIONAL convenience only (a future "prefill base cost" button). NOT a live dependency and NOT cached.

### Fulfillment vs pricing are decoupled (learned from how DBH treats its own providers)

DBH's own provider configs (`gshare_config`, `vendor_gh_config`, `vtu_networks`) store **only
connection settings** — url, key, timeouts, retries, enabled — and **no prices**; the provider is a
pure fulfillment pipe, and the admin **enters the cost by hand** into pricing. We mirror this:

- **DBH is a fulfillment pipe.** A `dbh_config` table holds connection settings only (`api_url`,
  `api_key`, timeouts, `is_enabled`) — no prices. There is **no price-cache table**.
- **The superadmin sets `base_cost` by hand** in our pricing (like DBH's admin sets `price_per_gb`).
  That is the number agents/subagents mark up.
- The **actual** cost still returns on each real order (`upstream_cost` from `create_order`) and
  reconciles against the admin-set base.

### Order response fields we depend on

`requestId`, `transactionReference`, `orderStatus` (`pending|processing|completed|delivered|failed|rejected`
— the order-row status; a fulfilled order reads **`delivered`**, not `completed`), `processingStatus`
(the request-row status; `completed` on success — this is DBH's own authority for "done"), `price`,
`remainingBalance` (our upstream float after the order), `statusUrl`, `errorCode`, `completedAt`/`failedAt`.
We treat an order as delivered on **any** of: `orderStatus∈{completed,delivered}`, `processingStatus=completed`,
or a `completedAt` stamp; as failed on `orderStatus∈{failed,rejected}`, `processingStatus∈{failed,rejected}`,
or a `failedAt` stamp. (`refunded` is a DBH *internal* admin action, never surfaced on the API — we don't consume it.)

### DataSite-side pieces to build

- `dbh_config` table — connection settings only (`api_url`, `api_key`, timeouts, `is_enabled`),
  mirroring DBH's `gshare_config`. No prices.
- `app/Services/Databundleshub/UpstreamClient.php` — thin HTTP client (Laravel `Http`), one method
  per endpoint, injects `X-API-Key`, maps responses to typed DTOs, redacts secrets in logs.
- `app/Jobs/PollUpstreamOrderStatus.php` — queued, backoff retry, polls `purchase-status` until a
  terminal state, then settles the order.
- `.env` may seed the key/url for local; the live values live in `dbh_config`.

---

## 3. The end-to-end order & money flow

When a **Subagent** sells a bundle to an **End User** (on their D3 storefront):

1. **Validate & price** — resolve the price the subagent pays (Agent-set subagent price) and the
   cascade: `base_cost` (Databundleshub) ≤ `agent_price` ≤ `subagent_price` ≤ `retail`.
2. **Debit subagent wallet** (DataSite DB) at the subagent price. Insufficient funds → reject before
   any upstream call.
3. **Create local `order`** (status `pending`) with our reference as the idempotency key.
4. **Call upstream** `create_order` via `UpstreamClient`; this consumes DataSite's Databundleshub
   float at `base_cost`. Store `requestId`/`transactionReference`.
5. **Dispatch `PollUpstreamOrderStatus`** — poll until terminal.
6. **On `completed`:** mark order delivered; **credit the owning Agent's commission** = `subagent_price −
   agent_cost`; record Superadmin platform profit = `agent_cost − base_cost` (ledger entry). All
   wallet/commission moves are logged (balance history) inside a DB transaction.
7. **On `failed`/`rejected`:** **reverse** the subagent wallet debit and mark the commission
   `reversed`. Nothing is credited upstream-side since the float is only truly spent on success
   (reconcile against `remainingBalance`).

Agent→customer direct sales are the same flow with the Agent as seller (no subagent hop).
Wallet **top-ups** on DataSite (subagent/agent funding their DataSite balance) use **DataSite's own**
payment gateway (Paystack/MoMo) — this is where Databundleshub's payment-init/webhook code may be
reused. This is separate from the upstream float, which the Superadmin funds directly on
Databundleshub.

---

## 4. Pricing model (DECIDED — built in the pricing slice)

We set our OWN selling rates; the Databundleshub API cost is only a guardrail. Per-GB model:

**`tier_prices`** — `pricing_tier_id` (FK) · `network` (mtn/telecel/at) · `min_gb` · `max_gb` ·
`price_per_gb` (our rate) · `is_active`. One row per tier × network × GB range.

- We mark up freely per network / range / tier — fulfillment being upstream does not constrain price.
- The DBH `get_pricing` cost is cached separately and used ONLY as a **floor guardrail**: block
  saving a `price_per_gb` below cost. It never dictates the price.
- `pricing_tiers` (Slice 1) is the tier list; `tier_prices` holds the rates and is built later.

---

## 5. What we explicitly do NOT build

VTU/telecom adapters, GShare client, `FulfillmentDispatchRouter`, provider config screens, provider
webhooks — all owned by Databundleshub. DataSite only ever talks to the Databundleshub API.
