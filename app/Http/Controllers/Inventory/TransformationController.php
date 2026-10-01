<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTransformationJob;
use App\Services\Inventory\TransformationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransformationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'technical_sheet_id' => 'required|exists:inventory_technical_sheets,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|numeric|gt:0',
            'inputs' => 'required|array|min:1',
            'inputs.*.lpn_code' => 'required|string',
            'inputs.*.consumed' => 'required|numeric|min:0',
            'inputs.*.wastes' => 'nullable|array',
            'inputs.*.wastes.*.quantity' => 'required|numeric|min:0',
            'inputs.*.wastes.*.waste_reason_id' => 'nullable|integer|exists:inventory_waste_reasons,id',
            'inputs.*.wastes.*.waste_type_id' => 'nullable|integer|exists:inventory_waste_types,id',
        ]);

        $data = $this->normalizePayload($data);

        ProcessTransformationJob::dispatch($data, (int) $request->user()->id);

        return back()->with('success', 'Producción enviada a procesamiento en segundo plano.');
    }

    /**
     * El formulario envía cadenas vacías cuando un merma aún no tiene motivo o tipo,
     * y `exists` las rechazaría. Se convierten en null para que la validación sea coherente.
     */
    private function normalizePayload(array $data): array
    {
        $data['inputs'] = collect($data['inputs'] ?? [])
            ->map(function (array $input): array {
                $input['lpn_code'] = trim((string) ($input['lpn_code'] ?? ''));
                $input['consumed'] = (float) ($input['consumed'] ?? 0);

                $input['wastes'] = collect($input['wastes'] ?? [])
                    ->map(fn ($waste) => [
                        'quantity' => (float) ($waste['quantity'] ?? 0),
                        'waste_reason_id' => $this->toNullableId($waste['waste_reason_id'] ?? null),
                        'waste_type_id' => $this->toNullableId($waste['waste_type_id'] ?? null),
                    ])
                    ->filter(fn (array $waste) => $waste['quantity'] > 0)
                    ->values()
                    ->all();

                return $input;
            })
            ->values()
            ->all();

        return $data;
    }

    private function toNullableId($value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }

    public function checkAvailability(Request $request, TransformationService $transformationService)
    {
        $data = $request->validate([
            'technical_sheet_id' => 'required|exists:inventory_technical_sheets,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        try {
            $availability = $transformationService->validateAvailability(
                (int) $data['technical_sheet_id'],
                (float) $data['quantity'],
                (int) $data['location_id']
            );

            return response()->json([
                'success' => true,
                'availability' => $availability
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }
    }
}
