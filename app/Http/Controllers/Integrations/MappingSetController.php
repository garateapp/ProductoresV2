<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationClient;
use App\Models\IntegrationMappingSet;
use App\Models\IntegrationMappingSetVersion;
use App\Models\IntegrationMappingItem;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MappingSetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IntegrationMappingSet::class);
        $filters = $request->only(['q', 'client_id']);

        $mappingSets = IntegrationMappingSet::with(['client', 'currentVersion'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                    ->orWhere('codigo', 'like', "%{$v}%");
            }))
            ->when($filters['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($ms) => [
                'id' => $ms->id,
                'codigo' => $ms->codigo,
                'nombre' => $ms->nombre,
                'cliente' => $ms->client?->nombre,
                'estado' => $ms->estado,
                'version' => $ms->currentVersion?->version,
                'created_at' => $ms->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/MappingSets/Index', [
            'mappingSets' => $mappingSets,
            'filters' => $filters,
            'clients' => IntegrationClient::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request, IntegrationAuditService $audit)
    {
        $this->authorize('create', IntegrationMappingSet::class);

        $data = $request->validate([
            'client_id' => 'required|exists:integration_clients,id',
            'codigo' => 'required|string|max:50|unique:integration_mapping_sets,codigo',
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
        ]);

        $mappingSet = IntegrationMappingSet::create([
            ...$data,
            'estado' => 'borrador',
            'created_by' => $request->user()->id,
        ]);

        $version = IntegrationMappingSetVersion::create([
            'mapping_set_id' => $mappingSet->id,
            'version' => 1,
            'estado' => 'borrador',
            'created_by' => $request->user()->id,
        ]);

        $mappingSet->update(['current_version_id' => $version->id]);

        $audit->mappingSetCreated($mappingSet->id, $mappingSet->nombre);

        return redirect()->route('integrations.mapping-sets.show', $mappingSet)
            ->with('success', 'Conjunto de mapeo creado.');
    }

    public function show(IntegrationMappingSet $mappingSet)
    {
        $this->authorize('view', $mappingSet);

        $mappingSet->load(['client', 'currentVersion.items' => fn ($q) => $q->orderBy('valor_interno')]);

        $version = $mappingSet->currentVersion;

        return Inertia::render('Integrations/MappingSets/Show', [
            'mappingSet' => [
                'id' => $mappingSet->id,
                'codigo' => $mappingSet->codigo,
                'nombre' => $mappingSet->nombre,
                'descripcion' => $mappingSet->descripcion,
                'cliente' => $mappingSet->client?->nombre,
                'estado' => $mappingSet->estado,
                'version' => $version?->version,
                'version_estado' => $version?->estado,
                'inmutable' => $version?->inmutable,
                'fecha_inicio_vigencia' => $version?->fecha_inicio_vigencia?->format('Y-m-d'),
                'fecha_fin_vigencia' => $version?->fecha_fin_vigencia?->format('Y-m-d'),
                'created_at' => $mappingSet->created_at?->format('Y-m-d H:i'),
            ],
            'items' => $version?->items->map(fn ($i) => [
                'id' => $i->id,
                'valor_interno' => $i->valor_interno,
                'valor_externo' => $i->valor_externo,
                'descripcion' => $i->descripcion,
                'activo' => $i->activo,
                'orden' => $i->orden,
            ]) ?? [],
        ]);
    }

    public function publish(Request $request, IntegrationMappingSet $mappingSet, IntegrationAuditService $audit)
    {
        $this->authorize('publish', $mappingSet);

        $version = $mappingSet->currentVersion;

        if (!$version) {
            return back()->with('error', 'El conjunto no tiene una versión para publicar.');
        }

        if ($version->items()->count() === 0) {
            return back()->with('error', 'No se puede publicar un conjunto sin ítems.');
        }

        $version->update([
            'estado' => 'publicado',
            'inmutable' => true,
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        $mappingSet->update(['estado' => 'publicado']);

        $audit->log('mapping_set_published', 'integration_mapping_set', $mappingSet->id,
            "{$mappingSet->nombre} v{$version->version}");

        return back()->with('success', 'Conjunto de mapeo publicado.');
    }

    public function importBulk(Request $request, IntegrationMappingSet $mappingSet)
    {
        $this->authorize('update', $mappingSet);

        $version = $mappingSet->currentVersion;

        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden importar ítems en una versión inmutable.');
        }

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.valor_interno' => 'required|string|max:200',
            'items.*.valor_externo' => 'required|string|max:200',
            'items.*.descripcion' => 'nullable|string|max:500',
        ]);

        $count = 0;
        foreach ($data['items'] as $item) {
            IntegrationMappingItem::updateOrCreate(
                [
                    'mapping_set_version_id' => $version->id,
                    'valor_interno' => $item['valor_interno'],
                ],
                [
                    'valor_externo' => $item['valor_externo'],
                    'descripcion' => $item['descripcion'] ?? null,
                    'activo' => true,
                ]
            );
            $count++;
        }

        return back()->with('success', "{$count} ítems importados correctamente.");
    }
}
