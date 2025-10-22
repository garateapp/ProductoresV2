<?php


namespace App\Http\Controllers;

use App\Models\Calidad;
use App\Models\Detalle;
use App\Models\Especie;
use App\Models\Parametro;
use App\Models\PhotoType;
use App\Models\Recepcion;
use App\Models\Valor;
use App\Models\Variedad;
use App\Mail\ReceptionReportPreview;
use App\Services\QualityChartsService;
use App\Services\ReportNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function cargarFirmpro(Recepcion $recepcion)
    {
        $calidad = $recepcion->calidad;
        $embalaje='';
        $fecha=date('Y-m-d H:i:s');

        if (!$calidad) {
            return response()->json(['message' => 'No se encontró registro de calidad para esta recepción.'], 404);
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
        $recepcion->loadMissing(['calidad.photos.photoType']);

        $calidad = $recepcion->calidad;

        $temperatura_pulpa = null;
        $porcentaje_exportable = 100;
        $defectos_calidad_sum = 0;
        $defectos_condicion_sum = 0;
        $danos_plaga_sum = 0;

        if ($calidad) {
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
            'isPreview'

        );
    }

    public function approveReport(Recepcion $recepcion, ReportNotificationService $notificationService)
    {
        $calidad = $recepcion->calidad;

        $temperatura_pulpa = null;
        $porcentaje_exportable = 100;
        $defectos_calidad_sum = 0;
        $defectos_condicion_sum = 0;
        $danos_plaga_sum = 0;

        if ($calidad) {
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
        $html = view('reports.reception_report', compact(
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

            // Save public URL in recepcion->informe so Index can show direct link
            $publicUrl = asset('storage/' . $pdfRelative);
            $recepcion->informe = $publicUrl;
            $recepcion->save();

            try {
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
            $recipients = array_filter(['carlos.alvarez@greenex.cl']);
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

        $previewUrl = route('control-calidad.preview-report', $recepcion->id);

        $notificationService->sendPreviewReportWhatsapp($recepcion->fresh(), $phones, $previewUrl);

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
}
