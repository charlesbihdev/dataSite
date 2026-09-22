import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Resolve a Wayfinder URL to a full, scheme-qualified absolute URL for display. Cross-domain routes
 * come back protocol-relative (`//host/path`); relative paths come back as `/path`. Both resolve
 * against the current page so a store link reads as `https://host/path`, never `//host/path`.
 */
export function displayUrl(url: string): string {
    if (typeof window === 'undefined') {
        return url;
    }

    try {
        return new URL(url, window.location.href).toString();
    } catch {
        return url;
    }
}
