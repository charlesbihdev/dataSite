/**
 * Shared formatters. GHS money + short dates, so every admin screen renders numbers the same way.
 */
const cedisFormatter = new Intl.NumberFormat('en-GH', {
    style: 'currency',
    currency: 'GHS',
});

export function cedis(value: number | string | null | undefined): string {
    const n = typeof value === 'string' ? parseFloat(value) : (value ?? 0);
    return cedisFormatter.format(Number.isFinite(n) ? n : 0);
}

export function gb(value: number | string): string {
    const n = typeof value === 'string' ? parseFloat(value) : value;
    return `${Number.isFinite(n) ? n : 0}GB`;
}
