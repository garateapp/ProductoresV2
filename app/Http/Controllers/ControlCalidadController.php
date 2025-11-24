<?php


namespace App\Http\Controllers;

use App\Models\Calidad;
use App\Models\Detalle;
use App\Models\Especie;
use App\Models\Parametro;
use App\Models\PhotoType;
use App\Models\Recepcion;
use App\Models\Service;
use App\Models\Valor;
use App\Models\Variedad;
use App\Mail\ReceptionReportPreview;
use App\Services\QualityChartsService;
use App\Services\ReportNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;

class ControlCalidadController extends Controller
{

    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (! empty($user->idprod)) {
            $isProducer = true;
        }

        $query = Recepcion::query()->orderBy('fecha_g_recepcion', 'desc');

        if ($isProducer) {
            $query->where('n_emisor', $user->name);
            $producerEspeciesNames = $user->especies()->pluck('name')->toArray();
            $query->whereIn('n_especie', $producerEspeciesNames);
        }

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_variedad', 'like', '%'.$searchTerm.'%')
                    ->orWhere('n_especie', 'like', '%'.$searchTerm.'%');
            });
        }

        $variedades = collect();

        if ($request->has('especie_id') && $request->input('especie_id') !== '') {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('n_especie', $especie->name);
                $variedades = $especie->variedads;
            }
        }

        if ($request->has('variedad_id') && $request->input('variedad_id') !== '') {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('n_variedad', $variedad->name);
            }
        }

        $totalRecepciones = $query->count();
        $totalKilos = (int) $query->sum('peso_neto');

        // Eager load calidad and its photos for the main recepciones list
        $recepciones = $query->with(['calidad.photos.photoType'])->paginate(10)->withQueryString();

        $especies = Especie::all();

        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
        }

        $parametros = Parametro::all();
        $photoTypes = PhotoType::all();

        return Inertia::render('ControlCalidad/Index', [
            'recepciones' => $recepciones,
            'especies' => $especies->toArray(),
            'variedades' => $variedades,
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'isProducer' => $isProducer,
            'totalRecepciones' => $totalRecepciones,
            'totalKilos' => $totalKilos,
            'parametros' => $parametros,
            'photoTypes' => $photoTypes,
        ]);
    }

    public function getValores(Request $request)
    {
        $valores = Valor::where('parametro_id', $request->parametro_id)
            ->where('especie', $request->especie)
            ->get();

        return response()->json($valores);
    }

    public function storeCalidad(Request $request)
    {

        $validated = $request->validate([
            'recepcion_id' => 'required|exists:recepcions,id',
            't_muestra' => 'nullable|integer',
            'materia_vegetal' => 'boolean',
            'piedras' => 'boolean',
            'barro' => 'boolean',
            'pedicelo_largo' => 'boolean',
            'racimo' => 'boolean',
            'esponjas' => 'boolean',
            'h_esponjas' => 'nullable|string',
            'llenado_tottes' => 'nullable|string',
            'embalaje' => 'nullable|integer',
            'seteo_termo' => 'nullable|string|max:255',
            'nota_calidad' => 'nullable|numeric',
            'obs_ext' => 'nullable|string',
        ]);

        foreach (['materia_vegetal', 'piedras', 'barro', 'pedicelo_largo', 'racimo', 'esponjas'] as $field) {
            $validated[$field] = ! empty($validated[$field]) ? 'SI' : 'NO';
        }

        $t_muestra = $validated['t_muestra'] ?? 100;
        $validated['t_muestra'] = $t_muestra;

        $recepcionId = $validated['recepcion_id'];
        $nota_calidad = $validated['nota_calidad'] ?? null;
        unset($validated['nota_calidad']);

        $calidad = Calidad::updateOrCreate(
            ['recepcion_id' => $recepcionId],
            $validated
        );


        $recepcion = Recepcion::find($recepcionId);
        if ($recepcion) {
            $recepcion->nota_calidad = ($nota_calidad === '' || $nota_calidad === null) ? null : $nota_calidad;
            DB::connection('sqlsrv')
    ->table('PKG_G_Recepcion')
    ->where('numero_i', $recepcion->numero_g_recepcion)
    ->update(['nota_calidad' => $nota_calidad]);
            $recepcion->save();
        }

        // Revert to redirect()->back() with flash data
        return redirect()->back()->with('calidad_id', $calidad->id)->with('success', 'Condiciones de llegada guardadas exitosamente.');
    }

    public function storeDetalle(Request $request)
    {
        Log::info('Request all:', $request->all()); // Keep this for overall view

        // Add specific logging for calidad_id
        if ($request->has('calidad_id')) {
            Log::info('calidad_id received:', ['value' => $request->input('calidad_id'), 'type' => gettype($request->input('calidad_id'))]);
        } else {
            Log::info('calidad_id not present in request.');
        }
        $validated = $request->validate([
            'calidad_id' => 'required|exists:calidads,id',
            'parametro_id' => 'required|exists:parametros,id',
            'valor_id' => 'nullable|exists:valors,id',
            'cantidad_muestra' => 'nullable|integer',
            'exportable' => 'boolean',
            'temperatura' => 'nullable',
            'valor_presion' => 'nullable|numeric',
            'obs_ext' => 'nullable|string',
        ]);

        $parametro = Parametro::find($validated['parametro_id']);
        $valor = $validated['valor_id'] ? Valor::find($validated['valor_id']) : null;


        if (in_array($validated['parametro_id'], ['1', '2', '3', '4', '5', '6'])) {
            $tipo_detalle = 'cc';
        } else {
            $tipo_detalle = 'ss';
        }
        $calidad = Calidad::find($validated['calidad_id']);
        $calidad->obs_ext = $validated['obs_ext'] ?? null;
        $calidad->save();

        $cantidadMuestra = isset($validated['cantidad_muestra']) ? (int) $validated['cantidad_muestra'] : 0;
        $valorPresion = isset($validated['valor_presion']) ? (float) $validated['valor_presion'] : null;
        $temperatura = $validated['temperatura'] ?? null;
        $t_muestra = $calidad->t_muestra ?: 100;
        if ($t_muestra <= 0) {
            $t_muestra = 100;
        }

        $porcMuestra = $cantidadMuestra ? round(($cantidadMuestra / $t_muestra) * 100, 2) : 0;
        $categoria = $request->boolean('exportable') ? 'Exportable' : null;

        $detalleData = [
            'calidad_id' => $validated['calidad_id'],
            'porcentaje_muestra' => $porcMuestra,
            'valor_ss' => $valorPresion,
            'cantidad' => $cantidadMuestra,
            'tipo_detalle' => $tipo_detalle,
            'temperatura' => $temperatura,
            'categoria' => $categoria,
        ];

        $detalleItem = $valor?->name;
        if (! $detalleItem) {
            return redirect()->back()->withErrors(['valor_id' => 'Debe seleccionar un valor para el parámetro elegido.']);
        }

        $detalle = Detalle::updateOrCreate(
            [
                'calidad_id' => $validated['calidad_id'],
                'tipo_item' => $parametro->name,
                'detalle_item' => $detalleItem,
                'tipo_detalle' => $tipo_detalle,
                'porcentaje_muestra' => $porcMuestra,
                'valor_ss' => $validated['valor_presion'],
                'categoria' => $categoria,

            ],
            $detalleData
        );

        $detalles = Detalle::where('calidad_id', $validated['calidad_id'])->with(['parametro', 'valor'])->get();
        $defecto_param_names = Parametro::whereIn('id', [3, 4, 5])->pluck('name')->toArray();
        $desorden_param_names = Parametro::whereIn('id', [11, 12])->pluck('name')->toArray();
        $curva_param_names = Parametro::whereIn('id', [1, 2, 6])->pluck('name')->toArray();
        $madurez_param_names = Parametro::whereIn('id', [7, 8, 9, 10, 13, 14, 15, 16, 17, 18])->pluck('name')->toArray();

        $defectos = $detalles->whereIn('tipo_item', $defecto_param_names);
        $desordenFisiologico = $detalles->whereIn('tipo_item', $desorden_param_names);
        $curvaCalibre = $detalles->whereIn('tipo_item', $curva_param_names);
        $indiceMadurez = $detalles->whereIn('tipo_item', $madurez_param_names);

        return redirect()->back()->with('success', 'Detalle guardado exitosamente.');
    }

    public function destroyDetalle(Detalle $detalle)
    {
        $detalle->delete();

        return response()->json(['message' => 'Detalle eliminado exitosamente.']);
    }

    public function getDetalles(Recepcion $recepcion)
    {
        $calidad = $recepcion->calidad;

        if ($calidad) {
            $calidad->loadMissing('photos.photoType');
        }

        if (! $calidad) {
            return response()->json(['defectos' => [], 'desordenFisiologico' => []]);
        }

        $detalles = $calidad->detalles()->with(['parametro', 'valor'])->get();

        $defecto_param_names = Parametro::whereIn('id', [3, 4, 5])->pluck('name')->toArray();
        $desorden_param_names = Parametro::whereIn('id', [11, 12])->pluck('name')->toArray();
        $curva_param_names = Parametro::whereIn('id', [1, 2, 6])->pluck('name')->toArray();
        $madurez_param_names = Parametro::whereIn('id', [7, 8, 9, 10, 13, 14, 15, 16, 17, 18])->pluck('name')->toArray();

        $defectos = $detalles->whereIn('tipo_item', $defecto_param_names);
        $desordenFisiologico = $detalles->whereIn('tipo_item', $desorden_param_names);
        $curvaCalibre = $detalles->whereIn('tipo_item', $curva_param_names);
        $indiceMadurez = $detalles->whereIn('tipo_item', $madurez_param_names);

        $hasFirmproDetails = $calidad->detalles()->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->exists();

        return response()->json([
            'defectos' => $defectos->values(),
            'desordenFisiologico' => $desordenFisiologico->values(),
            'curvaCalibre' => $curvaCalibre->values(),
            'indiceMadurez' => $indiceMadurez->values(),
            'hasFirmproDetails' => $hasFirmproDetails,
        ]);
    }

    public function getCalidad(Recepcion $recepcion)
    {
        if (! $recepcion->calidad) {
            return response()->json(null);
        }

        $calidad = Calidad::with('photos.photoType')->find($recepcion->calidad->id);

        return response()->json($calidad);
    }

    public function validateKilos(Recepcion $recepcion)
    {
        try {
            $remote = DB::connection('sqlsrv')
                ->table('V_PKG_Recepcion_FG')
                ->selectRaw('SUM(COALESCE(peso_neto, 0)) as total_peso_neto')
                ->where('numero_g_recepcion', $recepcion->numero_g_recepcion)
                ->groupBy('numero_g_recepcion')
                ->first();

            $localKilos = (int) ($recepcion->peso_neto ?? 0);
            $sourceKilos = $remote ? (int) ($remote->total_peso_neto ?? 0) : null;

            return response()->json([
                'found' => (bool) $remote,
                'needs_sync' => $remote ? $sourceKilos !== $localKilos : false,
                'source_kilos' => $sourceKilos,
                'local_kilos' => $localKilos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error validating reception kilos', [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'validation_failed'], 500);
        }
    }

    public function cargarFirmpro(Recepcion $recepcion)
    {

        $calidad = $recepcion->calidad;
        $embalaje='';
        $fecha=date('Y-m-d H:i:s');

        if (!$calidad) {
            return response()->json(['message' => 'No se encontró registro de calidad para esta recepción.'], 404);
        }

        try{
       Detalle::where('calidad_id', $calidad->id)
            ->whereIn('tipo_item', [
                'FIRMEZAS',
                'DISTRIBUCIÓN DE FIRMEZA',
                'DISTRIBUCIÓN DE CALIBRES',
                'COLOR DE CUBRIMIENTO'
            ])
            ->delete();
            Log::info("borrado");
        }
        catch (\Throwable $e) {
            Log::error($e->getMessage());
        }
        $firmpro1 = Http::post('https://api.greenexweb.cl/api/BuscarRecepcionCloud?filter[numero_recepcion][eq]='.$recepcion->numero_g_recepcion);

        $firmpro1 = $firmpro1->json();

        $categories = [];
        $series = [];
        if ($recepcion->n_variedad == 'Dagen') {
            $rangos = [279, 219, 179, 1, 11, 12];
        } else {
            $rangos = [279, 219, 179, 1];
        }

        $l = [];
        $d = [];
        $b = [];

        foreach ($rangos as $rango) {
            $nfrutos = 0;
            $nfrutostot = 0;
            $nfirmeza = 0;
            $sumt = 0;
            $light = 0;
            $dark = 0;
            $black = 0;
            $tlight = 0;
            $tdark = 0;
            $tblack = 0;

            foreach ($firmpro1 as $items) {
                $n = 1;

                foreach ($items as $item) {

                    if ($n == 4) {
                        $firmeza = $item;
                    }
                    if ($n == 5) {
                        $calibre = $item;
                    }
                    if ($n == 13) {
                        $color = $item;
                    }
                    if ($n == 14) {

                        if ($color == 'Rojo') {
                            $tlight += 1;
                        }
                        if ($color == 'Rojo caoba') {
                            $tdark += 1;
                        }
                        if ($color == 'Santina') {
                            $tdark += 1;
                        }
                        if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                            $tblack += 1;
                        }
                        if ($color == 'Negro') {
                            $tblack += 1;
                        }

                        if ($rango == 279) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre < 28) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {
                                if ($firmeza >= 280) {
                                    if ($color == 'Rojo') {
                                        $light += 1;
                                    }
                                    if ($color == 'Rojo caoba') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Santina') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                                        $black += 1;
                                    }
                                    if ($color == 'Negro') {
                                        $black += 1;
                                    }
                                }

                            }

                        }
                        if ($rango == 219) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre >= 28 && $calibre < 30) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {
                                if ($firmeza >= 200 && $firmeza < 280) {
                                    if ($color == 'Rojo') {
                                        $light += 1;
                                    }
                                    if ($color == 'Rojo caoba') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Santina') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                                        $black += 1;
                                    }
                                    if ($color == 'Negro') {
                                        $black += 1;
                                    }
                                }
                            }
                        }
                        if ($rango == 179) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre >= 30 && $calibre < 33) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {
                                if ($firmeza >= 180 && $firmeza < 200) {
                                    if ($color == 'Rojo') {
                                        $light += 1;
                                    }
                                    if ($color == 'Rojo caoba') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Santina') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                                        $black += 1;
                                    }
                                    if ($color == 'Negro') {
                                        $black += 1;
                                    }
                                }
                            }
                        }
                        if ($rango == 1) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre >= 33 && $calibre < 36) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {
                                if ($firmeza >= 1 && $firmeza < 180) {
                                    if ($color == 'Rojo') {
                                        $light += 1;
                                    }
                                    if ($color == 'Rojo caoba') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Santina') {
                                        $dark += 1;
                                    }
                                    if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                                        $black += 1;
                                    }
                                    if ($color == 'Negro') {
                                        $black += 1;
                                    }
                                }
                            }
                        }
                        if ($rango == 11) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre >= 36 && $calibre < 39) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {

                            }
                        }
                        if ($rango == 12) {
                            if ($recepcion->n_variedad == 'Dagen') {
                                if ($calibre >= 39) {
                                    $sumt += $firmeza;
                                    $nfrutos += 1;
                                }
                            } else {

                            }
                        }
                    }
                    $n += 1;
                }
                $nfrutostot += 1;
            }
            if ($recepcion->n_variedad == 'Dagen') {
                if ($sumt > 0 && $nfrutos > 0) {

                    if ($rango == 279) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => 'PRECALIBRE',
                            'fecha' => Carbon::now(),
                        ]);
                    }
                    if ($rango == 219) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => 'L',
                            'fecha' => Carbon::now(),
                        ]);

                    }
                    if ($rango == 179) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => 'XL',
                            'fecha' => Carbon::now(),
                        ]);
                    }
                    if ($rango == 1) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => 'J',
                            'fecha' => Carbon::now(),
                        ]);
                    }
                    if ($rango == 11) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => '2J',
                            'fecha' => Carbon::now(),
                        ]);
                    }
                    if ($rango == 12) {
                        Detalle::create([
                            'calidad_id' => $calidad->id,
                            'embalaje' => $calidad->embalaje ?? 1,
                            'valor_ss' => $sumt / $nfrutos,
                            'porcentaje_muestra' => $sumt / $nfrutos,
                            'tipo_item' => 'FIRMEZAS',
                            'tipo_detalle' => 'ss',
                            'detalle_item' => '3J',
                            'fecha' => Carbon::now(),
                        ]);
                    }
                }

            } else {

                if ($tlight > 0) {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => $light * 100 / $tlight,
                        'porcentaje_muestra' => $light * 100 / $tlight,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'LIGHT',
                        'fecha' => Carbon::now(),
                    ]);
                    // $l[]=$light*100/$tlight;
                } else {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => 0,
                        'porcentaje_muestra' => 0,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'LIGHT',
                        'fecha' => Carbon::now(),
                    ]);
                }

                if ($tdark > 0) {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => $dark * 100 / $tdark,
                        'porcentaje_muestra' => $dark * 100 / $tdark,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'DARK',
                        'fecha' => Carbon::now(),
                    ]);
                    // $d[]=$dark*100/$tdark;
                } else {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => 0,
                        'porcentaje_muestra' => 0,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'DARK',
                        'fecha' => Carbon::now(),
                    ]);
                }

                if ($tblack > 0) {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => $black * 100 / $tblack,
                        'porcentaje_muestra' => $black * 100 / $tblack,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'BLACK',
                        'fecha' => Carbon::now(),
                    ]);
                    // $b[]=$black*100/$tblack;
                } else {
                    Detalle::create([
                        'calidad_id' => $calidad->id,
                        'embalaje' => $calidad->embalaje ?? 1,
                        'valor_ss' => 0,
                        'porcentaje_muestra' => 0,
                        'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle' => 'cc',
                        'detalle_item' => 'BLACK',
                        'fecha' => Carbon::now(),
                    ]);
                }
            }
        }
        // consulta para distribución de calibres
        $this->calibres = Http::post('https://api.greenexweb.cl/api/BuscarRecepcionCloudConsolidado?filter[numero_recepcion][eq]='.$recepcion->numero_g_recepcion);
        $this->calibres = $this->calibres->json();

        foreach ($this->calibres as $items) {
            $n = 1;
            foreach ($items as $item) {
                if ($n == 5) {
                    $cantidad_frutos = $item;
                }

                if ($recepcion->n_variedad == 'Dagen') {
                    if ($n == 24) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'PRECALIBRE',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 25) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'L',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 26) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'XL',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 27) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 28) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '2J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 29) {
                        if ($item > 0) {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '3J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 30) {
                        if ($item > 0) {

                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'SOBRECALIBRE',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                } else {
                    if ($n == 14) {
                        if ($item == 0) {

                        } else {

                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'FIRMEZAS',
                                'tipo_detalle' => 'ss',
                                'detalle_item' => 'FRUTA BLANDA',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 24) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'PRECALIBRE',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 25) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'L',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 26) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'XL',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 27) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => 'J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 28) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '2J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 29) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '3J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 30) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '4J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }
                    if ($n == 31) {
                        if ($item == 0) {

                        } else {
                            Detalle::create([
                                'calidad_id' => $recepcion->calidad->id,
                                'embalaje' => $embalaje,
                                'valor_ss' => floatval($item) * 100,
                                'porcentaje_muestra' => floatval($item) * 100,
                                'tipo_item' => 'DISTRIBUCIÓN DE CALIBRES',
                                'tipo_detalle' => 'cc',
                                'detalle_item' => '5J',
                                'fecha' => $fecha,
                            ]);
                        }
                    }

                }

                $n += 1;

            }
            break;

        }

        // consulta para distribución de color
        $this->firmpro = Http::post('https://api.greenexweb.cl/api/BuscarRecepcionCloud?filter[numero_recepcion][eq]='.$recepcion->numero_g_recepcion);
        $this->firmpro = $this->firmpro->json();
        $subpromedio_light = 0;
        $subpromedio_dark = 0;
        $subpromedio_black = 0;
        $subpromedio_light2 = 0;
        $subpromedio_dark2 = 0;
        $subpromedio_black2 = 0;
        $rojo = 0;
        $rojocaoba = 0;
        $santina = 0;
        $caobaoscuro = 0;
        $negro = 0;
        $fueradecolor = 0;
        $totalfrutos = 0;
        // dagen
        $mblando = 0;
        $blando = 0;
        $sensitivo = 0;
        $firme = 0;
        $mfirme = 0;
        foreach ($this->firmpro as $items) {
            $totalfrutos += 1;
            $n = 1;

            // CADA REGISTRO:
            foreach ($items as $item) {
                if ($n == 4) {
                    $firmeza = $item;

                }
                if ($n == 5) {
                    $calibre = $item;
                }
                if ($n == 13) {
                    $color = $item;
                    if ($color == 'Fuera color') {
                        $fueradecolor += 1;
                    }
                    if ($color == 'Rojo') {
                        $rojo += 1;
                        $subpromedio_light += $firmeza;
                        $subpromedio_light2 += $calibre;
                    }
                    if ($color == 'Rojo caoba') {
                        $rojocaoba += 1;
                        $subpromedio_dark += $firmeza;
                        $subpromedio_dark2 += $calibre;
                    }
                    if ($color == 'Santina') {
                        $santina += 1;
                        $subpromedio_dark += $firmeza;
                        $subpromedio_dark2 += $calibre;
                    }
                    if ($color == 'Caoba oscuro' || $color == 'Caoba Oscuro') {
                        $caobaoscuro += 1;
                        $subpromedio_black += $firmeza;
                        $subpromedio_black2 += $calibre;
                    }
                    if ($color == 'Negro') {
                        $negro += 1;
                        $subpromedio_black += $firmeza;
                        $subpromedio_black2 += $calibre;
                    }
                    if ($firmeza < 250) {
                        $mblando += 1;
                    }
                    if ($firmeza >= 250 && $firmeza < 400) {
                        $blando += 1;
                    }
                    if ($firmeza >= 400 && $firmeza < 600) {
                        $sensitivo += 1;
                    }
                    if ($firmeza >= 600 && $firmeza < 950) {
                        $firme += 1;
                    }
                    if ($firmeza >= 950) {
                        $mfirme += 1;
                    }

                }
                $n += 1;
            }
            if ($totalfrutos >= $cantidad_frutos) {
                break;
            }

        }

        if ($fueradecolor > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $fueradecolor * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'Fuera de Color',
                'fecha' => $fecha,
            ]);
        }
        if ($rojo > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $rojo * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'ROJO',
                'fecha' => $fecha,
            ]);
        }
        if ($rojocaoba > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $rojocaoba * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'ROJO CAOBA',
                'fecha' => $fecha,
            ]);
        }
        if ($santina > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $santina * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'SANTINA',
                'fecha' => $fecha,
            ]);
        }
        if ($caobaoscuro > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $caobaoscuro * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'CAOBA OSCURO',
                'fecha' => $fecha,
            ]);
        }
        if ($negro > 0) {
            Detalle::create([
                'calidad_id' => $recepcion->calidad->id,
                'embalaje' => $embalaje,
                'valor_ss' => $negro * 100 / $totalfrutos,
                'tipo_item' => 'COLOR DE CUBRIMIENTO',
                'tipo_detalle' => 'cc',
                'detalle_item' => 'NEGRO',
                'fecha' => $fecha,
            ]);
        }

        if ($recepcion->n_variedad == 'Dagen') {
            if ($mblando > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $mblando * 100 / $cantidad_frutos,
                    'porcentaje_muestra' => $mblando * 100 / $cantidad_frutos,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'MUY BLANDO',
                    'fecha' => $fecha,
                ]);
            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'porcentaje_muestra' => 0,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'MUY BLANDO',
                    'fecha' => $fecha,
                ]);
            }
            if ($blando > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $blando * 100 / $cantidad_frutos,
                    'porcentaje_muestra' => $blando * 100 / $cantidad_frutos,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'BLANDO',
                    'fecha' => $fecha,
                ]);
            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'porcentaje_muestra' => 0,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'BLANDO',
                    'fecha' => $fecha,
                ]);
            }

            if ($sensitivo > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $sensitivo * 100 / $cantidad_frutos,
                    'porcentaje_muestra' => $sensitivo * 100 / $cantidad_frutos,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'SENSIBLE',
                    'fecha' => $fecha,
                ]);
            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'porcentaje_muestra' => 0,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'SENSITIVO',
                    'fecha' => $fecha,
                ]);
            }

            if ($firme > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $firme * 100 / $cantidad_frutos,
                    'porcentaje_muestra' => $firme * 100 / $cantidad_frutos,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'FIRME',
                    'fecha' => $fecha,
                ]);
            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'porcentaje_muestra' => 0,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'FIRME',
                    'fecha' => $fecha,
                ]);
            }

            if ($mfirme > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $mfirme * 100 / $cantidad_frutos,
                    'porcentaje_muestra' => $mfirme * 100 / $cantidad_frutos,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'MUY FIRME',
                    'fecha' => $fecha,
                ]);
            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'porcentaje_muestra' => 0,
                    'tipo_item' => 'DISTRIBUCIÓN DE FIRMEZA',
                    'tipo_detalle' => 'cc',
                    'detalle_item' => 'MUY FIRME',
                    'fecha' => $fecha,
                ]);
            }

        } else {

            if ($rojo > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $subpromedio_light / $rojo,
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'LIGHT',
                    'fecha' => $fecha,
                ]);

            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'LIGHT',
                    'fecha' => $fecha]);
            }

            if (($rojocaoba + $santina) > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $subpromedio_dark / ($rojocaoba + $santina),
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'DARK',
                    'fecha' => $fecha,
                ]);

            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'DARK',
                    'fecha' => $fecha,
                ]);
            }

            if (($negro + $caobaoscuro) > 0) {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => $subpromedio_black / ($negro + $caobaoscuro),
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'BLACK',
                    'fecha' => $fecha,
                ]);

            } else {
                Detalle::create([
                    'calidad_id' => $recepcion->calidad->id,
                    'embalaje' => $embalaje,
                    'valor_ss' => 0,
                    'tipo_item' => 'FIRMEZAS',
                    'tipo_detalle' => 'ss',
                    'detalle_item' => 'BLACK',
                    'fecha' => $fecha,
                ]);

            }
        }

        return response()->json(['message' => 'Firmpro data loaded successfully.']);
    }

    public function generateReport(Recepcion $recepcion)
    {
        $html = view('reports.reception_report', $this->buildPreviewReportViewData($recepcion))->render();

        try {
            $pdfRelative = 'reporte_recepcion_' . $recepcion->numero_g_recepcion . '.pdf';
            $pdfPath = storage_path('app/public/' . $pdfRelative);
            $tmpDir = storage_path('app/browsershot-temp');
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            $chrome = env('BROWSERSHOT_CHROME_PATH', '/home/forge/.cache/puppeteer/chrome/linux-139.0.7258.138/chrome-linux64/chrome');
            if(config('app.env') === 'local') {


            Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                //  ->setChromePath($chrome)
                //  ->setOption('executablePath', $chrome)
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
                ->showBackground()
                ->savePdf($pdfPath);
                } else {
                    Browsershot::html($html)
                    ->setTemporaryDirectory($tmpDir)
                     ->setChromePath($chrome)
                     ->setOption('executablePath', $chrome)
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
                    ->showBackground()
                    ->savePdf($pdfPath);
                }
            $recepcion->informe = asset('storage/' . $pdfRelative);
            $recepcion->save();

            return response()->file($pdfPath);
        } catch (\Exception $e) {
            Log::error('Generate report error: ' . $e->getMessage());
            throw $e;

        }
    }

