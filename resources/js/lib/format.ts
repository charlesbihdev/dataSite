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

/** "Just now", "5 min ago", "3 hours ago", "Yesterday", "Monday", then "12 Oct" (year added when not this year). */
export function friendlyDate(iso: string | null | undefined, now: Date = new Date()): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const minutes = Math.floor((now.getTime() - date.getTime()) / 60000);
    const startOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const daysAgo = Math.round((startOfDay(now) - startOfDay(date)) / 86400000);

    if (minutes < 1) {
        return 'Just now';
    }
    if (minutes < 60) {
        return `${minutes} min ago`;
    }
    if (daysAgo === 0) {
        const hours = Math.floor(minutes / 60);
        return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
    }
    if (daysAgo === 1) {
        return 'Yesterday';
    }
    if (daysAgo < 7) {
        return date.toLocaleDateString('en-GB', { weekday: 'long' });
    }

    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}),
    });
}

export function gb(value: number | string): string {
    const n = typeof value === 'string' ? parseFloat(value) : value;
    return `${Number.isFinite(n) ? n : 0}GB`;
}
