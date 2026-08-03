<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationProfile;
use App\Models\IntegrationRun;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RunController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IntegrationRun::class);
        $filters = $request->only(['q', 'estado', 'profile_id', 'date_from', 'date_to']);

        $runs = IntegrationRun::with(['profile', 'user'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->whereHas('profile', fn ($q) => $q->where('nombre', 'like', "%{$v}%")))
            ->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filters['profile_id'] ?? null, fn ($q, $v) => $q->where('profile_id', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($run) => [
                'id' => $run->id,
                'profile' => $run->profile?->nombre,
                'profile_codigo' => $run->profile?->codigo,
                'estado' => $run->estado?->value,
                'estado_label' => $run->estado?->label(),
                'estado_color' => $run->estado?->color(),
                'usuario' => $run->user?->name,
                'total' => $run->total_registros,
                'exitosos' => $run->exitosos,
                'fallidos' => $run->fallidos,
                'pendientes' => $run->pendientes,
                'duracion' => $run->duracion_segundos,
                'created_at' => $run->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Runs/Index', [
            'runs' => $runs,
            'filters' => $filters,
            'profiles' => IntegrationProfile::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'statuses' => collect(IntegrationRunStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(Request $request, IntegrationAuditService $audit)
    {
        $this->authorize('create', IntegrationRun::class);

        $data = $request->validate([
            'profile_id' => 'required|exists:integration_profiles,id',
            'profile_version_id' => 'nullable|exists:integration_profile_versions,id',
            'nota' => 'nullable|string|max:500',
        ]);

        $profile = IntegrationProfile::findOrFail($data['profile_id']);

        $run = IntegrationRun::create([
            'profile_id' => $profile->id,
            'profile_version_id' => $data['profile_version_id'] ?? $profile->current_version_id,
            'estado' => IntegrationRunStatus::PENDING,
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $audit->log('run_created', 'IntegrationRun', $run->id, "Ejecución iniciada para perfil {$profile->nombre}");

        dispatch(new \App\Jobs\Integrations\ProcessIntegrationRun($run));

        return redirect()->route('integrations.runs.show', $run)
            ->with('success', 'Ejecución iniciada correctamente.');
    }

    public function show(IntegrationRun $run)
    {
        $this->authorize('view', $run);

        $run->load(['profile', 'profileVersion', 'user', 'records' => function ($q) {
            $q->latest()->limit(200);
        }]);

        return Inertia::render('Integrations/Runs/Show', [
            'run' => [
                'id' => $run->id,
                'profile' => $run->profile?->nombre,
                'profile_codigo' => $run->profile?->codigo,
                'version' => $run->profileVersion?->version,
                'estado' => $run->estado?->value,
                'estado_label' => $run->estado?->label(),
                'estado_color' => $run->estado?->color(),
                'usuario' => $run->user?->name,
                'total_registros' => $run->total_registros,
                'procesados' => $run->procesados,
                'exitosos' => $run->exitosos,
                'fallidos' => $run->fallidos,
                'pendientes' => $run->pendientes,
                'archivo_generado' => $run->archivo_generado,
                'duracion_segundos' => $run->duracion_segundos,
                'metricas' => $run->metricas,
                'errores' => $run->errores,
                'nota' => $run->nota,
                'started_at' => $run->started_at?->format('Y-m-d H:i:s'),
                'finished_at' => $run->finished_at?->format('Y-m-d H:i:s'),
                'created_at' => $run->created_at?->format('Y-m-d H:i'),
            ],
            'records' => $run->records->map(fn ($r) => [
                'id' => $r->id,
                'source_identifier' => $r->source_identifier,
                'estado' => $r->estado?->value,
                'estado_label' => $r->estado?->label(),
                'errores' => $r->errores,
                'advertencias' => $r->advertencias,
                'processed_at' => $r->processed_at?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    public function cancel(Request $request, IntegrationRun $run, IntegrationAuditService $audit)
    {
        $this->authorize('cancel', $run);

        if (!in_array($run->estado?->value, ['pending', 'preparing', 'processing'])) {
            return back()->with('error', 'Solo se pueden cancelar ejecuciones en estado pendiente o en proceso.');
        }

        $run->update([
            'estado' => IntegrationRunStatus::CANCELLED,
            'finished_at' => now(),
        ]);

        $audit->log('run_cancelled', 'IntegrationRun', $run->id, 'Ejecución cancelada por usuario');

        return back()->with('success', 'Ejecución cancelada.');
    }

    public function reprocess(Request $request, IntegrationRun $run, IntegrationAuditService $audit)
    {
        $this->authorize('reprocess', $run);

        if ($run->estado?->value !== 'failed' && $run->estado?->value !== 'partially_completed') {
            return back()->with('error', 'Solo se pueden reprocesar ejecuciones fallidas o parcialmente completadas.');
        }

        $newRun = IntegrationRun::create([
            'profile_id' => $run->profile_id,
            'profile_version_id' => $run->profile_version_id,
            'estado' => IntegrationRunStatus::PENDING,
            'user_id' => $request->user()->id,
            'nota' => 'Reproceso de ejecución #' . $run->id,
            'created_by' => $request->user()->id,
        ]);

        $audit->log('run_reprocess', 'IntegrationRun', $newRun->id, "Reproceso iniciado desde ejecución #{$run->id}");

        dispatch(new \App\Jobs\Integrations\ProcessIntegrationRun($newRun));

        return redirect()->route('integrations.runs.show', $newRun)
            ->with('success', 'Reproceso iniciado.');
    }
}
