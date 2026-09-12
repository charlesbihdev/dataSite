# DataSite — Engineering Principles (READ FIRST, OBEY STRICTLY)

> **This document is binding.** Every agent (Claude, Gemini, Codex, or human) MUST read this
> file **and the relevant skills** before writing or editing any code in this project. These
> rules are not suggestions. If a change violates a rule here, the change is wrong — fix the
> change, do not bend the rule. If a rule genuinely blocks you, stop and ask the user; do not
> silently work around it.

The base app lives in `datasiteAdmin/` (Laravel 13 + Inertia v3 + React 19 + TypeScript +
Tailwind v4 + shadcn/ui + Fortify). We build the 4-tier DataSite platform on top of it
(Superadmin → Agent → Subagent → End User). See `../DATABUNDLESHUB_EXPLANATION_AND_DIFFERENCES.md`
for the domain model.

---

## 0. Before you touch code — activate skills

Skills live in `.claude/skills/**` and `.agents/skills/**` (same skills, per-LLM folders).
**You MUST activate the matching skill BEFORE editing, not when you get stuck.**

| Working on… | Activate skill |
| :-- | :-- |
| Any React page, form, `<Link>`, `useForm`, layout props, navigation | `inertia-react-development` |
| Any Laravel PHP (controllers, models, migrations, requests, policies, jobs, services) | `laravel-best-practices` |
| Any styling / Tailwind classes / theme tokens | `tailwindcss-development` |
| Calling backend routes/actions from the frontend | `wayfinder-development` |
| Auth: login, registration, 2FA, passkeys, password reset | `fortify-development` |
| Writing or fixing tests | `testing-best-practices` |
| Matching existing conventions in an unfamiliar area | `infer-conventions` |

Do **not** invent APIs from memory. Confirm package versions (`composer show --direct`,
`package.json`) and use `search-docs` for version-specific Laravel/Inertia behavior.

---

## 1. Theming — every colour is a named token, ZERO hardcoded colors

**Single source of truth: `resources/css/app.css` (`@theme` block).** Colours are defined there
once as tokens and referenced everywhere as Tailwind utilities. Blue + white is the starting
palette; adding a colour later (a surface, a status, a chart series) means adding a token in
`app.css` and using it by name — never hardcoding one in a component. Changing the brand must be a
one-line edit in `app.css`.

### The brand tokens (add to the `@theme` block in `app.css`)

```css
@theme {
    /* ── DataSite brand: blue & white — single source of truth ─────────────── */
    --color-brand:        oklch(0.55 0.22 264);      /* primary blue  (~blue-600) */
    --color-brand-hover:  oklch(0.49 0.22 264);      /* hover/active  (~blue-700) */
    --color-brand-fg:     oklch(0.99 0 0);           /* text on blue = white      */
    --color-brand-subtle: oklch(0.55 0.22 264 / 10%);/* tinted fills / rings      */
}

:root {
    --primary: var(--color-brand);        /* shadcn primary = brand blue */
    --primary-foreground: var(--color-brand-fg);
    --ring: var(--color-brand);
    /* white/near-white surfaces are the existing --background/--card tokens */
}
```

### Rules

- **NEVER** write a raw color anywhere: no `#2563eb`, no `rgb(...)`, no inline `style={{color}}`,
  no `bg-blue-600`, no arbitrary values like `bg-[#fff]`. All of these are violations.
- **Brand/UI color = token utility only:** `bg-brand`, `text-brand`, `border-brand`,
  `hover:bg-brand-hover`, `ring-brand`, plus the semantic shadcn tokens `bg-background`,
  `bg-card`, `text-foreground`, `text-muted-foreground`, `border-border`, `bg-sidebar`, etc.
- **No exceptions — status colors are tokens too.** Success / warning / error / info are NOT the
  raw Tailwind palette (`text-emerald-600`, `bg-amber-50`, ...). They are **semantic tokens** defined
  once in `app.css`: `--color-success`, `--color-warning`, `--color-danger` (red already exists as
  the shadcn `--destructive`), `--color-info`. Components then write `text-success`, `bg-warning`,
  etc. — never a raw palette value. Wrap status styling in one reusable component (e.g.
  `<StatusBadge status="failed">`) so each state's look is decided in exactly one place.
- **The palette is not "blue and white forever" — it is "whatever is in `app.css`."** Blue + white
  is just the starting set. When you need another colour (a new surface background, a chart series,
  another status), you ADD a token to `app.css` and use it by name. The rule is never "only two
  colours"; it is "every colour is a named token, defined in one file."
- Dark mode is driven by the `.dark` variant tokens already in `app.css`. Never branch on color
  manually — set the token, let the variant resolve it.
