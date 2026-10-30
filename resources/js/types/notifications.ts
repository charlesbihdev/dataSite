export interface AgentNotification {
    id: number;
    title: string;
    body: string;
    createdAt: string | null;
    read: boolean;
}

/** Shared `notifications` prop — null outside the agent portal. */
export interface NotificationFeed {
    unread: number;
    items: AgentNotification[];
}
