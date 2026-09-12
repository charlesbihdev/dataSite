export type AccountType = "agents" | "subagents";

export interface ApiKeyItem {
    id: number;
    name: string;
    prefix: string;
    isActive: boolean;
    lastUsedAt: string;
    createdAt: string;
}

export interface RawApiKeyFlash {
    rawKey: string;
    name: string;
    prefix: string;
    accountName: string;
    accountId: number;
    accountType: AccountType;
}

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
    pricingTierId: number | null;
    pricingTierName: string | null;
    apiKeys?: ApiKeyItem[];
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
