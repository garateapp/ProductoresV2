<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\GarcesCodeMapping;
use App\Services\Garces\GarcesCodeCsvImporter;
use App\Services\Garces\GarcesCodeDictionaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GarcesDictionaryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'tipo', 'activo']);

        $mappings = GarcesCodeMapping::query()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('clave_interna', 'like', "%{$v}%")
                    ->orWhere('clave_garces', 'like', "%{$v}%")
                    ->orWhere('notas', 'like', "%{$v}%");
            }))
            ->when($filters['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when(isset($filters['activo']) && $filters['activo'] !== '', fn ($q) => $q->where('activo', (bool) $filters['activo']))
            ->orderBy('tipo')
            ->orderBy('clave_interna')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (GarcesCodeMapping $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'clave_interna' => $m->clave_interna,
                'clave_garces' => $m->clave_garces,
                'vigencia_desde' => $m->vigencia_desde?->format('Y-m-d'),
                'vigencia_hasta' => $m->vigencia_hasta?->format('Y-m-d'),
                'prioridad' => $m->prioridad,
                'activo' => $m->activo,
                'notas' => $m->notas,
                'updated_at' => $m->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Integrations/Garces/Dictionary/Index', [
            'mappings' => $mappings,
            'filters' => $filters,
            'types' => GarcesCodeDictionaryService::types(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        GarcesCodeMapping::updateOrCreate(
            [
                'tipo' => $data['tipo'],
                'clave_interna' => $data['clave_interna'],
                'clave_garces' => $data['clave_garces'],
            ],
            array_merge($data, [
                'activo' => $data['activo'] ?? true,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ])
        );

        return back()->with('success', 'Mapeo guardado.');
    }

    public function update(Request $request, GarcesCodeMapping $mapping)
    {
        $data = $this->validated($request);

        $mapping->update(array_merge($data, ['updated_by' => $request->user()?->id]));

        return back()->with('success', 'Mapeo actualizado.');
    }

    public function destroy(GarcesCodeMapping $mapping)
    {
        $mapping->delete();

        return back()->with('success', 'Mapeo eliminado.');
    }

    public function import(Request $request, GarcesCodeCsvImporter $importer)
    {
        $request->validate([
            'csv' => 'nullable|string',
            'file' => 'nullable|file|mimes:csv,txt',
        ]);

        $csv = $request->hasFile('file')
            ? (string) $request->file('file')->get()
            : (string) $request->input('csv');

        if (trim($csv) === '') {
            return back()->with('error', 'Adjunta un archivo CSV o pega su contenido.');
        }

        $result = $importer->import($csv, $request->user()?->id);

        return back()->with(
            'success',
            "Importación completada: {$result['created']} creados, {$result['updated']} actualizados."
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'string', Rule::in(GarcesCodeDictionaryService::types())],
            'clave_interna' => 'required|string|max:160',
            'clave_garces' => 'required|string|max:80',
            'vigencia_desde' => 'nullable|date',
            'vigencia_hasta' => 'nullable|date|after_or_equal:vigencia_desde',
            'prioridad' => 'nullable|integer|min:0',
            'activo' => 'boolean',
            'notas' => 'nullable|string|max:1000',
        ]);
    }
}
