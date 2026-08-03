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
                $data = $notification->data;
                $kind = $data['kind'] ?? null;

                if ($kind === 'material_request_created') {
                    return [
                        'id' => $notification->id,
                        'type' => 'Solicitud',
                        'label' => 'Solicitud de materiales',
                        'message' => ($data['creator_name'] ?? 'Alguien') . ' creó ' . ($data['codigo'] ?? 'una solicitud'),
                        'request_id' => $data['material_request_id'] ?? null,
                        'codigo' => $data['codigo'] ?? null,
                        'origin' => $data['origin']['nombre'] ?? null,
                        'destination' => $data['destination']['nombre'] ?? null,
                        'kind' => $kind,
                        'read_at' => optional($notification->read_at)->toDateTimeString(),
                        'created_at' => optional($notification->created_at)->toDateTimeString(),
                    ];
                }

                if ($kind === 'return_created') {
                    return [
                        'id' => $notification->id,
                        'type' => 'Devolución',
                        'label' => 'Devolución de materiales',
                        'message' => ($data['creator_name'] ?? 'Alguien') . ' creó ' . ($data['codigo'] ?? 'una devolución'),
                        'request_id' => $data['return_id'] ?? null,
                        'codigo' => $data['codigo'] ?? null,
                        'origin' => $data['origin']['nombre'] ?? null,
                        'destination' => $data['destination']['nombre'] ?? null,
                        'kind' => $kind,
                        'read_at' => optional($notification->read_at)->toDateTimeString(),
                        'created_at' => optional($notification->created_at)->toDateTimeString(),
                    ];
                }

                if ($kind === 'inventory_transfer_dispatched') {
                    return [
                        'id' => $notification->id,
                        'type' => 'Traspaso',
                        'label' => 'Traspaso de inventario',
                        'message' => 'Folio ' . ($data['folio'] ?? '') . ' despachado',
                        'movement_id' => $data['movement_id'] ?? null,
                        'folio' => $data['folio'] ?? null,
                        'origin' => $data['origin']['nombre'] ?? null,
                        'destination' => $data['destination']['nombre'] ?? null,
                        'kind' => $kind,
                        'read_at' => optional($notification->read_at)->toDateTimeString(),
                        'created_at' => optional($notification->created_at)->toDateTimeString(),
                    ];
                }

                if ($kind === 'inventory_transfer_return_pending') {
                    return [
                        'id' => $notification->id,
                        'type' => 'Devolución pendiente',
                        'label' => 'Devolución de inventario pendiente',
                        'message' => 'Folio ' . ($data['folio'] ?? '') . ' pendiente de devolución',
                        'movement_id' => $data['movement_id'] ?? null,
                        'folio' => $data['folio'] ?? null,
                        'origin' => $data['origin']['nombre'] ?? null,
                        'destination' => $data['destination']['nombre'] ?? null,
                        'kind' => $kind,
                        'read_at' => optional($notification->read_at)->toDateTimeString(),
                        'created_at' => optional($notification->created_at)->toDateTimeString(),
                    ];
                }

                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? $notification->type,
                    'label' => $data['label'] ?? null,
                    'file' => $data['file'] ?? null,
                    'message' => $data['message'] ?? null,
                    'kind' => $kind,
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