public function previewReport(Recepcion $recepcion)
    {
        return view('reports.reception_report', $this->buildPreviewReportViewData($recepcion, true));
    }

public function previewPage(Recepcion $recepcion)
    {
        return Inertia::render('ControlCalidad/Preview', [
            'recepcionId' => $recepcion->id,
            'numero' => $recepcion->numero_g_recepcion,
            'approved' => (bool) $recepcion->informe,
            'informeUrl' => $recepcion->informe,
            'htmlUrl' => route('control-calidad.preview-report-html', $recepcion->id),
            'approveUrl' => route('control-calidad.approve-report', $recepcion->id),
            'generateUrl' => route('control-calidad.generate-report', $recepcion->id),
            'resendUrl' => route('control-calidad.resend-report', $recepcion->id),
            'sendPreviewUrl' => route('control-calidad.send-preview', $recepcion->id),
            'sendPreviewWhatsappUrl' => route('control-calidad.send-preview-whatsapp', $recepcion->id),
        ]);
    }

    private function buildPreviewReportViewData(Recepcion $recepcion, bool $isPreview = false): array
    {
        $recepcion->loadMissing([
            'calidad.photos.photoType',
        ]);

        $calidad = $recepcion->calidad;
        $temperatura_pulpa = null;
        $porcentaje_exportable = 100;
        $defectos_calidad_sum = 0;
        $defectos_condicion_sum = 0;
        $danos_plaga_sum = 0;
        $exporterName = 'Greenex SpA';
        $seteo_termo = 'N/A';

        if (!empty($recepcion->n_emisor)) {
            $service = Service::query()
                ->whereHas('users', function ($query) use ($recepcion) {
                    $query->where('name', $recepcion->n_emisor);

                    if (! empty($recepcion->id_emisor)) {
                        $query->orWhere('idprod', $recepcion->id_emisor);
                    }
                })
                ->with('owner')
                ->first();

            if ($service && $service->owner ) {
                $exporterName = $service->name;
            }

            Log::Info("recepcion ",[$recepcion]);




        }

        if ($calidad) {
            $seteo_termo = $calidad->seteo_termo ?? 'N/A';
            $temperatura_pulpa_detalle = $calidad->detalles()->where('tipo_item', 'SOLIDOS SOLUBLES')->first();
            //dd($temperatura_pulpa_detalle);
            if ($temperatura_pulpa_detalle) {
                $temperatura_pulpa = $temperatura_pulpa_detalle->temperatura;
            }

            $defectos_calidad_sum = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CALIDAD')
                ->sum('porcentaje_muestra');
            $defectos_condicion_sum = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CONDICION')
                ->sum('porcentaje_muestra');
            $danos_plaga_sum = $calidad->detalles()
                ->where('tipo_item', 'DAÑO DE PLAGA')
                ->sum('porcentaje_muestra');

             $defectos_calidad_precalibre = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CALIDAD')
                ->where('detalle_item', 'PRECALIBRE')
                ->sum('porcentaje_muestra');
            $defectos_calidad_sum=$defectos_calidad_sum-$defectos_calidad_precalibre;

            $total_defectos_sum = $defectos_calidad_sum + $defectos_condicion_sum + $danos_plaga_sum+$defectos_calidad_precalibre;
            $porcentaje_exportable = max(0, 100 - $total_defectos_sum);
        }

        $receptions = collect([$recepcion]);
        $sizeDistribution = QualityChartsService::getSizeDistributionData($receptions);
        $averageFirmness = QualityChartsService::getPromedioFirmezasData($receptions);
        $firmnessDistribution = QualityChartsService::getDistribucionFirmezasData($receptions);

        $solubleSolids = QualityChartsService::getSolidosSolublesData($receptions);
        $coverageColor = QualityChartsService::getColorCubrimientoData($receptions);

        $tabulatedIds = [6955, 8557, 8558, 8559, 8560, 8561, 8563, 8564, 8630, 8657, 8665, 8666, 8683,8899];
        $shouldTabulateCharts = in_array((int) $recepcion->id_emisor, $tabulatedIds, true);

        $html_tabla_distribucion_calibre = '';
        $html_tabla_color = '';
        $html_tabla_firmeza_grande = '';
        $html_tabla_firmeza_mediana = '';
        $html_tabla_firmeza_pequena = '';
        $html_tabla_color_fondo = '';
        $html_tabla_calibrix = '';
        $html_tabla_porc_firmeza = '';
        $html_tabla_porcentaje_firmeza = '';

        if ($shouldTabulateCharts) {

 if ($recepcion->calidad->detalles){
                  $categories_distribucion_calibre = [];
        $series_distribucion_calibre = [];
        $cantidad_distribucion_calibre = 0;

                foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES') as $detalle){

                    if ($recepcion->n_especie == 'Cherries') {
                        $cantidad_distribucion_calibre += $detalle->cantidad;
                    } else {
                        $cantidad_distribucion_calibre += $detalle->cantidad;
                    }
                }

                foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES') as $detalle){

                    $categories_distribucion_calibre[] = $detalle->detalle_item;
                    if ($recepcion->n_especie == 'Cherries') {
                        $series_distribucion_calibre[] = $detalle->valor_ss;
                    } else {
                        if ($cantidad_distribucion_calibre > 0) {
                            $series_distribucion_calibre[] = ($detalle->porcentaje_muestra * 100) / $cantidad_distribucion_calibre;
                        } else {
                            $series_distribucion_calibre[] = $detalle->porcentaje_muestra;
                        }
                    }
                }

    $html_tabla_distribucion_calibre = '<table border="1" cellpadding="5" cellspacing="0">';
    $html_tabla_distribucion_calibre .= '<thead><tr><th>Calibre</th><th>Valor</th></tr></thead>';
    $html_tabla_distribucion_calibre .= '<tbody>';

    foreach ($categories_distribucion_calibre as $index => $categoria) {
        $valor = $series_distribucion_calibre[$index] ?? '';
        $html_tabla_distribucion_calibre .= '<tr>';
        $html_tabla_distribucion_calibre .= '<td>' . htmlspecialchars($categoria) . '</td>';
        $html_tabla_distribucion_calibre .= '<td>' . round($valor, 2) . '</td>';
        $html_tabla_distribucion_calibre .= '</tr>';
    }

    $html_tabla_distribucion_calibre .= '</tbody></table>';

    $series_color = [];
        foreach ($recepcion->calidad->detalles->where('tipo_item', 'COLOR DE CUBRIMIENTO') as $detalle) {
                $name_color = $detalle->detalle_item;
                if ($recepcion->n_especie == 'Cherries') {
                    $series_color[] = ['name' => $name_color, 'y' => $detalle->valor_ss];
                } else {
                    $series_color[] = ['name' => $name_color, 'y' => $detalle->porcentaje_muestra];
                }

        }

        $html_tabla_color='<table border="1" cellpadding="5" cellspacing="0">';
        $html_tabla_color.='<thead><tr><th>Color</th><th>Valor</th></tr></thead>';
        $html_tabla_color.='<tbody>';
        foreach ($series_color as $serie) {
            $html_tabla_color.='<tr>';
            $html_tabla_color.='<td>'.$serie['name'].'</td>';
            $html_tabla_color.='<td>'.$serie['y'].'</td>';
            $html_tabla_color.='</tr>';
        }
        $html_tabla_color.='</tbody></table>';

        if ($recepcion->calidad->detalles->where('tipo_item','COLOR DE FONDO')->count()) {
            $distribucion_color_fondo=$this->generarGrafico($recepcion->id,'color/fondo','color_fondo',440,460);

            foreach ($recepcion->calidad->detalles->where('tipo_item', 'COLOR DE FONDO') as $detalle) {
                //$categories[]=$detalle->detalle_item;
                //$series[]=$detalle->porcentaje_muestra;
                $name_color_fondo = $detalle->detalle_item;

                $series_color_fondo[] = ['name' => $name_color_fondo, 'y' => $detalle->porcentaje_muestra];
            }
            $html_tabla_color_fondo='<table border="1" cellpadding="5" cellspacing="0">';
            $html_tabla_color_fondo.='<thead><tr><th>Color</th><th>Valor</th></tr></thead>';
            $html_tabla_color_fondo.='<tbody>';
            foreach ($series_color_fondo as $serie) {
                $html_tabla_color_fondo.='<tr>';
                $html_tabla_color_fondo.='<td>'.$serie['name'].'</td>';
                $html_tabla_color_fondo.='<td>'.$serie['y'].'</td>';
                $html_tabla_color_fondo.='</tr>';
            }
            $html_tabla_color_fondo.='</tbody></table>';
        }

             $html_tabla_firmeza_grande='';
        $html_tabla_firmeza_mediana='';
        $html_tabla_firmeza_pequena='';
        if ($recepcion->n_especie!='Orange' || $recepcion->n_especie=="Cherries") {
                   $categories_firmeza_grande = [];
                   $series_firmeza_grande = [];
                   $categories_firmeza_mediana = [];
                   $series_firmeza_mediana = [];
                   $categories_firmeza_pequena = [];
                   $series_firmeza_pequena = [];


            if ($recepcion->calidad->detalles){
                foreach ($recepcion->calidad->detalles->where('tipo_item', 'GRANDE') as $detalle){
                        $categories_firmeza_grande[] = $detalle->detalle_item;
                        $series_firmeza_grande[] = ['name' => $detalle->detalle_item, 'y' => $detalle->valor_ss];
                }
            }

            if ($recepcion->calidad->detalles){
                foreach ($recepcion->calidad->detalles->where('tipo_item', 'MEDIANO') as $detalle){
                        $categories_firmeza_mediana[] = $detalle->detalle_item;
                        $series_firmeza_mediana[] = ['name' => $detalle->detalle_item, 'y' => $detalle->valor_ss];
                }
            }

            if ($recepcion->calidad->detalles){
                foreach ($recepcion->calidad->detalles->where('tipo_item', 'CHICO') as $detalle){
                        $categories_firmeza_pequena[] = $detalle->detalle_item;
                        $series_firmeza_pequena[] = ['name' => $detalle->detalle_item, 'y' => $detalle->valor_ss];
                }
            }

            $html_tabla_firmeza_grande='<table border="1" cellpadding="5" cellspacing="0">';
            $html_tabla_firmeza_grande.='<thead><tr><th>Calibre</th><th>Valor</th></tr></thead>';
            $html_tabla_firmeza_grande.='<tbody>';
            foreach ($series_firmeza_grande as $serie) {
                $html_tabla_firmeza_grande.='<tr>';
                $html_tabla_firmeza_grande.='<td>'.$serie['name'].'</td>';
                $html_tabla_firmeza_grande.='<td>'.$serie['y'].'</td>';
                $html_tabla_firmeza_grande.='</tr>';
            }
            $html_tabla_firmeza_grande.='</tbody></table>';
            $html_tabla_firmeza_mediana='<table border="1" cellpadding="5" cellspacing="0">';
            $html_tabla_firmeza_mediana.='<thead><tr><th>Calibre</th><th>Valor</th></tr></thead>';
            $html_tabla_firmeza_mediana.='<tbody>';
            foreach ($series_firmeza_mediana as $serie) {
                $html_tabla_firmeza_mediana.='<tr>';
                $html_tabla_firmeza_mediana.='<td>'.$serie['name'].'</td>';
                $html_tabla_firmeza_mediana.='<td>'.$serie['y'].'</td>';
                $html_tabla_firmeza_mediana.='</tr>';
            }
            $html_tabla_firmeza_mediana.='</tbody></table>';

            $html_tabla_firmeza_pequena='<table border="1" cellpadding="5" cellspacing="0">';
            $html_tabla_firmeza_pequena.='<thead><tr><th>Calibre</th><th>Valor</th></tr></thead>';
            $html_tabla_firmeza_pequena.='<tbody>';
            foreach ($series_firmeza_pequena as $serie) {
                $html_tabla_firmeza_pequena.='<tr>';
                $html_tabla_firmeza_pequena.='<td>'.$serie['name'].'</td>';
                $html_tabla_firmeza_pequena.='<td>'.$serie['y'].'</td>';
                $html_tabla_firmeza_pequena.='</tr>';
            }
            $html_tabla_firmeza_pequena.='</tbody></table>';
            $categories_porc_firmeza=[];
            $series_porc_firmeza=[];
             if ($recepcion->n_variedad == 'Dagen'){
            foreach ($recepcion->calidad->detalles->where('tipo_item', 'FIRMEZAS') as $detalle){

                    $categories_porc_firmeza[] = $detalle->detalle_item;
                    if ($recepcion->n_especie == 'Cherries') {
                        $series_porc_firmeza[] = $detalle->valor_ss;
                    } else {
                        $series_porc_firmeza[] = $detalle->porcentaje_muestra;
                    }


                }
            }else{
            foreach ($recepcion->calidad->detalles->where('tipo_item', 'FIRMEZAS') as $detalle){
                if ($detalle->detalle_item == 'LIGHT' || $detalle->detalle_item == 'DARK' || $detalle->detalle_item == 'BLACK'){

                        $categories_porc_firmeza[] = $detalle->detalle_item;
                        if ($recepcion->n_especie == 'Cherries') {
                            $series_porc_firmeza[] = $detalle->valor_ss;
                        } else {
                            $series_porc_firmeza[] = $detalle->porcentaje_muestra;
                        }

                }
            }
            }
                $html_tabla_porc_firmeza='<table border="1" cellpadding="5" cellspacing="0">';
                $html_tabla_porc_firmeza.='<thead><tr><th>Color</th><th>Valor</th></tr></thead>';
                $html_tabla_porc_firmeza.='<tbody>';
                foreach ($categories_porc_firmeza as $key => $value) {
                    $valor = $series_porc_firmeza[$key] ?? 0;
                    $html_tabla_porc_firmeza .= '<tr>';
                    $html_tabla_porc_firmeza .= '<td>' . htmlspecialchars($value) . '</td>';
                    $html_tabla_porc_firmeza .= '<td>' . round($valor, 2) . '</td>';
                    $html_tabla_porc_firmeza .= '</tr>';
                }
                    $html_tabla_porc_firmeza.='</tbody>';
                    $html_tabla_porc_firmeza.='</table>';
                 $html_tabla_calibrix='';
        $categories_calibrix=[];
        $series_calibrix=[];
        //$items=['LIGHT','DARK','BLACK'];


        $cont=0;
        if ($recepcion->calidad->detalles->where('tipo_item','SOLIDOS SOLUBLES')->count()>0){
            foreach ($recepcion->calidad->detalles->where('tipo_item','SOLIDOS SOLUBLES') as $detalle){


                        $categories_calibrix[]=$detalle->detalle_item;
                        $series_calibrix[]=$detalle->valor_ss;
            }
                   $html_tabla_calibrix='<table border="1" cellpadding="5" cellspacing="0">';
            $html_tabla_calibrix.='<thead><tr><th>Color</th><th>Valor</th></tr></thead>';
            $html_tabla_calibrix.='<tbody>';
            foreach ($series_calibrix as $serie) {
                $html_tabla_calibrix.='<tr>';
                $html_tabla_calibrix.='<td>'.$categories_calibrix[$cont].'</td>';
                $html_tabla_calibrix.='<td>'.$serie.'</td>';
                $html_tabla_calibrix.='</tr>';
                $cont++;

            }
            $html_tabla_calibrix.='</tbody>';
            $html_tabla_calibrix.='</table>';

        }
        else{

                        $categories_calibrix[]='NONAME';
                        $series_calibrix[]=0;


        }
    }
        $categories_porcentaje_firmezas = [];
        $series_porcentaje_firmezas = [];
        $colores=[];
        $html_tabla_porcentaje_firmeza='';

   if ($recepcion->calidad->detalles) {
    if ($recepcion->n_variedad == 'Dagen') {
        foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA') as $detalle) {
            $categories_porcentaje_firmezas[] = $detalle->detalle_item;
            $series_porcentaje_firmezas[] = $detalle->porcentaje_muestra;
        }

        // Generar tabla HTML para Dagen (1 fila de datos)
        $html_tabla_porcentaje_firmeza = '<table border="1" cellpadding="5" cellspacing="0">';
        $html_tabla_porcentaje_firmeza .= '<tr><th></th>';
        foreach ($categories_porcentaje_firmezas as $categoria) {
            $html_tabla_porcentaje_firmeza .= '<th>' . $categoria . '</th>';
        }
        $html_tabla_porcentaje_firmeza .= '</tr>';

        $html_tabla_porcentaje_firmeza .= '<tr><td>Porcentaje</td>';
        foreach ($series_porcentaje_firmezas as $valor) {
            $html_tabla_porcentaje_firmeza .= '<td>' . round($valor, 2) . '%</td>';
        }
        $html_tabla_porcentaje_firmeza .= '</tr>';
        $html_tabla_porcentaje_firmeza .= '</table>';

    } else {
        // Otras variedades (tabla de 3 filas: LIGHT, DARK, BLACK)

        $l = $d = $b = [];
        foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'LIGHT') as $detalle) {
            $l[] = $detalle->valor_ss;
        }
        foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'DARK') as $detalle) {
            $d[] = $detalle->valor_ss;
        }
        foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'BLACK') as $detalle) {
            $b[] = $detalle->valor_ss;
        }

        $categories_porcentaje_firmezas = [
            'Muy Firme >280 - 1000<br>Durofel >75',
            'Firme 200 - 279<br>Durofel 72 - 74.9',
            'Sensible 180 - 199<br>Durofel 65 - 69.9',
            'Blando 0,1 - 179<br>Durofel <65,4'
        ];
        $series_porcentaje_firmezas = [$l, $d, $b];
        $colores = ['LIGHT', 'DARK', 'BLACK'];

        // Generar tabla HTML
        $html_tabla_porcentaje_firmeza = '<table border="1" cellpadding="5" cellspacing="0">';
        $html_tabla_porcentaje_firmeza .= '<tr><th></th>';
        foreach ($categories_porcentaje_firmezas as $categoria) {
            $html_tabla_porcentaje_firmeza .= '<th>' . $categoria . '</th>';
        }
        $html_tabla_porcentaje_firmeza .= '</tr>';

        foreach ($series_porcentaje_firmezas as $index => $fila) {
            $html_tabla_porcentaje_firmeza .= '<tr><td>' . $colores[$index] . '</td>';
            foreach ($fila as $valor) {
                $html_tabla_porcentaje_firmeza .= '<td>' . round($valor, 2) . '%</td>';
            }
            $html_tabla_porcentaje_firmeza .= '</tr>';
        }
        $html_tabla_porcentaje_firmeza .= '</table>';
    }
    Log::info("% firmeza ".$html_tabla_porcentaje_firmeza);
    Log::info("Color Fondo ".$html_tabla_color_fondo);
    Log::info("Calibrix ".$html_tabla_calibrix);
    Log::info("Color ".$html_tabla_color_fondo);
    Log::info("D. Calibre ".$html_tabla_distribucion_calibre);


}

         else {
                $html_tabla_distribucion_calibre = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_firmeza_grande = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_firmeza_mediana = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_firmeza_pequena = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_color_fondo = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_calibrix = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
                $html_tabla_porcentaje_firmeza = '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
            }

            // $html_tabla_color = $this->buildColorCoverageTable($coverageColor);

            // if ($calidad && ! in_array($recepcion->n_especie, ['Cherries', 'Dagen'], true)) {
            //     $html_tabla_firmeza_grande = $this->buildFirmnessSizeTable($calidad, 'GRANDE', 'Firmeza (Grande)');
            //     $html_tabla_firmeza_mediana = $this->buildFirmnessSizeTable($calidad, 'MEDIANO', 'Firmeza (Mediana)');
            //     $html_tabla_firmeza_pequena = $this->buildFirmnessSizeTable($calidad, 'CHICO', 'Firmeza (Pequeña)');
            //     $html_tabla_color_fondo = $this->buildDetallePercentageTable($calidad, 'COLOR DE FONDO', 'Color', 'Porcentaje');
            // }

            // if ($calidad) {
            //     $html_tabla_calibrix = $this->buildCalibrixTable($calidad);
            //     $html_tabla_porcentaje_firmeza = $this->buildPresionesTable($calidad);
            // }

            // $html_tabla_porc_firmeza = $this->buildAverageFirmnessTable($averageFirmness);
            // $html_tabla_color = $html_tabla_color ?: $this->buildColorCoverageTable($coverageColor);
        }
    }
     if($recepcion->id_emisor=="7023"  && ($recepcion->variedad->name=='Rainier' || $recepcion->variedad->name=='Santina')) {
                $exporterName = 'Greenex SpA';
            }
        return compact(
            'recepcion',
            'temperatura_pulpa',
            'porcentaje_exportable',
            'defectos_calidad_sum',
            'defectos_condicion_sum',
            'danos_plaga_sum',
            'sizeDistribution',
            'coverageColor',
            'averageFirmness',
            'firmnessDistribution',
            'solubleSolids',
            'isPreview',
            'exporterName',
            'seteo_termo',
            'html_tabla_distribucion_calibre',
            'html_tabla_color',
            'html_tabla_firmeza_grande',
            'html_tabla_firmeza_mediana',
            'html_tabla_firmeza_pequena',
            'html_tabla_color_fondo',
            'html_tabla_calibrix',
            'html_tabla_porc_firmeza',
            'html_tabla_porcentaje_firmeza'

        );
    }
    public function syncNotasCalidad()
    {
        $recepciones = Recepcion::whereNotNull('nota_calidad')
            ->whereNotNull('numero_g_recepcion')
            ->get(['numero_g_recepcion', 'nota_calidad']);

        if ($recepciones->isEmpty()) {
            return response()->json([
                'updated' => 0,
                'total' => 0,
                'message' => 'No hay recepciones con nota de calidad para sincronizar.',
            ]);
        }

        $updated = 0;

        foreach ($recepciones as $recepcion) {
            $affected = DB::connection('sqlsrv')
                ->table('PKG_G_Recepcion')
                ->where('numero_i', $recepcion->numero_g_recepcion)
                ->update(['nota_calidad' => $recepcion->nota_calidad]);

            $updated += $affected;
        }

        return response()->json([
            'updated' => $updated,
            'total' => $recepciones->count(),
            'message' => 'Notas de calidad sincronizadas correctamente.',
        ]);
    }

    public function exportablePercentages()
    {
        $results = [];

        Recepcion::query()
            ->whereHas('calidad')
            ->with(['calidad.detalles' => function ($query) {
                $query->select('id', 'calidad_id', 'tipo_item', 'detalle_item', 'porcentaje_muestra');
            }])
            ->orderBy('fecha_g_recepcion')
            ->chunkById(250, function ($recepciones) use (&$results) {
                foreach ($recepciones as $recepcion) {
                    $percentage = $this->calculateExportablePercentage($recepcion);
                    $results[] = [
                        'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                        'productor' => $recepcion->n_emisor,
                        'especie' => $recepcion->n_especie,
                        'variedad' => $recepcion->n_variedad,
                        'porcentaje_exportable' => $percentage,
                    ];
                }
            });

        return response()->json($results);
    }

    private function calculateExportablePercentage(Recepcion $recepcion): float
    {
        $calidad = $recepcion->calidad;

        if (! $calidad) {
            return 0;
        }

        $detalles = $calidad->detalles ?? collect();

        $defectosCalidad = $detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD')
            ->sum('porcentaje_muestra');

        $defectosCondicion = $detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN')
            ->sum('porcentaje_muestra');

        $danosPlaga = $detalles
            ->where('tipo_item', 'DAÑO DE PLAGA')
            ->sum('porcentaje_muestra');

        $defectosCalidadPrecalibre = $detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD')
            ->where('detalle_item', 'PRECALIBRE')
            ->sum('porcentaje_muestra');

        $defectosCalidadAjustado = $defectosCalidad - $defectosCalidadPrecalibre;
        $totalDefectos = $defectosCalidadAjustado + $defectosCondicion + $danosPlaga + $defectosCalidadPrecalibre;

        return max(0, 100 - $totalDefectos);
    }

    public function approveReport(Recepcion $recepcion, ReportNotificationService $notificationService)
    {
        Log::Info('approveReport:'.$recepcion->numero_g_recepcion);
        $calidad = $recepcion->calidad;

        $temperatura_pulpa = null;
        $porcentaje_exportable = 100;
        $defectos_calidad_sum = 0;
        $defectos_condicion_sum = 0;
        $danos_plaga_sum = 0;
         $exporterName = 'Greenex SpA';
        $seteo_termo = 'N/A';

        if (! empty($recepcion->n_emisor)) {
            $service = Service::query()
                ->whereHas('users', function ($query) use ($recepcion) {
                    $query->where('name', $recepcion->n_emisor);

                    if (! empty($recepcion->id_emisor)) {
                        $query->orWhere('idprod', $recepcion->id_emisor);
                    }
                })
                ->with('owner')
                ->first();

            if ($service && $service->owner) {
                $exporterName = $service->name;
            }
            // Log::Info(Service::query()
            //     ->whereHas('users', function ($query) use ($recepcion) {
            //         $query->where('name', $recepcion->n_emisor);

            //         if (! empty($recepcion->id_emisor)) {
            //             $query->orWhere('idprod', $recepcion->id_emisor);
            //         }
            //     })
            //     ->with('owner')->toSql());
        }

        if ($calidad) {
             $seteo_termo = $calidad->seteo_termo ?? 'N/A';

            $temperatura_pulpa_detalle = $calidad->detalles()->where('tipo_detalle', 'ss')->first();
            if ($temperatura_pulpa_detalle) {
                $temperatura_pulpa = $temperatura_pulpa_detalle->temperatura;
            }

            $defectos_calidad_sum = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CALIDAD')
                ->sum('porcentaje_muestra');
            $defectos_condicion_sum = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CONDICION')
                ->sum('porcentaje_muestra');
            $danos_plaga_sum = $calidad->detalles()
                ->where('tipo_item', 'DAÑOS DE PLAGA')
                ->sum('porcentaje_muestra');
                 $defectos_calidad_precalibre = $calidad->detalles()
                ->where('tipo_item', 'DEFECTOS DE CALIDAD')
                ->where('detalle_item', 'PRECALIBRE')
                ->sum('porcentaje_muestra');

            $defectos_calidad_sum=$defectos_calidad_sum-$defectos_calidad_precalibre;
            $total_defectos_sum = $defectos_calidad_sum + $defectos_condicion_sum + $danos_plaga_sum+$defectos_calidad_precalibre;
            $porcentaje_exportable = max(0, 100 - $total_defectos_sum);
        }

        $receptions = collect([$recepcion]);
        $sizeDistribution = QualityChartsService::getSizeDistributionData($receptions);
        $averageFirmness = QualityChartsService::getPromedioFirmezasData($receptions);
        $firmnessDistribution = QualityChartsService::getDistribucionFirmezasData($receptions);
        $solubleSolids = QualityChartsService::getSolidosSolublesData($receptions);
        $coverageColor = QualityChartsService::getColorCubrimientoData($receptions);

        $isPreview = false; // render without preview-only controls
            if($recepcion->id_emisor=="7023"  && $recepcion->variedad=='Rainier'){
                $exporterName = 'Greenex SpA';
            }
        $html = view('reports.reception_report', compact(
            'recepcion',
            'temperatura_pulpa',
            'porcentaje_exportable',
            'defectos_calidad_sum',
            'defectos_condicion_sum',
            'danos_plaga_sum',
            'exporterName',
            'seteo_termo',
            'sizeDistribution',
            'coverageColor',
            'averageFirmness',
            'firmnessDistribution',
            'solubleSolids',
            'isPreview'
        ))->render();

        try {
            $pdfRelative = 'reporte_recepcion_' . $recepcion->numero_g_recepcion . '.pdf';
            $pdfPath = storage_path('app/public/' . $pdfRelative);
            $tmpDir = storage_path('app/browsershot-temp');

            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
 $chrome = env('BROWSERSHOT_CHROME_PATH', '/home/forge/.cache/puppeteer/chrome/linux-139.0.7258.138/chrome-linux64/chrome');

            if(config('app.env') === 'local') {


            Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                // ->setChromePath($chrome)
                // ->setOption('executablePath', $chrome) // fuerza a puppeteer a usar ese binario
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
                ->showBackground()
                ->savePdf($pdfPath);
            } else {

                Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                ->setChromePath($chrome)
                ->setOption('executablePath', $chrome) // fuerza a puppeteer a usar ese binario
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
                ->showBackground()
                ->savePdf($pdfPath);
            }

            // Save public URL in recepcion->informe so Index can show direct link
            $publicUrl = asset('storage/' . $pdfRelative);
            $recepcion->informe = $publicUrl;
            $recepcion->save();

            try {
                 Log::info('Reception notification send', [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            ]);
                $notificationService->notifyReceptionReport(
                    $recepcion->fresh(),
                    $publicUrl,
                    $pdfPath,
                    $pdfRelative
                );
            } catch (\Throwable $e) {
                Log::error('Reception notification dispatch failed', [
                    'recepcion_id' => $recepcion->id,
                    'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => 'approved',
                'url' => $publicUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Approve report error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function generatePreviewReportPdf(Recepcion $recepcion): array
    {
        $viewData = $this->buildPreviewReportViewData($recepcion, true);
        $html = view('reports.reception_report', $viewData)->render();

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
@mkdir($tmpDir, 0755, true);
        }

        $filename = 'reporte_recepcion_' . $recepcion->numero_g_recepcion . '_preview.pdf';
        $tempPath = $tmpDir . '/preview_' . $recepcion->id . '_' . time() . '.pdf';
        $chrome = env('BROWSERSHOT_CHROME_PATH', env('CHROME_PATH', '/home/forge/.cache/puppeteer/chrome/linux-139.0.7258.138/chrome-linux64/chrome'));

        try {
            if(config('app.env') === 'local') {
            $shot = Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                // ->setChromePath($chrome)
                // ->setOption('executablePath', $chrome)
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
            } else {
                $shot = Browsershot::html($html)
                ->setTemporaryDirectory($tmpDir)
                ->setChromePath($chrome)
                ->setOption('executablePath', $chrome)
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
            }

            $shot->savePdf($tempPath);
        } catch (\Throwable $e) {
            Log::error('Preview report PDF generation failed', [
                'recepcion_id' => $recepcion->id,
                'error' => $e->getMessage(),
            ]);

            return [null, null, $e->getMessage()];
        }

        return [$tempPath, $filename, null];
    }

    public function sendPreviewEmail(Recepcion $recepcion)
    {
         if (!Auth::user()->role('Administrador')->exists()) {
            if ($recepcion->informe) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El reporte ya fue aprobado, utiliza la opción de reenviar.',
                ], 422);
            }
         }

        if (app()->environment('local')) {
            $recipients = array_filter(['carlos.alvarez@greenex.cl','fabian.garay@greenex.cl','nadia.lell@greenex.cl']);
        } else {
            $recipients = array_values(array_filter(config('reports.preview_recipients', [])));
            if ($recepcion->n_emisor === 'Greenex SpA') {
                $recipients[] = 'claudio.jorquera@greenex.cl';
            }
            Log::debug('Preview report recipients', ['recipients' => $recipients]);
        }

        if (empty($recipients)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay destinatarios configurados para la previsualización.',
            ], 500);
        }

        [$tempPath, $filename, $error] = $this->generatePreviewReportPdf($recepcion);
        if ($error !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo generar el PDF del reporte.',
            ], 500);
        }



        $uniqueRecipients = collect($recipients)->filter()->unique()->values();
        $failedRecipients = collect();

        foreach ($uniqueRecipients as $recipient) {
            try {
                Mail::to($recipient)->send(new ReceptionReportPreview(
                    $recepcion->fresh(),
                    route('control-calidad.preview-report', $recepcion->id),
                    $tempPath,
                    $filename
                ));
                Log::info('Preview report email sent', [
                    'recepcion_id' => $recepcion->id,
                    'recipient' => $recipient,
                ]);
            } catch (\Throwable $e) {
                Log::error('Preview report email send failed', [
                    'recepcion_id' => $recepcion->id,
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
                $failedRecipients->push([
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

@unlink($tempPath);

        if ($failedRecipients->isNotEmpty()) {
            if ($failedRecipients->count() === $uniqueRecipients->count()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo enviar el correo de previsualizaci�n a ninguno de los destinatarios.',
                    'failed' => $failedRecipients,
                ], 500);
            }

            return response()->json([
                'status' => 'partial',
                'message' => 'El reporte se envi� solo a algunos destinatarios. Revisa los registros para detalles.',
                'failed' => $failedRecipients,
            ]);
        }

        return response()->json([
            'status' => 'sent',
        ]);
    }

    public function sendPreviewWhatsapp(Recepcion $recepcion, ReportNotificationService $notificationService)
    {
        if (!Auth::user()->role('Administrador')->exists()) {
            if ($recepcion->informe) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El reporte ya fue aprobado, utiliza la opción de reenviar.',
                ], 422);
            }
        }
        $phones = array_values(array_filter(config('reports.preview_phones', [])));
        if (empty($phones)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay destinatarios configurados para la previsualización vía WhatsApp.',
            ], 500);
        }

        [$tempPath, $filename, $error] = $this->generatePreviewReportPdf($recepcion);
        if ($error !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo generar el PDF del reporte.',
            ], 500);
        }

        $previewUrl = route('control-calidad.preview-report', $recepcion->id);
        Log::info('Preview report WhatsApp send', [
            'recepcion_id' => $recepcion->id,
            'phones' => $phones,
            'preview_url' => $previewUrl,
        ]);

        $notificationService->sendPreviewReportWhatsapp(
            $recepcion->fresh(),
            $phones,
            $previewUrl,
            $tempPath,
            $filename
        );

