<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingCamara;
use App\Services\PreCooling\ParametrizacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CamaraController extends Controller
{
    use AuthorizesPreCooling;

    public function __construct(private readonly ParametrizacionService $parametrizacion)
    {
    }

    public function index(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $camaras = PreCoolingCamara::with('parametros')->orderBy('codigo')->get()->map(function (PreCoolingCamara $camara) {
            return [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
                'tipo' => $camara->tipo,
                'activo' => $camara->activo,
                'tiene_saldos' => $this->camaraTieneSaldos($camara),
                'parametros' => $camara->parametrosPorDimension(),
            ];
        });

        return Inertia::render('PreCooling/Camaras/Index', [
            'camaras' => $camaras,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:pre_cooling_camaras,codigo',
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|in:rackeable,planta_libre',
            'parametros' => 'required|array',
        ]);

        $parametros = $this->parametrizacion->validar('pre_cooling_camaras', $request->input('parametros', []));

        $camara = PreCoolingCamara::create([...$data, 'activo' => true]);
        $this->parametrizacion->sincronizar($camara, $parametros);

        return back()->with('success', 'Cámara creada correctamente.');
    }

    public function update(Request $request, PreCoolingCamara $camara)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|in:rackeable,planta_libre',
            'activo' => 'boolean',
        ]);

        $camara->update($data);

        if ($this->camaraTieneSaldos($camara)) {
            return back()->with('success', 'Cámara actualizada correctamente.');
        }

        $request->validate(['parametros' => 'required|array']);
        $parametros = $this->parametrizacion->validar('pre_cooling_camaras', $request->input('parametros', []));
        $this->parametrizacion->sincronizar($camara, $parametros);

        return back()->with('success', 'Cámara actualizada correctamente.');
    }

    protected function camaraTieneSaldos(PreCoolingCamara $camara): bool
    {
        if (! Schema::hasTable('pre_cooling_saldos')) {
            return false;
        }

        return DB::table('pre_cooling_saldos')
            ->where('camara_id', $camara->id)
            ->exists();
    }
}
