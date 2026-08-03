<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\TechnicalSheetTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryMaterial;
use App\Models\InventoryPackaging;
use App\Models\InventoryTechnicalSheet;
use App\Services\Inventory\PackagingCatalogService;
use App\Services\Inventory\TechnicalSheetImportService;
use App\Services\Inventory\TechnicalSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TechnicalSheetController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $sheets = InventoryTechnicalSheet::query()
            ->with(['packaging:id,codigo,nombre', 'creator:id,name', 'unitItems.material:id,codigo,nombre', 'unitItems.replacementMaterial:id,codigo,nombre', 'palletItems.material:id,codigo,nombre', 'palletItems.replacementMaterial:id,codigo,nombre'])
            ->orderByDesc('fecha_vigencia_desde')
            ->orderByDesc('version')
            ->get()
            ->map(fn (InventoryTechnicalSheet $sheet) => [
                'id' => $sheet->id,
                'packaging_id' => $sheet->packaging_id,
                'material_id' => $sheet->material_id,
                'packaging' => trim(($sheet->packaging?->codigo ?? '').' · '.($sheet->packaging?->nombre ?? '')),
                'es_semielaborado' => $sheet->es_semielaborado,
                'version' => $sheet->version,
                'fecha_vigencia_desde' => optional($sheet->fecha_vigencia_desde)->format('Y-m-d'),
                'fecha_vigencia_hasta' => optional($sheet->fecha_vigencia_hasta)->format('Y-m-d'),
                'activo' => $sheet->activo,
                'observacion' => $sheet->observacion,
                'creator' => $sheet->creator?->name,
                'unit_items' => $sheet->unitItems->map(fn ($item) => [
                    'material_id' => $item->material_id,
                    'replacement_material_id' => $item->replacement_material_id,
                    'label' => trim(($item->material?->codigo ?? '').' · '.($item->material?->nombre ?? '')),
                    'replacement_label' => $item->replacementMaterial ? trim(($item->replacementMaterial->codigo ?? '').' · '.($item->replacementMaterial->nombre ?? '')) : null,
                    'cantidad_estandar' => (float) $item->cantidad_estandar,
                    'calibre' => $item->calibre,
                ])->values(),
                'pallet_items' => $sheet->palletItems->map(fn ($item) => [
                    'material_id' => $item->material_id,
                    'replacement_material_id' => $item->replacement_material_id,
                    'label' => trim(($item->material?->codigo ?? '').' · '.($item->material?->nombre ?? '')),
                    'replacement_label' => $item->replacementMaterial ? trim(($item->replacementMaterial->codigo ?? '').' · '.($item->replacementMaterial->nombre ?? '')) : null,
                    'cantidad_estandar' => (float) $item->cantidad_estandar,
                    'calibre' => $item->calibre,
                ])->values(),
            ]);

        return Inertia::render('Inventory/TechnicalSheets/Index', [
            'sheets' => $sheets,
            'packagings' => InventoryPackaging::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'tipo', 'cantidad_cajas', 'altura']),
            'materials' => InventoryMaterial::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Request $request, TechnicalSheetService $sheetService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $sheetService->create($this->validateSheet($request), (int) $request->user()->id);

        return back()->with('success', 'Ficha técnica creada.');
    }

    public function update(Request $request, InventoryTechnicalSheet $technicalSheet, TechnicalSheetService $sheetService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $sheetService->update($technicalSheet, $this->validateSheet($request));

        return back()->with('success', 'Ficha técnica actualizada.');
    }

    public function syncPackagings(Request $request, PackagingCatalogService $catalogService): RedirectResponse
    {
        $this->authorizeInventory($request);

        try {
            $summary = $catalogService->syncFromSqlsrv();
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'No fue posible sincronizar embalajes desde SQL Server.');
        }

        return back()->with('success', "Embalajes sincronizados. {$summary['created']} creados, {$summary['updated']} actualizados.");
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->authorizeInventory($request);

        return Excel::download(new TechnicalSheetTemplateExport, 'plantilla-fichas-tecnicas.xlsx');
    }

    public function import(Request $request, TechnicalSheetImportService $importService): RedirectResponse
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
            $absolutePath = $file->getRealPath();
            $result = $importService->importFromExcel($absolutePath, (int) $request->user()->id);

            if (empty($result['errors'])) {
                return back()->with('success', "Se crearon {$result['created']} fichas técnicas correctamente.");
            }

            $errorSummary = implode("\n", $result['errors']);
            $createdMsg = $result['created'] > 0 ? "Se crearon {$result['created']} fichas técnicas. " : '';

            return back()->with('warning', $createdMsg . 'Errores: ' . $errorSummary);
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'Error al procesar el archivo: ' . $exception->getMessage());
        }
    }

    private function validateSheet(Request $request): array
    {
        return $request->validate([
            'es_semielaborado' => ['boolean'],
            'packaging_id' => ['required_if:es_semielaborado,false', 'nullable', 'exists:inventory_packagings,id'],
            'material_id' => ['required_if:es_semielaborado,true', 'nullable', 'exists:inventory_materials,id'],
            'fecha_vigencia_desde' => ['required', 'date'],
            'fecha_vigencia_hasta' => ['nullable', 'date', 'after_or_equal:fecha_vigencia_desde'],
            'activo' => ['boolean'],
            'observacion' => ['nullable', 'string'],
            'unit_items' => ['array'],
            'unit_items.*.material_id' => ['nullable', 'exists:inventory_materials,id'],
            'unit_items.*.cantidad_estandar' => ['nullable', 'numeric', 'gt:0'],
            'unit_items.*.calibre' => ['nullable', 'string', 'max:20'],
            'pallet_items' => ['array'],
            'pallet_items.*.material_id' => ['nullable', 'exists:inventory_materials,id'],
            'pallet_items.*.cantidad_estandar' => ['nullable', 'numeric', 'gt:0'],
            'pallet_items.*.calibre' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