- **Dark mode is DEFERRED, not designed against — build light-first, but stay dark-ready.** For
  speed we ship light-only and do NOT design or QA dark mode now. The one hard requirement: never use
  light-only literals (`bg-white`, `text-black`, `text-gray-500`, `border-gray-200`, `bg-gray-50`).
  Always use the semantic tokens instead — `bg-card`/`bg-background`, `text-foreground`,
  `text-muted-foreground`, `border-border`, `bg-muted`. This keeps the light build dark-ready by
  construction (dark later = flip token values, zero component edits) without spending any effort on
  dark today. The **storefront (D3) is forced light** regardless of the viewer's OS setting.

---

## 2. Styling — Tailwind forever, never write CSS

- **Use Tailwind utility classes for everything.** Do **not** write `.css`/`.scss` files, do
  **not** add `<style>` blocks, do **not** use inline `style={{}}` (except a genuinely dynamic
  value that cannot be a class, e.g. a computed `width: ${pct}%`).
- Compose class strings with the project's `cn()` helper (`@/lib/utils`). No string concatenation
  for conditional classes.
- Reuse shadcn/ui primitives in `resources/js/components/ui/**`. Add a new primitive only if one
  genuinely doesn't exist; match the existing shadcn structure and use `cva` for variants.
- Consistent scale: use the theme's `--radius`, spacing, and font tokens. No magic pixel values
  where a Tailwind scale step exists.

### Visual language — quiet, bordered, spacious (the house style)

Match the calm, professional look of the reference dashboards (biizz). The goal is a UI that feels
uncluttered and confident, not busy.

- **Separate with borders + whitespace, NOT shadows.** White cards on a near-white page, divided by
  a thin `border border-border` (grey). Avoid drop-shadows, heavy elevation, gradients, and
  boxes-nested-in-boxes. A subtle shadow is allowed only for true overlays (dropdowns, dialogs).
- **Give things room.** Generous padding inside cards (`p-5`/`p-6`), generous gaps between them,
  few elements per row. When in doubt, add space and remove elements — never cram.
- **Let grey do the quiet work.** Micro-labels are small, uppercase, muted
  (`text-xs font-semibold uppercase tracking-wide text-muted-foreground`); icons sit in soft grey
  chips (`bg-muted rounded-lg`); dividers are `border-border`.
- **Colour is rare and earned.** Brand blue appears on the ONE primary action and the active nav
  item — not on every button or heading. A status colour is a single small accent, never a fill
  that dominates a card. Most of the screen is ink, grey, and white.
- **The data is the hero.** Key numbers are large, bold, tabular (`text-2xl font-bold tabular-nums`);
  everything around them stays calm and secondary.
- **Rounded, consistent corners** via the `--radius` tokens (`rounded-lg`/`rounded-xl`). No mixing
  of sharp and round in the same view.

---

## 3. File size — max 300 lines per file (hard cap, frontend especially)

- **No file exceeds 300 lines.** If a component or controller grows past it, that is the signal
  to split: extract sub-components, hooks (`resources/js/hooks/`), helpers (`resources/js/lib/`),
  or (backend) service classes and form requests.
- A page component should orchestrate, not implement. Heavy tables, forms, cards, charts, and
  dialogs are their own components — the page composes them.
- Keep components small and single-purpose. A component that does two things is two components.

---

## 4. Modularity, component-based, reusable — organized like `biizz`

We mirror the `biizz` project's organization (its **layout, sidebar style, and structure** —
**NOT its amber colors; ours is blue & white**).

### Frontend component layout (`resources/js/components/`)

```
components/
  ui/              # shadcn primitives ONLY (button, dialog, sidebar, ...)
  common/          # cross-cutting reusable pieces used by 2+ features
                   #   e.g. StatTile, StatusBadge, DataTable, PageHeader, EmptyState
  <feature>/       # feature-scoped components, one folder per domain area
    agent/         #   Agent-portal components
    subagent/      #   Subagent-portal components
    superadmin/    #   Superadmin-portal components
    storefront/    #   End-user storefront components
    orders/  pricing/  wallet/  commissions/  ...
```

**The reuse rule (from biizz):** a component starts in its feature folder. **The moment a second
feature needs it, promote it to `components/common/`.** Never copy-paste a component into two
folders. Search `common/` and `ui/` for something reusable *before* writing anything new.

### Pages & layouts

- Pages: `resources/js/pages/<Area>/<Page>.tsx` (e.g. `pages/Agent/Subagents.tsx`).
- Layouts: `resources/js/layouts/**`. Reuse the app sidebar layout
  (`layouts/app/app-sidebar-layout.tsx`) — do not build a new shell per portal. Per-role nav is
  data passed into the shared `AppSidebar`, not a forked sidebar.
