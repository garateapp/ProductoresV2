<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Models\EstimationBiweeklyRow;
use App\Models\EstimationBiweeklyVersion;
use App\Services\EstimationBiweeklyImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EstimationBiweeklyRowController extends Controller
{
    use EnsuresEstimationsAccess;

    public function update(Request $request, EstimationBiweeklyVersion $estimation_biweekly_version, EstimationBiweeklyRow $estimation_biweekly_row, EstimationBiweeklyImportService $importService): Response|RedirectResponse
    {
        $this->ensureEstimationsAccess($request);

        if ($estimation_biweekly_row->estimation_biweekly_version_id !== $estimation_biweekly_version->id) {
            abort(404);
        }

        $data = $request->validate([
            'row_id' => ['required', 'integer'],
            'row' => ['required', 'array'],
            'row.producer_id' => ['required', 'exists:users,id'],
            'row.agronomist_id' => ['nullable', 'exists:users,id'],
            'row.variedad_id' => ['required', 'exists:variedads,id'],
            'row.planta' => ['nullable', 'string', 'max:120'],
            'row.sucursal' => ['required', 'string', 'max:120'],
            'row.csg' => ['nullable', 'string', 'max:64'],
            'row.especie' => ['nullable', 'string', 'max:80'],
            'row.tipo' => ['nullable', 'string', 'max:80'],
            'row.acopio' => ['required', 'boolean'],
            'row.mexico' => ['nullable', 'boolean'],
            'row.dia' => ['required', 'date'],
            'row.semana' => ['required', 'integer', 'min:1', 'max:53'],
            'row.total_kilo' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ((int) $data['row_id'] !== $estimation_biweekly_row->id) {
            abort(422, 'Row id mismatch.');
        }

        $version = $importService->applyManualUpdate($estimation_biweekly_version, $data, $request->user());

        if ($request->expectsJson()) {
            return response([
                'status' => 'ok',
                'version_id' => $version->id,
            ]);
        }

        return redirect()
            ->route('estimations.biweekly.show', $version)
            ->with('success', 'Cambios guardados.');
    }
}
