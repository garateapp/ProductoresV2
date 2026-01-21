<?php

namespace App\Http\Controllers;

use App\Exports\NotificationLogExport;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class NotificationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'recipient' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
            'n_proceso' => ['nullable'],
            'proceso_id' => ['nullable'],
            'producer_name' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = $this->buildFilteredQuery($filters);

        $logs = $query->paginate(20)->withQueryString();

        $logsTransformed = $logs->through(function (NotificationLog $log) {
            $context = $log->context ?? [];

            return [
                'id' => $log->id,
                'type' => $log->type,
                'recipient' => $log->recipient,
                'subject' => $log->subject,
                'status' => $log->status,
                'message' => $log->message,
                'context' => $context,
                'channel' => $context['channel'] ?? null,
                'n_proceso' => $context['n_proceso'] ?? null,
                'proceso_id' => $context['proceso_id'] ?? null,
                'numero_g_recepcion' => $context['numero_g_recepcion'] ?? null,
                'usuario_solicita' => $context['usuario_solicita'] ?? null,
                'c_productor' => $context['c_productor'] ?? null,
                'producer_name' => $context['producer_name'] ?? null,
                'created_at' => optional($log->created_at)->format('Y-m-d H:i:s'),
            ];
        });

        return Inertia::render('Admin/NotificationLogs/Index', [
            'logs' => $logsTransformed,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'recipient' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
            'n_proceso' => ['nullable'],
            'proceso_id' => ['nullable'],
            'producer_name' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = $this->buildFilteredQuery($filters)->get();

        return Excel::download(
            new NotificationLogExport($logs, $filters['type'] ?? null),
            'notification-logs.xlsx'
        );
    }

    protected function buildFilteredQuery(array $filters)
    {
        $query = NotificationLog::query()->latest();

        $query->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type));
        $query->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $query->when($filters['recipient'] ?? null, fn ($q, $recipient) => $q->where('recipient', 'like', "%{$recipient}%"));
        $query->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('context->channel', $channel));
        $query->when($filters['n_proceso'] ?? null, fn ($q, $value) => $q->where('context->n_proceso', $value));
        $query->when($filters['proceso_id'] ?? null, fn ($q, $value) => $q->where('context->proceso_id', $value));
        $query->when($filters['producer_name'] ?? null, fn ($q, $name) => $q->where('context->producer_name', 'like', "%{$name}%"));
        $query->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from));
        $query->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('recipient', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        });

        return $query;
    }
}
