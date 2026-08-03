<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Http\Requests\Inventory\DisposeWasteRecordRequest;
use App\Http\Requests\Inventory\ReviewWasteRecordRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryWasteReason;
use App\Models\InventoryWasteRecord;
use App\Services\Inventory\WasteManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\InventoryDestructionAct;
use Spatie\Browsershot\Browsershot;


class WasteController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'detected_location_id' => (string) $request->input('detected_location_id', ''),
            'quarantine_location_id' => (string) $request->input('quarantine_location_id', ''),
            'material_id' => (string) $request->input('material_id', ''),
            'waste_reason_id' => (string) $request->input('waste_reason_id', ''),
            'status' => (string) $request->input('status', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        $baseQuery = InventoryWasteRecord::query()
            ->with([
                'material:id,codigo,nombre',
                'logisticUnit:id,license_plate_number',
                'detectedLocation:id,codigo,nombre,path_code',
                'quarantineLocation:id,codigo,nombre,path_code',
                'reason:id,codigo,nombre',
                'reporter:id,name',
                'movementDetail:id,movement_id,metadata',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($inner) use ($filters): void {
                    $inner->where('code', 'like', '%'.$filters['q'].'%')
                        ->orWhere('notes', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['detected_location_id'] !== '', fn ($query) => $query->where('detected_location_id', $filters['detected_location_id']))
            ->when($filters['quarantine_location_id'] !== '', fn ($query) => $query->where('quarantine_location_id', $filters['quarantine_location_id']))
            ->when($filters['material_id'] !== '', fn ($query) => $query->where('material_id', $filters['material_id']))
            ->when($filters['waste_reason_id'] !== '', fn ($query) => $query->where('waste_reason_id', $filters['waste_reason_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'] !== '', fn ($query) => $query->whereDate('reported_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($query) => $query->whereDate('reported_at', '<=', $filters['date_to']));

        $summaryQuery = clone $baseQuery;
        $wastes = $baseQuery
            ->latest('reported_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryWasteRecord $record) => [
                'id' => $record->id,
                'code' => $record->code,
                'quantity' => (float) $record->quantity,
                'status' => $record->status,
                'reported_at' => optional($record->reported_at)->format('Y-m-d H:i'),
                'material' => $record->material ? trim($record->material->codigo.' · '.$record->material->nombre) : '-',
                'logistic_unit' => $record->logisticUnit?->license_plate_number,
                'detected_location' => $record->detectedLocation?->path_code ?: $record->detectedLocation?->nombre,
                'quarantine_location' => $record->quarantineLocation?->path_code ?: $record->quarantineLocation?->nombre,
                'quarantine_location_id' => $record->quarantine_location_id,
                'reason' => $record->reason?->nombre,
                'reported_by' => $record->reporter?->name,
                'requires_supervisor_review' => $record->requires_supervisor_review,
                'notes' => $record->notes,
                'position_label' => $this->formatPositionLabel((array) ($record->movementDetail?->metadata ?? [])),
            ]);

        $totalsByLocation = $summaryQuery
            ->selectRaw('detected_location_id, SUM(quantity) as total')
            ->groupBy('detected_location_id')
            ->with('detectedLocation:id,nombre,path_code')
            ->get()
            ->map(fn (InventoryWasteRecord $record) => [
                'location' => $record->detectedLocation?->path_code ?: $record->detectedLocation?->nombre,
                'total' => (float) $record->total,
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        return Inertia::render('Inventory/Waste/Index', [
            'filters' => $filters,
            'wastes' => $wastes,
            'summary' => [
                'total_quantity' => round((float) (clone $summaryQuery)->sum('quantity'), 4),
                'pending_review' => (clone $summaryQuery)->where('status', 'review_pending')->count(),
                'reported_today' => (clone $summaryQuery)->whereDate('reported_at', now()->toDateString())->count(),
                'top_locations' => $totalsByLocation,
            ],
            'locations' => InventoryLocation::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'path_code']),
            'materials' => InventoryMaterial::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'reasons' => InventoryWasteReason::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'statuses' => ['reported', 'review_pending', 'approved', 'sent_to_quarantine', 'disposed', 'reversed'],
        ]);
    }


    public function pdfAct(InventoryWasteRecord $wasteRecord)
    {
        $act = $wasteRecord->destructionAct()->with('user')->firstOrFail();
        $html = view('reports.destruction_act', ['record' => $wasteRecord, 'act' => $act])->render();

        try {
            return response()->streamDownload(function () use ($html) {
                echo Browsershot::html($html)
                //->setTemporaryDirectory($tmpDir)
                //  ->setChromePath($chrome)
                //  ->setOption('executablePath', $chrome)
                ->setOption('headless', true)
                ->noSandbox()
                ->addChromiumArguments([
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--font-render-hinting=none',
                    '--headless=new',
                ])
                ->waitUntilNetworkIdle()
                ->wait(15)
                ->setViewport(1920, 1080)
                ->landscape(false)
                ->showBackground()
                    ->format('A4')
                    ->margins(10, 10, 10, 10)
                    ->pdf();
            }, "Acta_Destruccion_{$act->folio}.pdf");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generando PDF de acta', ['error' => $e->getMessage()]);
            return back()->with('error', 'No se pudo generar el documento PDF.');
        }
    }

    public function show(Request $request, InventoryWasteRecord $wasteRecord){
        $this->authorizeInventory($request);

        $wasteRecord->load(['material', 'logisticUnit', 'detectedLocation', 'quarantineLocation', 'reason', 'reporter', 'reviewer', 'movementDetail']);

        return response()->json([
            'id' => $wasteRecord->id,
            'code' => $wasteRecord->code,
            'quantity' => (float) $wasteRecord->quantity,
            'status' => $wasteRecord->status,
            'severity' => $wasteRecord->severity,
            'reported_at' => optional($wasteRecord->reported_at)->format('Y-m-d H:i'),
            'reviewed_at' => optional($wasteRecord->reviewed_at)->format('Y-m-d H:i'),
            'material' => $wasteRecord->material ? trim($wasteRecord->material->codigo.' · '.$wasteRecord->material->nombre) : '-',
            'logistic_unit' => $wasteRecord->logisticUnit?->license_plate_number,
            'detected_location' => $wasteRecord->detectedLocation?->path_code ?: $wasteRecord->detectedLocation?->nombre,
            'quarantine_location' => $wasteRecord->quarantineLocation?->path_code ?: $wasteRecord->quarantineLocation?->nombre,
            'quarantine_location_id' => $wasteRecord->quarantine_location_id,
            'reason' => $wasteRecord->reason?->nombre,
            'reported_by' => $wasteRecord->reporter?->name,
            'reviewed_by' => $wasteRecord->reviewer?->name,
            'requires_supervisor_review' => $wasteRecord->requires_supervisor_review,
            'notes' => $wasteRecord->notes,
            'position_label' => $this->formatPositionLabel((array) ($wasteRecord->movementDetail?->metadata ?? [])),
        ]);
    }

    public function review(ReviewWasteRecordRequest $request, InventoryWasteRecord $wasteRecord, WasteManagementService $wasteManagementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $wasteManagementService->review($wasteRecord, (int) $request->user()->id, $request->validated());

        return back()->with('success', 'Merma revisada.');
    }

    public function sendToQuarantine(Request $request, InventoryWasteRecord $wasteRecord, WasteManagementService $wasteManagementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'quarantine_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
        ]);

        $wasteManagementService->sendToQuarantine($wasteRecord, (int) $data['quarantine_location_id'], (int) $request->user()->id);

        return back()->with('success', 'Merma enviada a cuarentena.');
    }

    public function dispose(DisposeWasteRecordRequest $request, InventoryWasteRecord $wasteRecord, WasteManagementService $wasteManagementService): RedirectResponse
    {
        $this->authorizeInventory($request);

        $wasteManagementService->dispose($wasteRecord, (int) $request->user()->id, $request->validated());

        return back()->with('success', 'Merma dispuesta.');
    }

    private function formatPositionLabel(array $metadata): ?string
    {
        $positionId = $metadata['position_id'] ?? null;
        if (! $positionId) {
            return null;
        }

        $parts = ["Posición #{$positionId}"];

        if ($lpn = data_get($metadata, 'position_logistic_unit_snapshot.license_plate_number')) {
            $parts[] = $lpn;
        }

        if ($locationCode = data_get($metadata, 'position_location_snapshot.codigo')) {
            $parts[] = $locationCode;
        }

        if ($lotCode = $metadata['position_lot_code_snapshot'] ?? null) {
            $parts[] = $lotCode;
        }

        return implode(' · ', $parts);
    }
}
