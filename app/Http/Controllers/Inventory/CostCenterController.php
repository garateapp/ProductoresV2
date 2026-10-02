<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\CostCenterTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryCostCenter;
use App\Models\Service;
use App\Services\Inventory\CostCenterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CostCenterController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $costCenters = InventoryCostCenter::query()
            ->with('service:id,name')
            ->orderBy('codigo')
            ->get()
            ->map(fn (InventoryCostCenter $costCenter) => [
                'id' => $costCenter->id,
                'codigo' => $costCenter->codigo,
                'nombre' => $costCenter->nombre,
                'descripcion' => $costCenter->descripcion,
                'service_id' => $costCenter->service_id,
                'service' => $costCenter->service ? [
                    'id' => $costCenter->service->id,
                    'name' => $costCenter->service->name,
                ] : null,
                'activo' => (bool) $costCenter->activo,
                'deliveries_count' => $costCenter->personDeliveries()->count(),
            ]);

        $services = Service::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Inventory/CostCenters/Index', [
            'costCenters' => $costCenters,
            'services' => $services,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        InventoryCostCenter::query()->create($this->validated($request));

        return back()->with('success', 'Centro de costo creado.');
    }

    public function update(Request $request, InventoryCostCenter $costCenter): RedirectResponse
    {
        $this->authorizeInventory($request);

        $costCenter->fill($this->validated($request, $costCenter))->save();

        return back()->with('success', 'Centro de costo actualizado.');
    }

    public function destroy(Request $request, InventoryCostCenter $costCenter): RedirectResponse
    {
        $this->authorizeInventory($request);

        // No se elimina si ya tiene entregas asociadas: la trazabilidad histórica
        // debe conservarlas, por eso la columna es nullOnDelete y se desactiva.
        if ($costCenter->personDeliveries()->exists()) {
            $costCenter->forceFill(['activo' => false])->save();

            return back()->with(
                'error',
                'El centro de costo tiene entregas asociadas, se desactivó en lugar de eliminarse.'
            );
        }

        $costCenter->delete();

        return back()->with('success', 'Centro de costo eliminado.');
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->authorizeInventory($request);

        return Excel::download(new CostCenterTemplateExport, 'plantilla-centros-costo.xlsx');
    }

    public function import(Request $request, CostCenterImportService $importService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return back()->with('error', 'Archivo inválido.');
        }

        try {
            $result = $importService->importFromExcel($file->getRealPath());

            $summary = trim(sprintf('Creados: %d. Actualizados: %d.', $result['created'], $result['updated']));

            if (empty($result['errors'])) {
                return back()->with('success', "Carga masiva completada. {$summary}");
            }

            return back()->with('warning', "Carga masiva completada con errores. {$summary} ".implode(' | ', $result['errors']));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Error al procesar el archivo: '.$exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?InventoryCostCenter $costCenter = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_cost_centers', 'codigo')->ignore($costCenter?->id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'activo' => ['boolean'],
        ]);

        $data['codigo'] = mb_strtoupper(trim((string) $data['codigo']));
        $data['descripcion'] = trim((string) ($data['descripcion'] ?? '')) ?: null;
        $data['service_id'] = $data['service_id'] !== null && $data['service_id'] !== ''
            ? (int) $data['service_id']
            : null;
        $data['activo'] = (bool) ($data['activo'] ?? true);

        return $data;
    }
}
