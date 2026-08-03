<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'evento', 'entidad_tipo', 'date_from', 'date_to']);

        $logs = IntegrationAuditLog::with('user')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('entidad_nombre', 'like', "%{$v}%")
                    ->orWhere('evento', 'like', "%{$v}%")
                    ->orWhere('motivo', 'like', "%{$v}%");
            }))
            ->when($filters['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($filters['entidad_tipo'] ?? null, fn ($q, $v) => $q->where('entidad_tipo', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id,
                'evento' => $log->evento,
                'entidad_tipo' => $log->entidad_tipo,
                'entidad_nombre' => $log->entidad_nombre,
                'usuario' => $log->user?->name,
                'motivo' => $log->motivo,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'eventos' => IntegrationAuditLog::select('evento')->distinct()->pluck('evento')->values(),
            'entidad_tipos' => IntegrationAuditLog::select('entidad_tipo')->distinct()->pluck('entidad_tipo')->values(),
        ]);
    }

    public function show(IntegrationAuditLog $auditLog)
    {
        $auditLog->load('user');

        return Inertia::render('Integrations/Audit/Show', [
            'log' => [
                'id' => $auditLog->id,
                'evento' => $auditLog->evento,
                'entidad_tipo' => $auditLog->entidad_tipo,
                'entidad_id' => $auditLog->entidad_id,
                'entidad_nombre' => $auditLog->entidad_nombre,
                'usuario' => $auditLog->user?->name,
                'valores_previos' => $auditLog->valores_previos,
                'valores_nuevos' => $auditLog->valores_nuevos,
                'motivo' => $auditLog->motivo,
                'ip_address' => $auditLog->ip_address,
                'created_at' => $auditLog->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }
}
