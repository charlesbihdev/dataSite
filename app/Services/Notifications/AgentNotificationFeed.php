<?php

namespace App\Services\Notifications;

use App\Models\Agent;
use App\Models\Announcement;
use App\Models\AnnouncementRead;

/**
 * An agent's view of admin notifications: unread count for the bell badge, the latest few for its
 * dropdown (the newest unread doubles as the dashboard banner), and marking them read.
 */
class AgentNotificationFeed
{
    public const LIST_SIZE = 10;

    /**
     * @return array{unread: int, items: array<int, array{id: int, title: string, body: string, createdAt: string|null, read: bool}>}
     */
    public function for(Agent $agent): array
    {
        $readByAgent = fn ($q) => $q->where('agent_id', $agent->getKey());

        $items = Announcement::query()
            ->latest('id')
            ->limit(self::LIST_SIZE)
            ->withExists(['reads as is_read' => $readByAgent])
            ->get()
            ->map(fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'createdAt' => $a->created_at?->toIso8601String(),
                'read' => (bool) $a->getAttribute('is_read'),
            ])
            ->values()
            ->all();

        return [
            'unread' => Announcement::query()->whereDoesntHave('reads', $readByAgent)->count(),
            'items' => $items,
        ];
    }

    public function markRead(Agent $agent, Announcement $announcement): void
    {
        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->getKey(), 'agent_id' => $agent->getKey()],
            ['read_at' => now()],
        );
    }

    public function markAllRead(Agent $agent): void
    {
        $now = now();

        $rows = Announcement::query()
            ->whereDoesntHave('reads', fn ($q) => $q->where('agent_id', $agent->getKey()))
            ->pluck('id')
            ->map(fn (int $id): array => ['announcement_id' => $id, 'agent_id' => $agent->getKey(), 'read_at' => $now])
            ->all();

        AnnouncementRead::query()->insertOrIgnore($rows);
    }
}
