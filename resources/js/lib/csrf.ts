/**
 * Read Laravel's XSRF-TOKEN cookie so a plain `fetch` can pass the CSRF check on the `web` middleware.
 * Used by the storefront assistant, which talks to a JSON endpoint outside the Inertia page flow.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : "";
}
