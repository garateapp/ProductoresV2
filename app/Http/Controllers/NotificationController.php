<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user
            ? $user->notifications()->latest()->paginate(20)->through(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? $notification->type,
                    'label' => $notification->data['label'] ?? null,
                    'file' => $notification->data['file'] ?? null,
                    'message' => $notification->data['message'] ?? null,
                    'read_at' => optional($notification->read_at)->toDateTimeString(),
                    'created_at' => optional($notification->created_at)->toDateTimeString(),
                ];
            })
            : null;

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $user = $request->user();
        $target = $user?->notifications()->findOrFail($notification);
        $target?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $user?->unreadNotifications->markAsRead();

        return back();
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()?->unreadNotifications()->count() ?? 0;

        return response()->json(['count' => $count]);
    }
}
