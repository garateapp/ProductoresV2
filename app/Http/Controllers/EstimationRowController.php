<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresEstimationsAccess;
use App\Models\EstimationRow;
use App\Models\EstimationVersion;
use App\Models\EstimationWeek;
use App\Services\EstimationImportService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class EstimationRowController extends Controller
{
    use EnsuresEstimationsAccess;

    public function update(Request $request, EstimationVersion $estimation_version, EstimationRow $estimation_row, EstimationImportService $importService): Response|RedirectResponse
    {
        $this->ensureEstimationsAccess($request);

        if ($estimation_row->estimation_version_id !== $estimation_version->id) {
            abort(404);
        }

        $data = $request->validate([
            'row_id' => ['required', 'integer'],
            'row' => ['required', 'array'],
            'row.grupo' => ['nullable', 'string', 'max:191'],
            'row.tipo_productor' => ['nullable', 'string', 'max:80'],
            'row.producer_id' => ['required', 'exists:users,id'],
            'row.agronomist_id' => ['nullable', 'exists:users,id'],
            'row.status_id' => ['required', 'exists:estimation_statuses,id'],
            'row.variedad_id' => ['required', 'exists:variedads,id'],
            'row.variedad_rotulada' => ['nullable', 'string', 'max:191'],
            'row.planta' => ['nullable', 'string', 'max:120'],
            'row.mexico' => ['nullable', 'boolean'],
            'row.acopio' => ['required', 'boolean'],
            'row.radio_mosca' => ['required', 'boolean'],
            'row.corea_greenex' => ['nullable', 'boolean'],
            'row.tipo_cereza' => ['nullable', 'string', 'max:60'],
            'row.total_kilo' => ['nullable', 'numeric', 'min:0'],
            'weeks' => ['nullable', 'array'],
            'weeks.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ((int) $data['row_id'] !== $estimation_row->id) {
            abort(422, 'Row id mismatch.');
        }

        $weekNumbers = array_map('intval', array_keys($data['weeks'] ?? []));
        if (! empty($weekNumbers)) {
            $validWeeks = EstimationWeek::where('season_id', $estimation_version->season_id)
                ->whereIn('week_number', $weekNumbers)
                ->pluck('week_number')
                ->all();
            $missing = array_values(array_diff($weekNumbers, $validWeeks));
            if (! empty($missing)) {
                abort(422, 'Semanas no registradas en la temporada: '.implode(', ', $missing));
            }
        }

        $version = $importService->applyManualUpdate($estimation_version, $data, $request->user());

        if ($request->expectsJson()) {
            return response([
                'status' => 'ok',
                'version_id' => $version->id,
            ]);
        }

        return redirect()
            ->route('estimations.show', $version)
            ->with('success', 'Cambios guardados.');
    }
}
