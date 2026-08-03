<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationSourceAdapter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SourceAdapterController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IntegrationProfile::class);

        $filters = $request->only(['q', 'tipo_conexion', 'activo']);

        $adapters = IntegrationSourceAdapter::with(['createdBy'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                    ->orWhere('key', 'like', "%{$v}%");
            }))
            ->when($filters['tipo_conexion'] ?? null, fn ($q, $v) => $q->where('tipo_conexion', $v))
            ->when(isset($filters['activo']), fn ($q) => $q->where('activo', $filters['activo'] === 'true'))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($adapter) => [
                'id' => $adapter->id,
                'key' => $adapter->key,
                'nombre' => $adapter->nombre,
                'tipo_conexion' => $adapter->tipo_conexion,
                'tipo_conexion_label' => match ($adapter->tipo_conexion) {
                    'database' => 'Base de Datos',
                    'api_rest' => 'API REST',
                    'archivo' => 'Archivo',
                    'ftp' => 'FTP/SFTP',
                    default => $adapter->tipo_conexion,
                },
                'activo' => $adapter->activo,
                'profiles_count' => $adapter->profiles()->count(),
                'creador' => $adapter->createdBy?->name,
                'created_at' => $adapter->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/SourceAdapters/Index', [
            'adapters' => $adapters,
            'filters' => $filters,
            'tipos_conexion' => [
                ['value' => 'database', 'label' => 'Base de Datos'],
                ['value' => 'api_rest', 'label' => 'API REST'],
                ['value' => 'archivo', 'label' => 'Archivo'],
                ['value' => 'ftp', 'label' => 'FTP/SFTP'],
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', IntegrationProfile::class);

        return Inertia::render('Integrations/SourceAdapters/Create', [
            'tipos_conexion' => [
                ['value' => 'database', 'label' => 'Base de Datos'],
                ['value' => 'api_rest', 'label' => 'API REST'],
                ['value' => 'archivo', 'label' => 'Archivo'],
                ['value' => 'ftp', 'label' => 'FTP/SFTP'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', IntegrationProfile::class);

        $data = $request->validate([
            'key' => 'required|string|max:100|unique:integration_source_adapters,key|regex:/^[a-z0-9_]+$/',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_conexion' => 'required|in:database,api_rest,archivo,ftp',
            'configuracion' => 'required|array',
            'esquema_entrada' => 'nullable|array',
            'activo' => 'boolean',
        ]);

        $adapter = IntegrationSourceAdapter::create([
            ...$data,
            'activo' => $data['activo'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('integrations.source-adapters.show', $adapter)
            ->with('success', 'Adapter creado correctamente.');
    }

    public function show(IntegrationSourceAdapter $sourceAdapter)
    {
        $this->authorize('viewAny', IntegrationProfile::class);

        $sourceAdapter->load(['createdBy', 'updatedBy']);

        $profiles = $sourceAdapter->profiles()->with('client')->latest()->limit(10)->get();

        return Inertia::render('Integrations/SourceAdapters/Show', [
            'adapter' => [
                'id' => $sourceAdapter->id,
                'key' => $sourceAdapter->key,
                'nombre' => $sourceAdapter->nombre,
                'descripcion' => $sourceAdapter->descripcion,
                'tipo_conexion' => $sourceAdapter->tipo_conexion,
                'tipo_conexion_label' => match ($sourceAdapter->tipo_conexion) {
                    'database' => 'Base de Datos',
                    'api_rest' => 'API REST',
                    'archivo' => 'Archivo',
                    'ftp' => 'FTP/SFTP',
                    default => $sourceAdapter->tipo_conexion,
                },
                'configuracion' => $sourceAdapter->configuracion,
                'esquema_entrada' => $sourceAdapter->esquema_entrada,
                'activo' => $sourceAdapter->activo,
                'creador' => $sourceAdapter->createdBy?->name,
                'actualizador' => $sourceAdapter->updatedBy?->name,
                'created_at' => $sourceAdapter->created_at?->format('Y-m-d H:i'),
                'updated_at' => $sourceAdapter->updated_at?->format('Y-m-d H:i'),
                'profiles' => $profiles->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'cliente' => $p->client?->nombre,
                    'direccion' => $p->direccion,
                    'activo' => $p->activo,
                ]),
            ],
        ]);
    }

    public function edit(IntegrationSourceAdapter $sourceAdapter)
    {
        $this->authorize('create', IntegrationProfile::class);

        return Inertia::render('Integrations/SourceAdapters/Edit', [
            'adapter' => [
                'id' => $sourceAdapter->id,
                'key' => $sourceAdapter->key,
                'nombre' => $sourceAdapter->nombre,
                'descripcion' => $sourceAdapter->descripcion,
                'tipo_conexion' => $sourceAdapter->tipo_conexion,
                'configuracion' => $sourceAdapter->configuracion,
                'esquema_entrada' => $sourceAdapter->esquema_entrada,
                'activo' => $sourceAdapter->activo,
            ],
            'tipos_conexion' => [
                ['value' => 'database', 'label' => 'Base de Datos'],
                ['value' => 'api_rest', 'label' => 'API REST'],
                ['value' => 'archivo', 'label' => 'Archivo'],
                ['value' => 'ftp', 'label' => 'FTP/SFTP'],
            ],
        ]);
    }

    public function update(Request $request, IntegrationSourceAdapter $sourceAdapter)
    {
        $this->authorize('create', IntegrationProfile::class);

        $data = $request->validate([
            'key' => "required|string|max:100|unique:integration_source_adapters,key,{$sourceAdapter->id}|regex:/^[a-z0-9_]+$/",
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo_conexion' => 'required|in:database,api_rest,archivo,ftp',
            'configuracion' => 'required|array',
            'esquema_entrada' => 'nullable|array',
            'activo' => 'boolean',
        ]);

        $sourceAdapter->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('integrations.source-adapters.show', $sourceAdapter)
            ->with('success', 'Adapter actualizado correctamente.');
    }

    public function destroy(IntegrationSourceAdapter $sourceAdapter)
    {
        $this->authorize('create', IntegrationProfile::class);

        if ($sourceAdapter->profiles()->count() > 0) {
            return back()->with('error', 'No se puede eliminar el adapter porque tiene perfiles asociados.');
        }

        $sourceAdapter->delete();

        return redirect()->route('integrations.source-adapters.index')
            ->with('success', 'Adapter eliminado correctamente.');
    }
}
