export type AccountType = 'agents' | 'subagents';

export interface Account {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    username: string | null;
    detail: string;
    wallet: number;
    earnings: number;
    ordersCount: number;
    ordersTotal: number;
    lastActivity: string | null;
    createdAt: string | null;
    status: string;
    canDelete: boolean;
}

export interface Named {
    id: number;
    name: string;
}

export interface AccountAnalytics {
    avgOrders: number;
    avgRevenue: number;
    activeRate: number;
    balance: { positive: number; zero: number; negative: number };
    topByOrders: { name: string; ordersCount: number; ordersTotal: number }[];
}
