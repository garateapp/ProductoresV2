<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Services\Inventory\CentralStockSyncService;
use App\Services\Inventory\MaterialCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MaterialController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = $request->only(['q', 'family_id', 'service_id', 'active']);

        $materials = InventoryMaterial::query()
            ->with(['family:id,nombre', 'unit:id,codigo', 'service:id,name'])
            ->withSum('stockLocations as internal_stock', 'stock_actual')
            ->when($filters['q'] ?? null, function ($query, $value) {
                $needle = trim((string) $value);
                $query->where(function ($inner) use ($needle): void {
                    $inner->where('codigo', 'like', '%'.$needle.'%')
                        ->orWhere('nombre', 'like', '%'.$needle.'%');
                });
            })
            ->when($filters['family_id'] ?? null, fn ($query, $value) => $query->where('family_id', $value))
            ->when($filters['service_id'] ?? null, fn ($query, $value) => $query->where('service_id', $value))
            ->when(($filters['active'] ?? '') !== '', fn ($query) => $query->where('activo', (bool) $request->boolean('active')))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryMaterial $material) => [
                'id' => $material->id,
                'codigo' => $material->codigo,
                'nombre' => $material->nombre,
                'descripcion' => $material->descripcion,
                'tipo_material' => $material->tipo_material,
                'family_id' => $material->family_id,
                'unit_id' => $material->unit_id,
                'service_id' => $material->service_id,
                'familia' => $material->family?->nombre,
                'unidad' => $material->unit?->codigo,
                'servicio' => $material->service?->name,
                'sap_on_hand' => (float) $material->sap_on_hand,
                'internal_stock' => (float) ($material->internal_stock ?? 0),
                'stock_minimo' => (float) $material->stock_minimo,
                'activo' => (bool) $material->activo,
            ]);

        return Inertia::render('Inventory/Materials/Index', [
            'materials' => $materials,
            'filters' => $filters,
            'families' => InventoryMaterialFamily::query()->orderBy('nombre')->get(['id', 'nombre']),
            'units' => InventoryUnit::query()->orderBy('codigo')->get(['id', 'codigo']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:inventory_materials,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'family_id' => ['nullable', 'exists:inventory_material_families,id'],
            'unit_id' => ['nullable', 'exists:inventory_units,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'tipo_material' => ['required', 'in:consumo,semielaborado,retornable'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ]);

        InventoryMaterial::create($data);

        return back()->with('success', 'Material creado.');
    }

    public function update(Request $request, InventoryMaterial $material): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'family_id' => ['nullable', 'exists:inventory_material_families,id'],
            'unit_id' => ['nullable', 'exists:inventory_units,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'tipo_material' => ['required', 'in:consumo,semielaborado,retornable'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ]);

        $material->fill($data)->save();

        return back()->with('success', 'Material actualizado.');
    }

    public function syncSap(Request $request, MaterialCatalogService $catalogService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $summary = $catalogService->syncFromSap(
                $request->filled('desde') ? $request->input('desde') : null,
                $request->filled('hasta') ? $request->input('hasta') : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'No fue posible sincronizar materiales desde SAP.');
        }

        return back()->with('success', "SAP sincronizado. {$summary['total']} filas, {$summary['created']} creados, {$summary['updated']} actualizados.");
    }

    public function syncCentralStock(Request $request, CentralStockSyncService $centralStockSyncService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $summary = $centralStockSyncService->syncFromSapOnHand((int) $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            $message = method_exists($exception, 'errors')
                ? collect($exception->errors())->flatten()->filter()->first()
                : null;

            return back()->with('error', $message ?: 'No fue posible cargar el stock SAP a Bodega Central.');
        }

        return back()->with(
            'success',
            "Stock conciliado en {$summary['location']}. {$summary['materials_adjusted']} materiales ajustados, {$summary['positive_count']} positivos, {$summary['negative_count']} negativos."
        );
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorizeInventory($request);

        $header = ['codigo', 'nombre', 'unidad', 'tipo', 'servicio'];

        return response()->streamDownload(function () use ($header) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';');
            fclose($handle);
        }, 'plantilla-materiales.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        $header = array_map(fn ($column) => trim(ltrim((string) $column, "\xEF\xBB\xBF")), $header);
        Log::info('CSV Header', ['header' => $header]);
        $expectedHeaders = ['codigo', 'nombre', 'unidad', 'tipo', 'servicio'];

        $headerLower = array_map('strtolower', $header);

        if (count(array_intersect($expectedHeaders, $headerLower)) < count($expectedHeaders)) {
            fclose($handle);

            return back()->with('error', 'El archivo CSV debe contener las columnas: codigo, nombre, unidad, tipo, servicio');
        }

        $codigoIdx = array_search('codigo', $headerLower);
        $nombreIdx = array_search('nombre', $headerLower);
        $unidadIdx = array_search('unidad', $headerLower);
        $tipoIdx = array_search('tipo', $headerLower);
        $servicioIdx = array_search('servicio', $headerLower);

        $created = 0;
        $updated = 0;
        $errors = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $codigo = trim($row[$codigoIdx] ?? '');
            $nombre = trim($row[$nombreIdx] ?? '');
            $unidadCodigo = trim($row[$unidadIdx] ?? '');
            $tipo = trim($row[$tipoIdx] ?? '');
            $servicioNombre = $servicioIdx !== false ? trim($row[$servicioIdx] ?? '') : null;

            if (empty($codigo) || empty($nombre) || empty($unidadCodigo) || empty($tipo)) {
                $errors[] = 'Fila ignorada: código, nombre, unidad o tipo vacío.';

                continue;
            }

            $tipoNormalizado = match (strtolower($tipo)) {
                'consumo' => 'consumo',
                'semielaborado', 'semi-elaborado', 'semielab' => 'semielaborado',
                'retornable' => 'retornable',
                default => null,
            };

            if (! $tipoNormalizado) {
                $errors[] = "Fila ignorada: tipo de material inválido '{$tipo}' para código '{$codigo}'.";

                continue;
            }

            $unit = InventoryUnit::where('codigo', $unidadCodigo)->first();
            if (! $unit) {
                $errors[] = "Fila ignorada: unidad '{$unidadCodigo}' no encontrada para código '{$codigo}'.";

                continue;
            }

            $service = null;
            if ($servicioNombre) {
                $service = Service::where('name', 'like', '%'.$servicioNombre.'%')->first();
            }

            $material = InventoryMaterial::where('codigo', $codigo)->first();

            $data = [
                'nombre' => $nombre,
                'unit_id' => $unit->id,
                'tipo_material' => $tipoNormalizado,
                'service_id' => $service?->id,
                'activo' => true,
            ];

            if ($material) {
                $material->update($data);
                $updated++;
            } else {
                $data['codigo'] = $codigo;
                InventoryMaterial::create($data);
                $created++;
            }
        }

        fclose($handle);

        $message = "Importación completada: {$created} creados, {$updated} actualizados.";
        if (! empty($errors)) {
            $message .= ' Algunos errores: '.implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= '...';
            }
        }

        return back()->with('success', $message);
    }
}
