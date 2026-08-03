<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationRun;
use App\Models\IntegrationPendingMapping;
use App\Models\IntegrationAuditLog;
use App\Models\IntegrationExport;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $profilesActivos = IntegrationProfile::where('activo', true)->count();
        $ejecucionesHoy = IntegrationRun::whereDate('created_at', today())->count();
        $registrosProcesados = IntegrationRun::whereDate('created_at', today())->sum('procesados');
        $registrosExitosos = IntegrationRun::whereDate('created_at', today())->sum('exitosos');
        $pendientes = IntegrationPendingMapping::whereNull('resolved_at')->count();
        $registrosFallidos = IntegrationRun::whereDate('created_at', today())->sum('fallidos');
        $exportacionesHoy = IntegrationExport::whereDate('created_at', today())->count();

        $duracionPromedio = IntegrationRun::whereDate('created_at', today())
            ->whereNotNull('duracion_segundos')
            ->avg('duracion_segundos');

        $ultimasEjecuciones = IntegrationRun::with(['profile', 'user'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($run) => [
                'id' => $run->id,
                'profile' => $run->profile?->nombre,
                'profile_codigo' => $run->profile?->codigo,
                'estado' => $run->estado?->value,
                'estado_label' => $run->estado?->label(),
                'usuario' => $run->user?->name,
                'total' => $run->total_registros,
                'exitosos' => $run->exitosos,
                'fallidos' => $run->fallidos,
                'created_at' => $run->created_at?->format('Y-m-d H:i'),
                'duracion' => $run->duracion_segundos,
            ]);

        $ultimosErrores = IntegrationAuditLog::with('user')
            ->where('evento', 'like', '%error%')
            ->orWhere('evento', 'like', '%failed%')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'evento' => $log->evento,
                'entidad_nombre' => $log->entidad_nombre,
                'usuario' => $log->user?->name,
                'created_at' => $log->created_at?->format('Y-m-d H:i'),
            ]);

        $proximosVencer = \App\Models\IntegrationMappingSetVersion::where('estado', 'publicado')
            ->whereNotNull('fecha_fin_vigencia')
            ->where('fecha_fin_vigencia', '>=', now())
            ->where('fecha_fin_vigencia', '<=', now()->addDays(30))
            ->with('mappingSet')
            ->limit(5)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'nombre' => $v->mappingSet?->nombre,
                'codigo' => $v->mappingSet?->codigo,
                'vencimiento' => $v->fecha_fin_vigencia?->format('Y-m-d'),
            ]);

        return Inertia::render('Integrations/Dashboard', [
            'stats' => [
                'profiles_activos' => $profilesActivos,
                'ejecuciones_hoy' => $ejecucionesHoy,
                'registros_procesados' => $registrosProcesados,
                'registros_exitosos' => $registrosExitosos,
                'pendientes_homologacion' => $pendientes,
                'registros_fallidos' => $registrosFallidos,
                'exportaciones_hoy' => $exportacionesHoy,
                'duracion_promedio' => $duracionPromedio ? round($duracionPromedio) : null,
            ],
            'ultimas_ejecuciones' => $ultimasEjecuciones,
            'ultimos_errores' => $ultimosErrores,
            'proximos_vencer' => $proximosVencer,
        ]);
    }
}
