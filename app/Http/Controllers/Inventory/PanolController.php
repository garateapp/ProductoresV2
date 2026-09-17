<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\Personal;
use App\Services\Inventory\PanolReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PanolController extends Controller
{
    use AuthorizesInventory;

    public function __construct(private readonly PanolReportService $service)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = $this->readFilters($request);
        $summary = $this->service->summary($this->serviceFilters($filters));

        return Inertia::render('Inventory/Panol/Index', [
            'filters' => $filters,
            'rows' => $summary['rows'],
            'totals' => $summary['totals'],
            'people' => Personal::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'cargo', 'area']),
            'cargos' => $this->distinctValues('person_position', 'cargo'),
            'areas' => $this->distinctValues('person_area', 'area'),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeInventory($request);

        $filters = $this->readFilters($request);
        $summary = $this->service->summary($this->serviceFilters($filters));
        $rows = collect($summary['rows']);

        $filename = 'control-panol-'.($filters['date_from'] ?: 'rango').'.xlsx';

        return Excel::download(new \App\Exports\PanolReportExport($rows), $filename);
    }

    /**
     * Valores únicos de cargo/área, considerando el snapshot de las entregas
     * y los datos actuales de la persona.
     *
     * @return list<string>
     */
    private function distinctValues(string $deliveryColumn, string $personalColumn): array
    {
        $deliveryValues = \DB::table('inventory_person_deliveries')
            ->whereNotNull($deliveryColumn)
            ->where($deliveryColumn, '!=', '')
            ->distinct()
            ->pluck($deliveryColumn);

        $personalValues = \DB::table('personal')
            ->whereNotNull($personalColumn)
            ->where($personalColumn, '!=', '')
            ->distinct()
            ->pluck($personalColumn);

        return $deliveryValues
            ->merge($personalValues)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function readFilters(Request $request): array
    {
        return [
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
            'producto' => trim((string) $request->input('producto', '')),
            'persona' => trim((string) $request->input('persona', '')),
            'cargo' => trim((string) $request->input('cargo', '')),
            'area' => trim((string) $request->input('area', '')),
            'solo_con_stock' => filter_var((string) $request->input('solo_con_stock', '1'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function serviceFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'material_id' => '',
            'person_id' => $filters['persona'],
            'cargo' => $filters['cargo'],
            'area' => $filters['area'],
            'q' => $filters['producto'],
            'solo_con_stock' => $filters['solo_con_stock'],
        ];
    }
}