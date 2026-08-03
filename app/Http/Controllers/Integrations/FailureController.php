<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Enums\IntegrationRunRecordStatus;
use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\IntegrationRunRecord;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FailureController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'profile_id', 'date_from', 'date_to']);

        $records = IntegrationRunRecord::with(['run.profile'])
            ->whereIn('estado', ['failed', 'pending_mapping'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('source_identifier', 'like', "%{$v}%")
                    ->where('errores', 'like', "%{$v}%");
            }))
            ->when($filters['profile_id'] ?? null, fn ($q, $v) => $q->whereHas('run', fn ($q) => $q->where('profile_id', $v)))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'run_id' => $r->run_id,
                'source_identifier' => $r->source_identifier,
                'perfil' => $r->run->profile?->nombre,
                'estado' => $r->estado?->value,
                'estado_label' => $r->estado?->label(),
                'errores' => $r->errores,
                'intentos' => $r->intentos,
                'created_at' => $r->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Failures/Index', [
            'records' => $records,
            'filters' => $filters,
            'profiles' => \App\Models\IntegrationProfile::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function show(IntegrationRunRecord $record)
    {
        $record->load(['run.profile', 'rulesTrace', 'mappingsTrace']);

        return Inertia::render('Integrations/Failures/Show', [
            'record' => [
                'id' => $record->id,
                'run_id' => $record->run_id,
                'source_identifier' => $record->source_identifier,
                'perfil' => $record->run->profile?->nombre,
                'estado' => $record->estado?->value,
                'estado_label' => $record->estado?->label(),
                'input_original' => $record->input_original,
                'input_normalizado' => $record->input_normalizado,
                'errores' => $record->errores,
                'advertencias' => $record->advertencias,
                'intentos' => $record->intentos,
                'created_at' => $record->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function reprocess(Request $request, IntegrationRunRecord $record, IntegrationAuditService $audit)
    {
        if ($record->estado?->value !== 'failed') {
            return back()->with('error', 'Solo se pueden reprocesar registros fallidos.');
        }

        $record->update([
            'estado' => IntegrationRunRecordStatus::PENDING,
            'intentos' => $record->intentos + 1,
        ]);

        $audit->log('failure_reprocess', 'IntegrationRunRecord', $record->id,
            "Registro #{$record->id} marcado para reproceso");

        return back()->with('success', 'Registro marcado para reproceso.');
    }
}
