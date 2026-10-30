export type OrderSegment = 'agent' | 'regular';

export interface Filters {
    status: string;
    network: string;
    seller: string;
    source: string;
    payment: string;
    q: string;
    range: string;
    from: string | null;
    to: string | null;
}

export const STATUSES = ['all', 'pending', 'verifying', 'processing', 'completed', 'failed', 'refunded'];
export const NETWORKS = ['all', 'mtn', 'telecel', 'at'];
export const SELLERS = ['all', 'agent', 'subagent'];
export const SOURCES = ['all', 'portal', 'api'];
export const PAYMENTS = ['all', 'paid', 'awaiting', 'failed', 'expired'];

/** Payment states the gateway Verify can still resolve (mirrors Order::VERIFIABLE_PAYMENTS). */
export const VERIFIABLE_PAYMENTS = ['awaiting', 'failed', 'expired'];
