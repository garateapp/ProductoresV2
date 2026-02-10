<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineDayController extends Controller
{
    use AuthorizesPlanning;

    public function show(Request $request, PackingLine $packingLine)
    {
        $this->authorizePlanning($request);

        $date = (string) $request->query('date', now()->toDateString());
        $date = Carbon::parse($date)->toDateString();

        $shiftId = $request->query('shift_id');
        $shiftId = $shiftId !== null && $shiftId !== '' ? (int) $shiftId : null;

        $shift = $shiftId ? Shift::query()->find($shiftId) : null;

        $processQuery = PackingProcess::query()
            ->with('shift')
            ->whereDate('fecha', $date);

        if ($shiftId) {
            $processQuery->where('shift_id', $shiftId);
        }

        $processes = $processQuery->orderByDesc('id')->get();
        $processIds = $processes->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        $lots = [];
        if (! empty($processIds)) {
            $lots = PackingProcessLot::query()
                ->whereIn('process_id', $processIds)
                ->where('packing_line_id', $packingLine->id)
                ->with(['packingLine', 'process.shift', 'lastPackagingChange.user'])
                ->orderByRaw('CASE WHEN inicio_estimado IS NULL THEN 1 ELSE 0 END')
                ->orderBy('inicio_estimado')
                ->orderBy('process_id')
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->values()
                ->all();
        }

        // Usamos un proceso “ancla” para compatibilidad de Show.jsx (rutas/links),
        // pero la pantalla es una vista por línea (del día) y no permite editar.
        $anchor = $processes->first();
        $printProcessId = $processes
            ->firstWhere(fn (PackingProcess $p) => ($p->estado?->value ?? (string) $p->estado) === 'CONFIRMADO')
            ?->id
            ?? $anchor?->id;

        $processLike = [
            'id' => $anchor?->id ?? 0,
            'especie' => 'VARIAS',
            'fecha' => $date,
            'shift_id' => $shift?->id ?? ($anchor?->shift_id ?? null),
            'shift' => $shift?->only(['id', 'codigo', 'nombre', 'horas', 'hora_inicio']) ?? $anchor?->shift?->only(['id', 'codigo', 'nombre', 'horas', 'hora_inicio']),
            'estado' => $anchor?->estado?->value ?? (string) ($anchor?->estado ?? 'BORRADOR'),
            'included_packing_line_ids' => [$packingLine->id],
            'updated_at' => now()->toDateTimeString(),
            'lots' => $lots,
        ];

        $lineMeta = [[
            'id' => $packingLine->id,
            'nombre' => $packingLine->nombre,
            'tipo' => $packingLine->tipo?->value ?? (string) $packingLine->tipo,
            'especie' => $packingLine->especie,
            'especies' => $packingLine->especies ?? null,
            'bins_por_hora' => null,
            'extra_horas' => 0,
            'capacidad_bins_turno' => null,
        ]];

        return Inertia::render('Planning/Processes/Show', [
            'process' => $processLike,
            'lines' => $lineMeta,
            'allLines' => $lineMeta,
            'inventory' => [],
            'inventoryFilters' => [],
            'allowSplit' => false,
            'badges' => [],
            'lineDay' => [
                'date' => $date,
                'shift_id' => $shift?->id ?? ($anchor?->shift_id ?? null),
                'shift' => $shift?->only(['id', 'codigo', 'nombre']) ?? $anchor?->shift?->only(['id', 'codigo', 'nombre']),
                'line' => [
                    'id' => $packingLine->id,
                    'nombre' => $packingLine->nombre,
                ],
                'process_ids' => $processIds,
                'print_process_id' => $printProcessId,
            ],
        ]);
    }
}

