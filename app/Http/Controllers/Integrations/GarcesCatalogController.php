<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GarcesCodeCatalog;
use App\Services\Garces\GarcesCodeCatalogService;
use App\Services\Garces\GarcesCodeDictionaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GarcesCatalogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'tipo']);

        $catalogs = GarcesCodeCatalog::query()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('codigo_interno', 'like', "%{$v}%")
                    ->orWhere('nombre', 'like', "%{$v}%");
            }))
            ->when($filters['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->orderBy('tipo')
            ->orderBy('codigo_interno')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (GarcesCodeCatalog $c) => [
                'id' => $c->id,
                'tipo' => $c->tipo,
                'codigo_interno' => $c->codigo_interno,
                'nombre' => $c->nombre,
                'activo' => $c->activo,
                'notas' => $c->notas,
            ]);

        return Inertia::render('Integrations/Garces/Catalog/Index', [
            'catalogs' => $catalogs,
            'filters' => $filters,
            'types' => GarcesCodeDictionaryService::types(),
        ]);
    }

    public function store(Request $request, GarcesCodeCatalogService $service)
    {
        $data = $this->validated($request);

        $service->upsert(
            $data['tipo'],
            $data['codigo_interno'],
            $data['nombre'] ?? null,
            $data['notas'] ?? null,
            $request->user()?->id
        );

        return back()->with('success', 'Código interno guardado.');
    }

    public function update(Request $request, GarcesCodeCatalog $catalog)
    {
        $data = $this->validated($request);

        $catalog->update([
            'tipo' => $data['tipo'],
            'codigo_interno' => $data['codigo_interno'],
            'nombre' => $data['nombre'] ?? null,
            'notas' => $data['notas'] ?? null,
            'activo' => $data['activo'] ?? $catalog->activo,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Código interno actualizado.');
    }

    public function destroy(GarcesCodeCatalog $catalog)
    {
        $catalog->delete();

        return back()->with('success', 'Código interno eliminado.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'string', Rule::in(GarcesCodeDictionaryService::types())],
            'codigo_interno' => 'required|string|max:80',
            'nombre' => 'nullable|string|max:150',
            'activo' => 'boolean',
            'notas' => 'nullable|string|max:1000',
        ]);
    }
}