- Sidebar: keep the shadcn `Sidebar` (`collapsible="icon" variant="inset"`) pattern with
  `SidebarHeader` (logo) / `SidebarContent` (role-aware nav) / `SidebarFooter` (user menu),
  exactly like `biizz` and the current `components/app-sidebar.tsx`.

### Backend modularity

- Thin controllers. Business logic lives in `app/Services/**` (e.g. `Pricing/`, `Fulfillment/`,
  `Commission/`, `Wallet/`). Validation lives in Form Requests. Authorization lives in Policies.
- One class, one responsibility. Cascading-order/commission logic is a service, not a controller
  method.

---

## 5. Do it the Laravel way & the Inertia way (NOT "regular React")

- **Server drives routing.** Use `Inertia::render()` from controllers; pages live in
  `resources/js/pages`. No client-side router, no `react-router`, no client-side data fetching for
  page data — props come from the controller.
- **Forms:** use Inertia's `useForm` / `<Form>` for submissions and validation errors. Do **not**
  hand-roll `fetch`/`axios` for form posts. Server-side validation via Form Requests is the source
  of truth; surface errors through Inertia.
- **URLs:** never hardcode paths. Use **Wayfinder** typed helpers imported from `@/actions/*`
  (controllers) and `@/routes/*` (named routes). Backend: named routes + `route()`.
- **Create files the Laravel way:** `php artisan make:*` (with `--no-interaction`). Migrations for
  every schema change; factories + seeders for every model.
- **Auth is Fortify** (already installed — passkeys/2FA scaffolded). Extend Fortify; do not build a
  parallel auth system. (Note the DataSite hierarchy: only `agent` may invite `subagent`;
  `subagent` cannot recruit — enforce in registration action + policy.)
- **Every behavior/logic change ships with a test** (PHPUnit here — `php artisan make:test`).
  Copy/layout-only changes don't. Run the narrowest passing test set.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP.

---

## 6. Databundleshub is our UPSTREAM SUPPLIER (API), not a code donor

**Fulfillment model (DECIDED):** DataSite does **NOT** connect to any VTU/telecom provider
directly. Databundleshub is the wholesaler: DataSite holds **one Databundleshub API key** and
places bundle orders through Databundleshub's public API. Databundleshub does the actual VTU/GShare
delivery. See `ARCHITECTURE.md` §"Upstream Supplier" for the full contract.

**Therefore DO NOT port** — these stay entirely on Databundleshub's side and are out of scope for
DataSite: `app/Services/GShare/*`, `FulfillmentDispatchRouter`, VTU adapters, provider config,
GShare/provider webhooks. Do not recreate them.

**Build in DataSite instead:**
- A single upstream client `app/Services/Databundleshub/UpstreamClient.php` (auth via `X-API-Key`,
  `create_order`, `purchase-status`, `get_pricing`) + a **status-poller job** (Databundleshub is
  **poll-based — it does NOT push callbacks to us**).
- Our own hierarchical model: `users.agent_id` (set on subagent rows → their owning agent),
  cascading tiered pricing (base = Databundleshub
  price → agent → subagent → retail), commission ledger, hierarchical wallets, role guards.

**Reuse only these small utilities** (adapt, don't screenshot the UI — Databundleshub's
Blade/Bootstrap/jQuery UI is off-limits; ours is new, blue & white, biizz-inspired):
- DataSite still runs its **own** payment gateway for **wallet funding** (users/subagents topping
  up their DataSite wallet). Here you MAY port Databundleshub's Paystack/MoMo/Moolre init + webhook
  handlers (`PaymentInitController`, `CallbackController`, `app/Http/Controllers/Webhooks/*`).
- `SensitiveDataRedactor`, Ghanaian MSISDN/phone sanitization helpers.

---

## 7. Definition of done (self-check before you finish)

- [ ] Activated the relevant skill(s) before editing.
- [ ] No raw colors anywhere; only brand/semantic **tokens** used; theme still edits from `app.css` alone.
- [ ] No `.css`/`.scss`/`<style>`/ad-hoc inline styles; Tailwind utilities + `cn()` only.
- [ ] Every touched file ≤ 300 lines.
- [ ] Reusable pieces live in `common/` or `ui/`; nothing copy-pasted across features.
- [ ] Routing/props via Inertia; URLs via Wayfinder; forms via `useForm`; files via `artisan make:`.
- [ ] Tests added/updated and passing; `pint` run on changed PHP.
