<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Notifications\AgentNotificationFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Marks admin notifications read for the signed-in agent (banner dismiss, opening one, mark all).
 * The feed itself is a shared prop, so every action just returns to the same page.
 */
class NotificationsController extends Controller
{
    public function read(Request $request, Announcement $announcement, AgentNotificationFeed $feed): RedirectResponse
    {
        $feed->markRead($request->user('agent'), $announcement);

        return back();
    }

    public function readAll(Request $request, AgentNotificationFeed $feed): RedirectResponse
    {
        $feed->markAllRead($request->user('agent'));

        return back();
    }
}
