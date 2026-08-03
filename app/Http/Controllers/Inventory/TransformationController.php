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
            'material_id' => 'required|exists:inventory_materials,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|numeric|gt:0',
            'inputs' => 'required|array|min:1',
            'inputs.*.lpn_code' => 'required|string',
            'inputs.*.consumed' => 'required|numeric|min:0',
            'inputs.*.waste' => 'numeric|min:0',
            'inputs.*.waste_reason_id' => 'nullable|exists:inventory_waste_reasons,id',
            'inputs.*.waste_type_id' => 'nullable|exists:inventory_waste_types,id',
        ]);

        ProcessTransformationJob::dispatch($data, (int) $request->user()->id);

        return back()->with('success', 'Producción enviada a procesamiento en segundo plano.');
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
