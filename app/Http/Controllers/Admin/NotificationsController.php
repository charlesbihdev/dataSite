<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Agent;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → all-agents notifications: compose, see how many agents read each one, delete a mistake.
 */
class NotificationsController extends Controller
{
    public function index(): Response
    {
        $sent = Announcement::query()
            ->withCount('reads')
            ->latest('id')
            ->paginate(20)
            ->through(fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'readCount' => (int) $a->getAttribute('reads_count'),
                'createdAt' => $a->created_at?->format('M j, Y g:i A'),
            ]);

        return Inertia::render('admin/notifications', [
            'sent' => $sent,
            'agentCount' => Agent::query()->count(),
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        Announcement::query()->create([
            ...$request->validated(),
            'admin_id' => $request->user('admin')?->getKey(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification sent to all agents.']);

        return back();
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notification deleted.']);

        return back();
    }
}
