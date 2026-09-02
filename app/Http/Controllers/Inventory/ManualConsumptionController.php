<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryManualConsumption;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryTechnicalSheet;
use App\Models\User;
use App\Services\Inventory\ManualConsumptionService;
use App\Services\Inventory\SalidasFolioProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualConsumptionController extends Controller
{
    use AuthorizesInventory;

    public function __construct(
        private readonly ManualConsumptionService $service,
        private readonly SalidasFolioProvider $folioProvider,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $query = InventoryManualConsumption::query()
            ->with(['material.service', 'location', 'movement', 'details.material']);

        if ($tipo = $request->string('tipo')->trim()->toString()) {
            $query->where('tipo_accion', $tipo);
        }

        if ($estado = $request->string('estado')->trim()->toString()) {
            $query->where('estado', $estado);
        }

        $history = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryManualConsumption $row) => [
                'id' => $row->id,
                'tipo_accion' => $row->tipo_accion,
                'tipo_label' => InventoryManualConsumption::accionLabel($row->tipo_accion),
                'material_id' => $row->material_id,
                'material_codigo' => $row->material?->codigo,
                'material_nombre' => $row->material?->nombre,
                'service_name' => $row->material?->service?->name,
                'semielaborado_material_id' => $row->semielaborado_material_id,
                'details' => $row->details->map(fn ($d) => [
                    'material_id' => $d->material_id,
                    'material_codigo' => $d->material?->codigo,
                    'material_nombre' => $d->material?->nombre,
                    'cantidad' => (float) $d->cantidad,
                ])->values()->all(),
                'cantidad' => (float) $row->cantidad,
                'fecha' => $row->fecha?->toDateString(),
                'location_name' => $row->location?->nombre,
                'id_g_produccion' => $row->id_g_produccion,
                'folio_nuevo' => $row->folio_nuevo,
                'folios' => $row->folios,
                'movement_id' => $row->movement_id,
                'movement_folio' => $row->movement?->folio,
                'movement_estado' => $row->movement?->estado,
                'estado' => $row->estado,
                'detalle_estado' => $row->detalle_estado,
                'observacion' => $row->observacion,
                'created_by' => $row->creator?->name,
                'created_at' => $row->created_at?->format('d/m/Y H:i'),
            ]);

        $materials = InventoryMaterial::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'service_id', 'tipo_material']);

        $compositions = $this->semielaboradoCompositions($materials);

        return Inertia::render('Inventory/ManualConsumptions/Index', [
            'history' => $history,
            'materials' => $materials->map(fn (InventoryMaterial $m) => [
                'id' => $m->id,
                'codigo' => $m->codigo,
                'nombre' => $m->nombre,
                'tipo_material' => $m->tipo_material,
            ]),
            'semielaboradoCompositions' => $compositions,
            'locations' => InventoryLocation::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'tipo'])
                ->map(fn (InventoryLocation $l) => [
                    'id' => $l->id,
                    'codigo' => $l->codigo,
                    'nombre' => $l->nombre,
                    'tipo' => $l->tipo,
                ]),
            'filters' => [
                'tipo' => (string) $request->string('tipo'),
                'estado' => (string) $request->string('estado'),
            ],
            'tipoOptions' => collect([
                InventoryManualConsumption::TIPO_REEMBALAJE,
                InventoryManualConsumption::TIPO_REPROCESO,
                InventoryManualConsumption::TIPO_COMPLETAR_SALDOS,
            ])->map(fn (string $t) => [
                'value' => $t,
                'label' => InventoryManualConsumption::accionLabel($t),
            ])->all(),
            'origenFolios' => $this->folioProvider->latest(200)
                ->map(fn ($row) => [
                    'id_g_produccion' => (int) $row->id_g_produccion,
                    'folio' => (string) $row->folio,
                    'c_embalaje' => (string) ($row->c_embalaje ?? ''),
                    'n_embalaje' => (string) ($row->n_embalaje ?? ''),
                    'n_linea_proceso' => (string) ($row->n_linea_proceso ?? ''),
                    'n_turno' => (string) ($row->n_turno ?? ''),
                    'cantidad' => (float) ($row->cantidad ?? 0),
                    'fecha_produccion' => isset($row->fecha_produccion) ? (string) $row->fecha_produccion : null,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Devuelve el despiece (materiales componentes) del semielaborado más reciente
     * y activo de cada material de tipo semielaborado.
     *
     * @param  \Illuminate\Support\Collection<int, InventoryMaterial>  $materials
     * @return array<int, list<array<string, mixed>>>
     */
    private function semielaboradoCompositions(\Illuminate\Support\Collection $materials): array
    {
        $semielaboradoIds = $materials
            ->where('tipo_material', 'semielaborado')
            ->pluck('id')
            ->filter()
            ->all();

        if ($semielaboradoIds === []) {
            return [];
        }

        $sheets = InventoryTechnicalSheet::query()
            ->whereIn('material_id', $semielaboradoIds)
            ->where('es_semielaborado', true)
            ->where('activo', true)
            ->with(['unitItems.material', 'unitItems.replacementMaterial'])
            ->get();

        $latest = $sheets
            ->sortByDesc(fn ($sheet) => $sheet->fecha_vigencia_desde ? $sheet->fecha_vigencia_desde->timestamp : 0)
            ->groupBy('material_id')
            ->map->first();

        $result = [];

        foreach ($latest as $materialId => $sheet) {
            $result[(int) $materialId] = $sheet->unitItems
                ->map(fn ($item) => [
                    'material_id' => $item->material_id,
                    'codigo' => $item->material?->codigo,
                    'nombre' => $item->material?->nombre,
                    'replacement_material_id' => $item->replacement_material_id,
                    'replacement_codigo' => $item->replacementMaterial?->codigo,
                    'replacement_nombre' => $item->replacementMaterial?->nombre,
                    'cantidad_estandar' => (float) $item->cantidad_estandar,
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInventory($request);

        $data = $request->validate([
            'tipo_accion' => ['required', 'in:reembalaje,reproceso,completar_saldos'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.material_id' => ['required', 'integer', 'exists:inventory_materials,id'],
            'materials.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'semielaborado_material_id' => ['nullable', 'integer', 'exists:inventory_materials,id'],
            'fecha' => ['required', 'date'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'linea' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
            'id_g_produccion' => ['nullable', 'integer'],
            'folios' => ['nullable', 'array'],
            'folios.*' => ['nullable', 'integer'],
            'folio_nuevo' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = $this->systemUserId();
        $row = $this->service->create($data, $userId, true);

        if ($row->estado === 'aplicado') {
            return back()->with('success', 'Consumo manual registrado correctamente.');
        }

        return back()->with('error', 'El consumo quedó en borrador: '.($row->detalle_estado ?? 'Sin detalle.'));
    }

    public function retry(Request $request, InventoryManualConsumption $consumption): RedirectResponse
    {
        $this->authorizeInventory($request);

        $row = $this->service->retry($consumption, $this->systemUserId());

        if ($row->estado === 'aplicado') {
            return back()->with('success', 'Consumo manual aplicado correctamente.');
        }

        return back()->with('error', 'El consumo sigue en borrador: '.($row->detalle_estado ?? 'Sin detalle.'));
    }

    private function systemUserId(): int
    {
        $user = auth()->user();

        if ($user) {
            return (int) $user->id;
        }

        $system = User::query()->where('email', config('services.termo.auto_consumption.system_user_email', 'sistema.auto@appgreenex.test'))->first();

        return $system ? (int) $system->id : 1;
    }
}
