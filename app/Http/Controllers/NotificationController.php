<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user
            ->notifications()
            ->latest()
            ->paginate(15);

        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'timezone' => (string) config('attendance.timezone', 'Asia/Jakarta'),
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        $targetUrl = $notification->data['url'] ?? null;

        if (
            $request->boolean('redirect')
            && is_string($targetUrl)
            && str_starts_with($targetUrl, '/')
            && ! str_starts_with($targetUrl, '//')
        ) {
            return redirect()->to($targetUrl);
        }

        return redirect()
            ->back(fallback: route('notifications.index'))
            ->with('status', 'Notifikasi ditandai telah dibaca.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return redirect()
            ->route('notifications.index')
            ->with('status', 'Semua notifikasi telah ditandai dibaca.');
    }
}