@unlink($tempPath);

        return response()->json([
            'status' => 'sent',
        ]);
    }

public function resendReport(Recepcion $recepcion, ReportNotificationService $notificationService)
    {
        if (! $recepcion->informe) {
            return response()->json([
                'status' => 'error',
                'message' => 'La recepcion no tiene informe disponible para reenviar.',
            ], 422);
        }

        $publicUrl = $recepcion->informe;
        $relativePath = null;

        if ($publicUrl) {
            $relativePath = Str::after($publicUrl, '/storage/');
            if ($relativePath === $publicUrl) {
                $relativePath = null;
            }
        }

        $absolutePath = $relativePath ? storage_path('app/public/' . ltrim($relativePath, '/')) : null;

        try {
            Log::info('Reception notification resend', [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
            ]);
            $notificationService->notifyReceptionReport(
                $recepcion->fresh(),
                $publicUrl,
                $absolutePath,
                $relativePath
            );
        } catch (\Throwable $e) {
            Log::error('Reception notification resend failed', [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                'error' => $e->getMessage(),
                 'stacktrace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo reenviar el informe.',
            ], 500);
        }

        return response()->json([
            'status' => 'resent',
        ]);
    }

    private function renderTabularTable(array $headers, array $rows): string
    {
        if (empty($rows)) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $html = '<table style="width:100%; border-collapse:collapse; font-size:10px; margin:6px 0;">';
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th style="border:1px solid #d1d5db; padding:4px; background-color:#f3f4f6; text-align:left;">'
                . e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            $values = array_values($row);
            foreach ($values as $index => $cell) {
                $align = $index === 0 ? 'left' : 'right';
                $html .= '<td style="border:1px solid #d1d5db; padding:4px; text-align:' . $align . ';">'
                    . $this->formatTableCell($cell, $index === 0) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function formatTableCell($value, bool $isLabel = false): string
    {
        if ($value === null || $value === '') {
            return $isLabel ? '&nbsp;' : '0';
        }

        if (is_string($value) && str_contains($value, '%')) {
            return e($value);
        }

        if (is_numeric($value)) {
            $floatValue = (float) $value;
            $decimals = abs($floatValue - round($floatValue)) > 0.01 ? 2 : 0;

            return number_format($floatValue, $decimals, ',', '.');
        }

        return e((string) $value);
    }


    private function buildCalibreDistributionTable(array $sizeDistribution): string
    {


        if (isset($sizeDistribution['categories'], $sizeDistribution['countsSeries'])) {
            $categories = $sizeDistribution['categories'];
            $series = $sizeDistribution['countsSeries'];

            if (empty($categories) || empty($series)) {
                return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
            }

            $headers = array_merge(['Calibre'], array_map(static function ($serie) {
                return $serie['name'] ?? '';
            }, $series), ['Total']);

            $columnTotals = array_fill(0, count($series), 0);
            $rows = [];

            foreach ($categories as $index => $category) {
                $row = [$category];
                $rowTotal = 0;

                foreach ($series as $serieIndex => $serie) {
                    $value = (float) ($serie['data'][$index] ?? 0);
                    $row[] = $value;
                    $rowTotal += $value;
                    $columnTotals[$serieIndex] += $value;
                }

                $row[] = $rowTotal;
                $rows[] = $row;
            }

            $totalRow = ['Total'];
            foreach ($columnTotals as $total) {
                $totalRow[] = $total;
            }
            $totalRow[] = array_sum($columnTotals);
            $rows[] = $totalRow;

            return $this->renderTabularTable($headers, $rows);
        }

        if (empty($sizeDistribution)) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $total = array_sum(array_map(static function ($item) {
            return (float) ($item['count'] ?? 0);
        }, $sizeDistribution));

        $rows = [];
        foreach ($sizeDistribution as $item) {
            $calibre = $item['calibre'] ?? $item['name'] ?? 'N/A';
            $count = (float) ($item['count'] ?? 0);
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
            $rows[] = [
                $calibre,
                $count,
                number_format($percentage, 2, ',', '.') . ' %',
            ];
        }

        $rows[] = [
            'Total',
            $total,
            $total > 0 ? '100 %' : '0 %',
        ];

        return $this->renderTabularTable(['Calibre', 'Cantidad', 'Porcentaje'], $rows);
    }

    private function buildColorCoverageTable($coverageColor): string
    {
        if (is_array($coverageColor) && isset($coverageColor['categories'], $coverageColor['countsSeries'])) {
            $categories = $coverageColor['categories'];
            $series = $coverageColor['countsSeries'];

            if (empty($categories) || empty($series)) {
                return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
            }

            $headers = array_merge(['Color'], array_map(static function ($serie) {
                return $serie['name'] ?? '';
            }, $series), ['Total']);

            $columnTotals = array_fill(0, count($series), 0);
            $rows = [];

            foreach ($categories as $index => $category) {
                $row = [$category];
                $rowTotal = 0;

                foreach ($series as $serieIndex => $serie) {
                    $value = (float) ($serie['data'][$index] ?? 0);
                    $row[] = $value;
                    $rowTotal += $value;
                    $columnTotals[$serieIndex] += $value;
                }

                $row[] = $rowTotal;
                $rows[] = $row;
            }

            $totalRow = ['Total'];
            foreach ($columnTotals as $total) {
                $totalRow[] = $total;
            }
            $totalRow[] = array_sum($columnTotals);
            $rows[] = $totalRow;

            return $this->renderTabularTable($headers, $rows);
        }

        if (! is_array($coverageColor) || empty($coverageColor)) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $rows = [];
        foreach ($coverageColor as $item) {
            $color = $item['color'] ?? 'N/A';
            $percentage = (float) ($item['percentage'] ?? 0);
            $rows[] = [$color, number_format($percentage, 2, ',', '.') . ' %'];
        }

        return $this->renderTabularTable(['Color', 'Porcentaje'], $rows);
    }

    private function buildFirmnessSizeTable(?Calidad $calidad, string $tipoItem, string $titulo): string
    {
        if (! $calidad) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $details = $calidad->detalles->where('tipo_item', strtoupper($tipoItem));
        if ($details->isEmpty()) {
            $details = $calidad->detalles->where('tipo_item', ucfirst(strtolower($tipoItem)));
        }

        if ($details->isEmpty()) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $rows = $details->map(function ($detail) {
            $label = $detail->detalle_item ?? 'N/A';
            $value = $detail->valor_ss ?? $detail->porcentaje_muestra ?? 0;

            return [$label, $value];
        })->values()->all();

        return $this->renderTabularTable(['Medición', 'Valor'], $rows);
    }

    private function buildDetallePercentageTable(?Calidad $calidad, string $tipoItem, string $labelTitle, string $valueTitle): string
    {
        if (! $calidad) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $details = $calidad->detalles->where('tipo_item', $tipoItem);
        if ($details->isEmpty()) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $rows = $details->map(function ($detail) {
            $label = $detail->detalle_item ?? 'N/A';
            $percentage = (float) ($detail->porcentaje_muestra ?? $detail->valor_ss ?? 0);

            return [$label, number_format($percentage, 2, ',', '.') . ' %'];
        })->values()->all();

        return $this->renderTabularTable([$labelTitle, $valueTitle], $rows);
    }

    private function buildCalibrixTable(?Calidad $calidad): string
    {
        if (! $calidad) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $rows = [];
        $mapping = [
            'GRANDE' => 'Grande',
            'MEDIANO' => 'Mediana',
            'CHICO' => 'Pequeña',
        ];

        foreach ($mapping as $tipo => $label) {
            $detail = $calidad->detalles
                ->where('tipo_item', $tipo)
                ->firstWhere('detalle_item', 'Solidos Solubles');

            if ($detail) {
                $rows[] = [$label, $detail->valor_ss ?? 0];
            }
        }

        return $this->renderTabularTable(['Categoría', 'Solidos Solubles (°Brix)'], $rows);
    }

    private function buildAverageFirmnessTable(array $averageFirmness): string
    {
        $categories = $averageFirmness['categories'] ?? [];
        $series = $averageFirmness['series'] ?? [];

        if (empty($categories) || empty($series)) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $headers = array_merge(['Segmento'], array_map(static function ($serie) {
            return $serie['name'] ?? '';
        }, $series));

        $rows = [];
        foreach ($categories as $index => $category) {
            $label = is_array($category) ? implode(' / ', array_filter($category, static fn ($item) => $item !== null && $item !== '')) : (string) $category;
            $row = [$label];

            foreach ($series as $serie) {
                $row[] = $serie['data'][$index] ?? 0;
            }

            $rows[] = $row;
        }

        return $this->renderTabularTable($headers, $rows);
    }

    private function buildPresionesTable(?Calidad $calidad): string
    {
        if (! $calidad) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $details = $calidad->detalles->where('tipo_item', 'PRESIONES');
        if ($details->isEmpty()) {
            return '<p style="font-size:10px; margin:6px 0;">Sin datos.</p>';
        }

        $rows = $details->map(function ($detail) {
            $label = $detail->detalle_item ?? 'N/A';
            $value = $detail->valor_ss ?? $detail->porcentaje_muestra ?? 0;

            return [$label, $value];
        })->values()->all();

        return $this->renderTabularTable(['Segmento', 'Valor'], $rows);
    }
}
