import type { PageLink } from '@/components/common/pagination';

export interface Figures {
    sales: number;
    revenue: number;
    subagentEarned: number;
    subagentPending: number;
    agentEarned: number;
    agentPending: number;
}

export interface ResellerSubagent extends Figures {
    id: number;
    name: string;
    username: string | null;
    phone: string;
    isActive: boolean;
}

export interface ResellerAgent extends Figures {
    id: number;
    name: string;
    username: string | null;
    phone: string;
    isActive: boolean;
    subagentCount: number;
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
    subagentShare: number;
    agentShare: number;
    platformShare: number | null;
    dbhCost: number | null;
    dbhActual: boolean;
    settlement: string;
    issues: string[];
}

export interface SplitFilters {
    agent: string;
    subagent: string;
    settlement: string;
    q: string;
    problems: boolean;
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
