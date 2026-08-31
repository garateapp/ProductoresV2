<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingTunel;
use App\Services\PreCooling\ParametrizacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class TunelController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(private readonly ParametrizacionService $parametrizacion)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $tuneles = PreCoolingTunel::with('parametros')->orderBy('codigo')->get()->map(function (PreCoolingTunel $tunel) {
            return [
                'id' => $tunel->id,
                'codigo' => $tunel->codigo,
                'nombre' => $tunel->nombre,
                'tipo' => $tunel->tipo,
                'activo' => $tunel->activo,
                'tiene_cargas' => $this->tunelTieneCargas($tunel),
                'parametros' => $tunel->parametrosPorDimension(),
            ];
        });

        return Inertia::render('PreCooling/Tuneles/Index', [
            'tuneles' => $tuneles,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:pre_cooling_tuneles,codigo',
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|in:californiano,modular,evaporador_central',
            'parametros' => 'required|array',
        ]);

        $parametros = $this->parametrizacion->validar('pre_cooling_tuneles', $request->input('parametros', []));

        $tunel = PreCoolingTunel::create([...$data, 'activo' => true]);
        $this->parametrizacion->sincronizar($tunel, $parametros);

        return back()->with('success', 'Túnel creado correctamente.');
    }

    public function update(Request $request, PreCoolingTunel $tunel)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|in:californiano,modular,evaporador_central',
            'activo' => 'boolean',
        ]);

        $tunel->update($data);

        if ($this->tunelTieneCargas($tunel)) {
            return back()->with('success', 'Túnel actualizado correctamente.');
        }

        $request->validate(['parametros' => 'required|array']);
        $parametros = $this->parametrizacion->validar('pre_cooling_tuneles', $request->input('parametros', []));
        $this->parametrizacion->sincronizar($tunel, $parametros);

        return back()->with('success', 'Túnel actualizado correctamente.');
    }

    protected function tunelTieneCargas(PreCoolingTunel $tunel): bool
    {
        if (! Schema::hasTable('pre_cooling_loads')) {
            return false;
        }

        return DB::table('pre_cooling_loads')
            ->where('tunel_id', $tunel->id)
            ->whereIn('estado', ['ingresado', 'iniciado'])
            ->exists();
    }
}
