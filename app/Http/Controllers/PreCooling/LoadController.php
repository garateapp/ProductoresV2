<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingLoad;
use App\Models\PreCoolingLoadFolio;
use App\Services\PreCooling\FolioProvider;
use App\Services\PreCooling\LoadService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class LoadController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(
        private readonly LoadService $loadService,
        private readonly FolioProvider $folioProvider,
    ) {
    }

    public function store(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'tunel_id' => 'required|integer|exists:pre_cooling_tuneles,id',
            'tipo_proceso_id' => 'required|integer|exists:pre_cooling_tipos_procesos,id',
            'fecha_hora_inicio' => 'required|date',
            'camara_destino_id' => 'nullable|integer|exists:pre_cooling_camaras,id',
            'temperatura_objetivo' => 'nullable|numeric|between:-50,100',
            'atributos' => 'nullable|array',
        ]);

        $this->loadService->crearCarga(
            (int) $data['tunel_id'],
            (int) $data['tipo_proceso_id'],
            $data['fecha_hora_inicio'],
            $request->user(),
            $data['camara_destino_id'] ? (int) $data['camara_destino_id'] : null,
            $data['temperatura_objetivo'] ?? null,
            $data['atributos'] ?? null,
        );

        return back()->with('success', 'Proceso creado correctamente.');
    }

    public function update(Request $request, PreCoolingLoad $load)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $raw = $request->all();
        $data = [];
        foreach ([
            'tipo_proceso_id' => 'nullable|integer|exists:pre_cooling_tipos_procesos,id',
            'camara_destino_id' => 'nullable|integer|exists:pre_cooling_camaras,id',
            'fecha_hora_inicio' => 'nullable|date',
            'fecha_hora_inversion' => 'nullable|date',
            'fecha_hora_termino' => 'nullable|date',
            'fecha_hora_descarga' => 'nullable|date',
            'temperatura_objetivo' => 'nullable|numeric|between:-50,100',
            'atributos' => 'nullable|array',
        ] as $campo => $regla) {
            if (array_key_exists($campo, $raw)) {
                $valor = $raw[$campo];
                if (is_string($valor) && $valor === '') {
                    $valor = null;
                }
                if ($valor !== null) {
                    Validator::validate([$campo => $valor], [$campo => $regla]);
                }
                $data[$campo] = $valor;
            }
        }

        $this->loadService->actualizarCarga($load->id, $data, $request->user());

        return back()->with('success', 'Proceso actualizado correctamente.');
    }

    public function lookupFolio(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'folio' => 'required|string|max:50',
        ]);

        $result = $this->folioProvider->buscar($data['folio']);

        if (! $result) {
            return response()->json(['found' => false, 'folio' => $data['folio']]);
        }

        return response()->json(['found' => true, 'data' => $result]);
    }

    public function storeFolio(Request $request, PreCoolingLoad $load)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'folio' => 'required|string|max:50',
            'banda' => 'required|string|max:50',
            'posicion' => 'required|string|max:50',
            'altura' => 'required|string|max:50',
            'nivel' => 'required|string|max:50',
            'cajas' => 'nullable|integer|min:0',
            'pallets' => 'nullable|integer|min:0',
            'temperatura_inicial' => 'nullable|numeric|between:-50,100',
            'especie' => 'nullable|string|max:255',
            'variedad' => 'nullable|string|max:255',
            'productor' => 'nullable|string|max:255',
            'exportadora' => 'nullable|string|max:255',
            'embalaje' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'calibre' => 'nullable|string|max:100',
            'atributos' => 'nullable|array',
        ]);

        $this->loadService->agregarFolio(
            $load->id,
            $data['folio'],
            $data['nivel'],
            $data['banda'],
            $data['posicion'],
            $data['altura'],
            $data,
            $request->user(),
        );

        return back()->with('success', 'Folio asignado a la celda.');
    }

    public function destroyFolio(Request $request, PreCoolingLoad $load, PreCoolingLoadFolio $folio)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $this->loadService->quitarFolio($load->id, $folio->id, $request->user());

        return back()->with('success', 'Folio retirado del proceso.');
    }

    public function iniciar(Request $request, PreCoolingLoad $load)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'temperatura_ambiente_inicio' => 'nullable|numeric|between:-50,100',
            'temperaturas_folios' => 'nullable|array',
            'temperaturas_folios.*.temperatura_inicio' => 'nullable|numeric|between:-50,100',
        ]);

        $this->loadService->iniciar(
            $load->id,
            $data['temperatura_ambiente_inicio'] ?? null,
            $data['temperaturas_folios'] ?? [],
            $request->user(),
        );

        return back()->with('success', 'Pre-enfriado iniciado.');
    }

    public function registrarInversion(Request $request, PreCoolingLoad $load)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'fecha_hora_inversion' => 'required|date',
            'temperatura_ambiente_inversion' => 'nullable|numeric|between:-50,100',
            'temperaturas_folios' => 'nullable|array',
            'temperaturas_folios.*.temperatura_inversion_interior' => 'nullable|numeric|between:-50,100',
            'temperaturas_folios.*.temperatura_inversion_exterior' => 'nullable|numeric|between:-50,100',
        ]);

        $this->loadService->registrarInversion(
            $load->id,
            $data['fecha_hora_inversion'],
            $data['temperatura_ambiente_inversion'] ?? null,
            $data['temperaturas_folios'] ?? [],
            $request->user(),
        );

        return back()->with('success', 'Inversión del flujo registrada.');
    }

    public function salir(Request $request, PreCoolingLoad $load)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.loads.manage');

        $data = $request->validate([
            'fecha_hora_fin' => 'nullable|date',
            'camara_id' => 'required|integer|exists:pre_cooling_camaras,id',
            'ubicaciones' => 'required|array|min:1',
            'ubicaciones.*.banda' => 'required|string|max:50',
            'ubicaciones.*.fila' => 'required|string|max:50',
            'ubicaciones.*.columna' => 'required|string|max:50',
            'ubicaciones.*.altura' => 'required|string|max:50',
            'ubicaciones.*.nivel' => 'required|string|max:50',
            'temperatura_ambiente_final' => 'nullable|numeric|between:-50,100',
            'temperaturas_folios' => 'nullable|array',
            'temperaturas_folios.*.temperatura_final_interna' => 'nullable|numeric|between:-50,100',
            'temperaturas_folios.*.temperatura_final_externa' => 'nullable|numeric|between:-50,100',
        ]);

        $this->loadService->salir(
            $load->id,
            $data['fecha_hora_fin'] ?? null,
            (int) $data['camara_id'],
            $data['ubicaciones'],
            $data['temperatura_ambiente_final'] ?? null,
            $data['temperaturas_folios'] ?? [],
            $request->user(),
        );

        return back()->with('success', 'Carga marcada como salida del túnel.');
    }
}
