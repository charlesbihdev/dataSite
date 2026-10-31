import type { PageLink } from '@/components/common/pagination';

export interface Figures {
    sales: number;
    revenue: number;
    subagentEarned: number;
    subagentPending: number;
    agentEarned: number;
    agentPending: number;
}

/** A person's own money. Withdrawn, requested and available are always all-time. */
export interface Money {
    totalEarned: number;
    withdrawn: number;
    requested: number;
    available: number;
}

export interface ResellerSubagent extends Figures, Money {
    id: number;
    name: string;
    username: string | null;
    phone: string;
    isActive: boolean;
}

/** Figures here are the subagents' group totals; own* and Money are the agent's own. */
export interface ResellerAgent extends Figures, Money {
    id: number;
    name: string;
    username: string | null;
    phone: string;
    isActive: boolean;
    subagentCount: number;
    ownSales: number;
    ownRevenue: number;
    ownProfit: number;
    ownPending: number;
    subagents: ResellerSubagent[];
}

export interface Split {
    id: number;
    reference: string;
    date: string | null;
    subagent: string;
    agent: string;
    orderStatus: string;
    customerPaid: number;
    sellerCost: number;
    subagentShare: number;
    agentShare: number;
    platformShare: number | null;
    dbhCost: number | null;
    dbhActual: boolean;
    settlement: string;
    issues: string[];
}

export interface SplitFilters {
    scope: 'subagents' | 'own';
    agent: string;
    subagent: string;
    settlement: string;
    q: string;
    problems: boolean;
    profit_only: boolean;
    range: string;
    from: string | null;
    to: string | null;
}

export interface AgentOption {
    id: number;
    name: string;
    subagents: { id: number; name: string }[];
}

export interface Paginated<T> {
    data: T[];
    links: PageLink[];
}
