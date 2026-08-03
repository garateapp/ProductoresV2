<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IntegrationProfile::class);

        $filters = $request->only(['q', 'activo']);

        $clients = IntegrationClient::with(['createdBy'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nombre', 'like', "%{$v}%")
                    ->orWhere('codigo', 'like', "%{$v}%")
                    ->orWhere('rut', 'like', "%{$v}%");
            }))
            ->when(isset($filters['activo']), fn ($q) => $q->where('activo', $filters['activo'] === 'true'))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($client) => [
                'id' => $client->id,
                'codigo' => $client->codigo,
                'nombre' => $client->nombre,
                'rut' => $client->rut,
                'email' => $client->email,
                'contacto' => $client->contacto,
                'activo' => $client->activo,
                'profiles_count' => $client->profiles()->count(),
                'creador' => $client->createdBy?->name,
                'created_at' => $client->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Clients/Index', [
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $this->authorize('create', IntegrationProfile::class);

        return Inertia::render('Integrations/Clients/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', IntegrationProfile::class);

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:integration_clients,codigo',
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $client = IntegrationClient::create([
            ...$data,
            'activo' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('integrations.clients.show', $client)
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(IntegrationClient $client)
    {
        $this->authorize('viewAny', IntegrationProfile::class);

        $client->load(['createdBy', 'updatedBy', 'profiles' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('Integrations/Clients/Show', [
            'client' => [
                'id' => $client->id,
                'codigo' => $client->codigo,
                'nombre' => $client->nombre,
                'rut' => $client->rut,
                'email' => $client->email,
                'contacto' => $client->contacto,
                'descripcion' => $client->descripcion,
                'activo' => $client->activo,
                'metadata' => $client->metadata,
                'creador' => $client->createdBy?->name,
                'actualizador' => $client->updatedBy?->name,
                'created_at' => $client->created_at?->format('Y-m-d H:i'),
                'updated_at' => $client->updated_at?->format('Y-m-d H:i'),
                'profiles' => $client->profiles->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'direccion' => $p->direccion,
                    'estado' => $p->estado?->label(),
                    'activo' => $p->activo,
                ]),
                'profiles_count' => $client->profiles()->count(),
            ],
        ]);
    }

    public function edit(IntegrationClient $client)
    {
        $this->authorize('create', IntegrationProfile::class);

        return Inertia::render('Integrations/Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'codigo' => $client->codigo,
                'nombre' => $client->nombre,
                'rut' => $client->rut,
                'email' => $client->email,
                'contacto' => $client->contacto,
                'descripcion' => $client->descripcion,
                'activo' => $client->activo,
                'metadata' => $client->metadata,
            ],
        ]);
    }

    public function update(Request $request, IntegrationClient $client)
    {
        $this->authorize('create', IntegrationProfile::class);

        $data = $request->validate([
            'codigo' => "required|string|max:50|unique:integration_clients,codigo,{$client->id}",
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contacto' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $client->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('integrations.clients.show', $client)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(IntegrationClient $client)
    {
        $this->authorize('create', IntegrationProfile::class);

        if ($client->profiles()->count() > 0) {
            return back()->with('error', 'No se puede eliminar el cliente porque tiene perfiles asociados.');
        }

        $client->delete();

        return redirect()->route('integrations.clients.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }
}
