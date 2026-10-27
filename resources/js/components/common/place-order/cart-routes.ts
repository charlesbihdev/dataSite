type RouteUrl = { url: () => string };

/**
 * The portal's cart endpoints (Wayfinder route modules) — `@/routes/agent/cart` or
 * `@/routes/subagent/cart` — so one Place-Order UI serves both portals.
 */
export interface CartRoutes {
    store: RouteUrl;
    bulk: RouteUrl;
    upload: RouteUrl;
    template: RouteUrl;
    checkout: RouteUrl;
    destroy: { url: (id: string) => string };
}
