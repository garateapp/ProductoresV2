<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationClient;
use App\Models\IntegrationPendingMapping;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PendingMappingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'client_id', 'campo', 'resolved']);

        $mappings = IntegrationPendingMapping::with(['client', 'profile', 'resolver'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('valor_interno', 'like', "%{$v}%")
                    ->orWhere('campo', 'like', "%{$v}%");
            }))
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['campo'] ?? null, fn ($q, $v) => $q->where('campo', $v))
            ->when(filled($filters['resolved'] ?? null), fn ($q) => $q->whereNotNull('resolved_at'))
            ->when(blank($filters['resolved'] ?? null) && !isset($filters['resolved']), fn ($q) => $q->whereNull('resolved_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($m) => [
                'id' => $m->id,
                'cliente' => $m->client?->nombre,
                'perfil' => $m->profile?->nombre,
                'campo' => $m->campo,
                'valor_interno' => $m->valor_interno,
                'frecuencia' => $m->frecuencia,
                'valor_asignado' => $m->valor_asignado,
                'resolved' => !is_null($m->resolved_at),
                'resolved_at' => $m->resolved_at?->format('Y-m-d H:i'),
                'resolver' => $m->resolver?->name,
            ]);

        return Inertia::render('Integrations/PendingMappings/Index', [
            'mappings' => $mappings,
            'filters' => $filters,
            'clients' => IntegrationClient::orderBy('nombre')->get(['id', 'nombre']),
            'campos' => IntegrationPendingMapping::select('campo')->distinct()->pluck('campo'),
        ]);
    }

    public function update(Request $request, IntegrationPendingMapping $pendingMapping, IntegrationAuditService $audit)
    {
        $data = $request->validate([
            'valor_asignado' => 'required|string|max:500',
            'observacion' => 'nullable|string|max:1000',
        ]);

        $pendingMapping->update([
            'valor_asignado' => $data['valor_asignado'],
            'observacion' => $data['observacion'] ?? null,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $audit->log('pending_mapping_resolved', 'IntegrationPendingMapping', $pendingMapping->id,
            "Mapeo pendiente resuelto: {$pendingMapping->campo} = {$data['valor_asignado']}");

        return back()->with('success', 'Mapeo resuelto correctamente.');
    }

    public function bulkResolve(Request $request, IntegrationAuditService $audit)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:integration_pending_mappings,id',
            'valor_asignado' => 'required|string|max:500',
        ]);

        IntegrationPendingMapping::whereIn('id', $data['ids'])
            ->whereNull('resolved_at')
            ->update([
                'valor_asignado' => $data['valor_asignado'],
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

        $audit->log('pending_mappings_bulk_resolved', 'IntegrationPendingMapping', 0,
            count($data['ids']) . ' mapeos resueltos en lote con valor: ' . $data['valor_asignado']);

        return back()->with('success', count($data['ids']) . ' mapeos resueltos en lote.');
    }
}
