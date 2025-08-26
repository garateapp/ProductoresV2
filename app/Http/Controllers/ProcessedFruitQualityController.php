<?php

namespace App\Http\Controllers;

use App\Models\Proceso;
use App\Models\Parametro;
use App\Models\Valor;
use App\Models\PhotoType;
use App\Models\ProcessedFruitQuality;
use App\Models\ProcessedFruitQualityDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ProcessedFruitQualityController extends Controller
{
    public function index(Request $request)
    {
        $query = Proceso::query()->orderBy('fecha', 'desc');

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_proceso', 'like', '%' . $searchTerm . '%')
                  ->orWhere('variedad', 'like', '%' . $searchTerm . '%')
                  ->orWhere('especie', 'like', '%' . $searchTerm . '%');
            });
        }

        $procesos = $query->with(['processedFruitQualities.details', 'processedFruitQualities.photos.photoType'])->paginate(10);

                        $parametros = Parametro::with('valors')->get();

        $photoTypes = PhotoType::all();

        return Inertia::render('ProcessedFruitQuality/Index', [
            'procesos' => $procesos,
            'filters' => $request->only(['search']),
            'parametros' => $parametros,
            'photoTypes' => $photoTypes,
        ]);
    }

    public function storeQuality(Request $request)
    {
        $validated = $request->validate([
            'proceso_id' => 'required|exists:procesos,id',
            'numero_de_caja' => 'nullable|string|max:255',
            't_muestra' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'responsable' => 'nullable|string',
            'materia_vegetal' => 'boolean',
            'piedras' => 'boolean',
            'barro' => 'boolean',
            'pedicelo_largo' => 'boolean',
            'racimo' => 'boolean',
            'esponjas' => 'boolean',
            'h_esponjas' => 'nullable|string',
            'llenado_tottes' => 'nullable|string',
            'embalaje' => 'nullable|integer',
            'obs_ext' => 'nullable|string',
        ]);

        // Convert booleans to 'SI'/'NO' strings
        foreach (['materia_vegetal', 'piedras', 'barro', 'pedicelo_largo', 'racimo', 'esponjas'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = $validated[$field] ? 'SI' : 'NO';
            }
        }

        $quality = ProcessedFruitQuality::create($validated);

        return redirect()->back()->with('quality_id', $quality->id)->with('success', 'Calidad de proceso guardada exitosamente.');
    }

    public function storeDetail(Request $request)
    {
        $validated = $request->validate([
            'processed_fruit_quality_id' => 'required|exists:processed_fruit_qualities,id',
            'parametro_id' => 'required|exists:parametros,id',
            'valor_id' => 'required|exists:valors,id',
            'cantidad_muestra' => 'nullable|integer',
            'exportable' => 'boolean',
            'temperatura' => 'nullable',
            'valor_presion' => 'nullable|numeric',
        ]);

        $quality = ProcessedFruitQuality::find($validated['processed_fruit_quality_id']);
        $parametro = Parametro::find($validated['parametro_id']);
        $valor = Valor::find($validated['valor_id']);

        $porcMuestra = ($quality->t_muestra > 0 && $validated['cantidad_muestra'] !== null) ? ($validated['cantidad_muestra'] / $quality->t_muestra * 100) : 0;

        $categoria = $validated['exportable'] ? 'Exportable' : null;

        $detailData = [
            'processed_fruit_quality_id' => $validated['processed_fruit_quality_id'],
            'parametro_id' => $validated['parametro_id'],
            'valor_id' => $validated['valor_id'],
            'cantidad_muestra' => $validated['cantidad_muestra'],
            'porcentaje_muestra' => $porcMuestra,
            'categoria' => $categoria,
            'temperatura' => $validated['temperatura'] ?? null,
            'valor_ss' => $validated['valor_presion'] ?? null,
            'tipo_item' => $parametro->nombre,
            'detalle_item' => $valor->nombre,
            'tipo_detalle' => $parametro->tipo_detalle ?? null, // Assuming parametro has tipo_detalle
        ];

        $detail = ProcessedFruitQualityDetail::updateOrCreate(
            [
                'processed_fruit_quality_id' => $validated['processed_fruit_quality_id'],
                'parametro_id' => $validated['parametro_id'],
                'valor_id' => $validated['valor_id'],
            ],
            $detailData
        );

        return redirect()->back()->with('success', 'Detalle de calidad guardado exitosamente.');
    }

    public function getQuality(Proceso $proceso, Request $request)
    {
        $qualityId = $request->query('quality_id');
        $quality = null;

        if ($qualityId) {
            $quality = ProcessedFruitQuality::where('proceso_id', $proceso->id)
                                            ->where('id', $qualityId)
                                            ->with('photos.photoType')
                                            ->first();
        } else {
            // If no specific quality_id is provided, return null for a new evaluation
            $quality = null;
        }
        return response()->json($quality);
    }

    public function getDetails(Proceso $proceso, Request $request)
    {
        $qualityId = $request->query('quality_id');
        $quality = null;

        if ($qualityId) {
            $quality = ProcessedFruitQuality::where('proceso_id', $proceso->id)
                                            ->where('id', $qualityId)
                                            ->first();
        } else {
            // If no specific quality_id is provided, return null
            $quality = null;
        }

        if (!$quality) {
            return response()->json(['detalles' => [], 'defectos' => [], 'desordenFisiologico' => [], 'curvaCalibre' => [], 'indiceMadurez' => []]);
        }

        $detalles = $quality->details()->with(['parametro', 'valor'])->get();

        // Categorize details based on parametro_id, similar to ControlCalidadController
        $defecto_param_ids = [3, 4, 5];
        $desorden_param_ids = [11, 12];
        $curva_param_ids = [1, 2, 6];
        $madurez_param_ids = [7, 8, 9, 10, 13, 14, 15, 16, 17, 18];

        $defectos = $detalles->filter(function($detalle) use ($defecto_param_ids) {
            return in_array($detalle->parametro_id, $defecto_param_ids);
        });
        $desordenFisiologico = $detalles->filter(function($detalle) use ($desorden_param_ids) {
            return in_array($detalle->parametro_id, $desorden_param_ids);
        });
        $curvaCalibre = $detalles->filter(function($detalle) use ($curva_param_ids) {
            return in_array($detalle->parametro_id, $curva_param_ids);
        });
        $indiceMadurez = $detalles->filter(function($detalle) use ($madurez_param_ids) {
            return in_array($detalle->parametro_id, $madurez_param_ids);
        });

        return response()->json([
            'detalles' => $detalles->values(), // All details
            'defectos' => $defectos->values(),
            'desordenFisiologico' => $desordenFisiologico->values(),
            'curvaCalibre' => $curvaCalibre->values(),
            'indiceMadurez' => $indiceMadurez->values(),
        ]);
    }
}
