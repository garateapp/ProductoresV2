<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\Especie;
use App\Models\InventoryPackaging;
use App\Models\InventoryProduction;
use App\Models\PackingProcess;
use App\Services\Inventory\TheoreticalConsumptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request, TheoreticalConsumptionService $theoreticalConsumptionService): Response
    {
        $this->authorizeInventory($request);

        $speciesCatalog = Especie::query()
            ->with(['variedads' => fn ($query) => $query->select(['id', 'name', 'especie_id'])->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $speciesOptions = [];
        $varietiesBySpecies = [];

        foreach ($speciesCatalog as $species) {
            $name = trim((string) ($species->name ?? ''));
            if ($name === '') {
                continue;
            }

            $speciesOptions[] = [
                'value' => $name,
                'label' => $name,
            ];

            $varietiesBySpecies[$name] = collect($species->variedads ?? [])
                ->map(fn ($variety) => trim((string) ($variety->name ?? '')))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->map(fn (string $value) => [
                    'value' => $value,
                    'label' => $value,
                ])
                ->all();
        }

        $processCollection = PackingProcess::query()
            ->with(['shift:id,nombre', 'lots:id,process_id,n_variedad,variedad_original,c_embalaje,n_embalaje,packing_line_id,peso_neto', 'lots.packingLine:id,nombre'])
            ->whereIn('estado', ['CONFIRMADO', 'EN_PROCESO', 'CERRADO'])
            ->latest('fecha')
            ->latest('id')
            ->limit(250)
            ->get();

        $packagingLookup = InventoryPackaging::query()
            ->whereIn(
                'codigo',
                $processCollection->flatMap(fn (PackingProcess $process) => $process->lots->pluck('c_embalaje'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
            )
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('codigo');

        $processes = $processCollection->map(function (PackingProcess $process) use (&$speciesOptions, &$varietiesBySpecies, $packagingLookup) {
                $species = trim((string) ($process->especie ?? ''));
                $varieties = $process->lots
                    ->flatMap(fn ($lot) => [
                        trim((string) ($lot->n_variedad ?? '')),
                        trim((string) ($lot->variedad_original ?? '')),
                    ])
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($species !== '' && ! array_key_exists($species, $varietiesBySpecies)) {
                    $varietiesBySpecies[$species] = [];
                }

                if ($species !== '') {
                    $hasSpecies = collect($speciesOptions)
                        ->contains(fn (array $option) => ($option['value'] ?? null) === $species);

                    if (! $hasSpecies) {
                        $speciesOptions[] = [
                            'value' => $species,
                            'label' => $species,
                        ];
                    }
                }

                foreach ($varieties as $variety) {
                    if ($species === '') {
                        continue;
                    }

                    $exists = collect($varietiesBySpecies[$species] ?? [])
                        ->contains(fn (array $option) => ($option['value'] ?? null) === $variety);

                    if (! $exists) {
                        $varietiesBySpecies[$species][] = [
                            'value' => $variety,
                            'label' => $variety,
                        ];
                    }
                }

                $lineNames = $process->lots
                    ->map(fn ($lot) => trim((string) ($lot->packingLine?->nombre ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                $packagingCode = $process->lots
                    ->pluck('c_embalaje')
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->first();

                $packagingName = $process->lots
                    ->pluck('n_embalaje')
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->first();
                $netWeight = round((float) $process->lots->sum(fn ($lot) => (float) ($lot->peso_neto ?? 0)), 3);
                $weightsByPackaging = $process->lots
                    ->groupBy(fn ($lot) => trim((string) ($lot->c_embalaje ?? '')))
                    ->map(fn ($lots) => round((float) $lots->sum(fn ($lot) => (float) ($lot->peso_neto ?? 0)), 3))
                    ->filter(fn ($weight, $code) => $code !== '' && $weight > 0)
                    ->all();

                $matchedPackaging = $packagingCode ? $packagingLookup->get($packagingCode) : null;
                $date = optional($process->fecha)->format('Y-m-d');
                $shift = trim((string) ($process->shift?->nombre ?? ''));
                $labelParts = array_filter([
                    $date,
                    $shift,
                    $species,
                    $lineNames->first(),
                ]);

                return [
                    'id' => $process->id,
                    'label' => 'Proceso #'.$process->id.(! empty($labelParts) ? ' · '.implode(' · ', $labelParts) : ''),
                    'fecha' => $date,
                    'turno' => $shift,
                    'especie' => $species,
                    'default_variedad' => $varieties->first(),
                    'variedades' => $varieties->values()->all(),
                    'linea' => $lineNames->first(),
                    'lineas' => $lineNames->all(),
                    'default_packaging_id' => $matchedPackaging?->id,
                    'default_packaging_code' => $matchedPackaging?->codigo ?? $packagingCode,
                    'default_packaging_name' => $matchedPackaging?->nombre ?? $packagingName,
                    'net_weight' => $netWeight,
                    'weights_by_packaging' => $weightsByPackaging,
                ];
            })
            ->values();

        $productions = InventoryProduction::query()
            ->with(['packaging:id,codigo,nombre', 'creator:id,name'])
            ->latest('fecha')
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(function (InventoryProduction $production) use ($theoreticalConsumptionService) {
                $calculation = $theoreticalConsumptionService->forProduction($production);

                return [
                    'id' => $production->id,
                    'fecha' => optional($production->fecha)->format('Y-m-d'),
                    'turno' => $production->turno,
                    'linea' => $production->linea,
                    'especie' => $production->especie,
                    'variedad' => $production->variedad,
                    'packaging' => trim(($production->packaging?->codigo ?? '').' · '.($production->packaging?->nombre ?? '')),
                    'cantidad_cajas' => (float) $production->cantidad_cajas,
                    'cantidad_pallets' => (float) $production->cantidad_pallets,
                    'observacion' => $production->observacion,
                    'creator' => $production->creator?->name,
                    'process_id' => $production->referencia_tipo === 'planning_process' ? (int) $production->referencia_id : null,
                    'process_label' => $production->referencia_tipo === 'planning_process' && $production->referencia_id
                        ? 'Proceso #'.$production->referencia_id
                        : null,
                    'calculation' => $calculation,
                ];
            });

        return Inertia::render('Inventory/Productions/Index', [
            'productions' => $productions,
            'packagings' => InventoryPackaging::query()->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'peso_std', 'cantidad_cajas']),
            'processes' => $processes,
            'speciesOptions' => collect($speciesOptions)->sortBy('label')->values()->all(),
            'varietiesBySpecies' => collect($varietiesBySpecies)
                ->map(fn (array $options) => collect($options)->sortBy('label')->values()->all())
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'process_id' => ['required', 'exists:processes,id'],
            'fecha' => ['required', 'date'],
            'turno' => ['required', 'string', 'max:50'],
            'linea' => ['required', 'string', 'max:50'],
            'especie' => ['required', 'string', 'max:100'],
            'variedad' => ['nullable', 'string', 'max:100'],
            'packaging_id' => ['required', 'exists:inventory_packagings,id'],
            'cantidad_cajas' => ['required', 'numeric', 'gte:0'],
            'cantidad_pallets' => ['required', 'numeric', 'gte:0'],
            'observacion' => ['nullable', 'string'],
        ]);

        InventoryProduction::create(array_merge(Arr::except($data, ['process_id']), [
            'referencia_tipo' => 'planning_process',
            'referencia_id' => (int) $data['process_id'],
            'created_by' => (int) $request->user()->id,
        ]));

        return back()->with('success', 'Producción registrada.');
    }

    public function preview(Request $request, TheoreticalConsumptionService $theoreticalConsumptionService): JsonResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'process_id' => ['required', 'exists:processes,id'],
            'fecha' => ['required', 'date'],
            'packaging_id' => ['required', 'exists:inventory_packagings,id'],
            'cantidad_cajas' => ['required', 'numeric', 'gte:0'],
            'cantidad_pallets' => ['required', 'numeric', 'gte:0'],
        ]);

        return response()->json(
            $theoreticalConsumptionService->preview(
                (int) $data['packaging_id'],
                (string) $data['fecha'],
                (float) $data['cantidad_cajas'],
                (float) $data['cantidad_pallets'],
            )
        );
    }
}
