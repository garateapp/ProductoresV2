<?php

namespace App\Http\Controllers;

use App\Exports\ProcessedFruitQualityExport;
use App\Models\Especie;
use App\Models\Parametro;
use App\Models\PhotoType;
use App\Models\Proceso;
use App\Models\ProcessedFruitQuality;
use App\Models\ProcessedFruitQualityDetail;
use App\Models\Variedad;
use App\Models\Valor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Log;

class ProcessedFruitQualityController extends Controller
{
    public function index(Request $request)
    {
        $query = Proceso::query()->orderBy('fecha', 'desc');

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_proceso', 'like', '%'.$searchTerm.'%')
                    ->orWhere('variedad', 'like', '%'.$searchTerm.'%')
                    ->orWhere('especie', 'like', '%'.$searchTerm.'%');
            });
        }

        // Species and variety filters
        $variedades = collect();
        if ($request->filled('especie_id')) {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('especie', $especie->name);
                $variedades = $especie->variedads;
            }
        }
        if ($request->filled('variedad_id')) {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('variedad', $variedad->name);
            }
        }

        $procesos = $query->with(['processedFruitQualities.details', 'processedFruitQualities.photos.photoType'])->paginate(10);

        $parametros = Parametro::with('valors')->get();

        $photoTypes = PhotoType::all();

        $especies = Especie::all();

        return Inertia::render('ProcessedFruitQuality/Index', [
            'procesos' => $procesos,
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'parametros' => $parametros,
            'photoTypes' => $photoTypes,
            'especies' => $especies->toArray(),
            'variedades' => $variedades,
        ]);
    }

    public function storeQuality(Request $request)
    {
        $validated = $request->validate([
            'proceso_id' => 'required|exists:procesos,id',
            'numero_de_caja' => 'nullable|string|max:255',
            'numero_embaladora_mano' => 'nullable|string|max:255',
            'peso_exacto_caja' => 'nullable|numeric',
            'codigo_embalaje' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'tolerance_label' => 'nullable|in:1S,1-2,3,4',
            'destino' => 'nullable|string|max:255',
            'calibre' => 'nullable|string|max:255',
            'color_cubrimiento' => 'nullable|string|max:255',
            'color_fondo' => 'nullable|string|max:255',
            't_muestra' => 'nullable|integer',
            'observaciones' => 'nullable|string',
            'responsable' => 'nullable|string',
            'estado' => 'nullable|in:Aprobada,Rechazada',
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

    public function updateToleranceLabel(ProcessedFruitQuality $quality, Request $request)
    {
        $data = $request->validate([
            'tolerance_label' => 'required|in:1S,1-2,3,4',
        ]);
        $quality->tolerance_label = $data['tolerance_label'];
        $quality->save();
        return response()->json(['status' => 'ok']);
    }

    public function updateStatus(ProcessedFruitQuality $quality, Request $request)
    {
        $data = $request->validate([
            'estado' => 'required|in:Aprobada,Rechazada',
        ]);
        $quality->estado = $data['estado'];
        $quality->save();
        return response()->json(['status' => 'ok', 'estado' => $quality->estado]);
    }

    public function storeDetail(Request $request)
    {
        $madurez_param_ids = [7, 8, 9, 10, 13, 14, 15, 16, 17, 18];

        $baseRules = [
            'processed_fruit_quality_id' => 'required|exists:processed_fruit_qualities,id',
            'parametro_id' => 'required|exists:parametros,id',
            'cantidad_muestra' => 'nullable|integer',
            'exportable' => 'boolean',
            'temperatura' => 'nullable',
            'valor_presion' => 'nullable|numeric',
        ];

        if (in_array((int) $request->input('parametro_id'), $madurez_param_ids, true)) {
            $baseRules['valor_text'] = 'nullable|string';
        } else {
            $baseRules['valor_id'] = 'required|exists:valors,id';
        }

        $validated = $request->validate($baseRules);

        $quality = ProcessedFruitQuality::find($validated['processed_fruit_quality_id']);
        $parametro = Parametro::find($validated['parametro_id']);

        $valorName = null;
        $valorId = null;
        if (in_array((int) $validated['parametro_id'], $madurez_param_ids, true)) {
            $valorName = isset($validated['valor_text']) && trim((string) $validated['valor_text']) !== ''
                ? trim((string) $validated['valor_text'])
                : null;
        } else {
            $valor = Valor::find($validated['valor_id']);
            $valorId = $valor?->id;
            $valorName = $valor?->nombre;
        }

        $porcMuestra = ($quality && $quality->t_muestra > 0 && array_key_exists('cantidad_muestra', $validated) && $validated['cantidad_muestra'] !== null)
            ? ($validated['cantidad_muestra'] / $quality->t_muestra * 100)
            : 0;

        $categoria = !empty($validated['exportable']) ? 'Exportable' : null;

        $detailData = [
            'processed_fruit_quality_id' => $validated['processed_fruit_quality_id'],
            'parametro_id' => $validated['parametro_id'],
            'valor_id' => $valorId,
            'cantidad_muestra' => $validated['cantidad_muestra'] ?? null,
            'porcentaje_muestra' => $porcMuestra,
            'categoria' => $categoria,
            'temperatura' => $validated['temperatura'] ?? null,
            'valor_ss' => $validated['valor_presion'] ?? null,
            'tipo_item' => $parametro?->nombre,
            'detalle_item' => $valorName,
            'tipo_detalle' => $parametro?->tipo_detalle ?? null,
        ];

        $match = [
            'processed_fruit_quality_id' => $validated['processed_fruit_quality_id'],
            'parametro_id' => $validated['parametro_id'],
        ];
        if ($valorId !== null) {
            $match['valor_id'] = $valorId;
        } elseif ($valorName !== null) {
            // Ensure we don't overwrite other free-text entries for the same parametro
            $match['detalle_item'] = $valorName;
        }

        ProcessedFruitQualityDetail::updateOrCreate($match, $detailData);

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
            // If no specific ID provided, return the latest quality for this process if exists
            $quality = ProcessedFruitQuality::where('proceso_id', $proceso->id)
                ->with('photos.photoType')
                ->latest()
                ->first();
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

        if (! $quality) {
            return response()->json(['detalles' => [], 'defectos' => [], 'desordenFisiologico' => [], 'curvaCalibre' => [], 'indiceMadurez' => []]);
        }

        $detalles = $quality->details()->with(['parametro', 'valor'])->get();

        // Categorize details based on parametro_id, similar to ControlCalidadController
        $defecto_param_ids = [3, 4, 5];
        $desorden_param_ids = [11, 12];
        $curva_param_ids = [1, 2, 6];
        $madurez_param_ids = [7, 8, 9, 10, 13, 14, 15, 16, 17, 18];

        $defectos = $detalles->filter(function ($detalle) use ($defecto_param_ids) {
            return in_array($detalle->parametro_id, $defecto_param_ids);
        });
        $desordenFisiologico = $detalles->filter(function ($detalle) use ($desorden_param_ids) {
            return in_array($detalle->parametro_id, $desorden_param_ids);
        });
        $curvaCalibre = $detalles->filter(function ($detalle) use ($curva_param_ids) {
            return in_array($detalle->parametro_id, $curva_param_ids);
        });
        $indiceMadurez = $detalles->filter(function ($detalle) use ($madurez_param_ids) {
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

    public function export(Request $request)
    {
        $query = ProcessedFruitQuality::with('proceso');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $items = $query->get();

        return Excel::download(new ProcessedFruitQualityExport($items), 'processed-fruit-quality.xlsx');
    }

    public function previewReport(Proceso $proceso)
    {
        $proceso->load(['processedFruitQualities.details', 'processedFruitQualities.photos.photoType']);

        return view('reports.processed_fruit_quality_report', [
            'proceso' => $proceso,
        ]);
    }

    public function generateReport(Proceso $proceso)
    {
        $proceso->load(['processedFruitQualities.details', 'processedFruitQualities.photos.photoType']);

        $html = view('reports.processed_fruit_quality_report', compact('proceso'))->render();

        try {
            $pdfRelative = 'reporte_proceso_' . $proceso->n_proceso . '.pdf';
            $pdfPath = storage_path('app/public/' . $pdfRelative);
            $tmpDir = storage_path('app/browsershot-temp');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }

            $shot =  Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                ->setOption('headless', true)
                ->noSandbox()
                ->addChromiumArguments([
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--font-render-hinting=none',
                    '--headless=new',
                ])
                ->waitUntilNetworkIdle()
                ->wait(15)
                ->setViewport(1920, 1080)
                ->landscape(false)
                ->showBackground();

            // Allow overriding paths via env (both our custom and Browsershot's names)
            $chromePath = env('CHROME_PATH') ?: env('BROWSERSHOT_CHROME_PATH');
            if (! empty($chromePath)) {
                $shot->setOption('executablePath', $chromePath);
            }
            $nodePath = env('NODE_PATH') ?: env('BROWSERSHOT_NODE_PATH');
            if (! empty($nodePath)) {
                $shot->setNodeBinary($nodePath);
            }
            $npmPath = env('NPM_PATH') ?: env('BROWSERSHOT_NPM_PATH');
            if (! empty($npmPath)) {
                $shot->setNpmBinary($npmPath);
            }

            $shot->savePdf($pdfPath);

            // Optional: allow overriding Chrome path via .env
            // if (! empty(env('CHROME_PATH'))) {
            //     $shot->setOption('executablePath', env('CHROME_PATH'));
            // }
            // if (! empty(env('NODE_PATH'))) {
            //     $shot->setNodeBinary(env('NODE_PATH'));
            // }
            // if (! empty(env('NPM_PATH'))) {
            //     $shot->setNpmBinary(env('NPM_PATH'));
            // }

            // $shot->savePdf($pdfPath);

            // Opcional: guardar URL pública en proceso->informe
            $proceso->informe = asset('storage/' . $pdfRelative);
            $proceso->save();

            return response()->file($pdfPath);
        } catch (\Exception $e) {
            Log::error('Browsershot (Proceso) error: ' . $e->getMessage());
            throw $e;
        }
    }
}
