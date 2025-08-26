<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use App\Models\Especie;
use App\Models\User;
use App\Models\Variedad;
use App\Models\Parametro;
use App\Models\Valor;
use App\Models\Calidad;
use App\Models\Detalle;
use App\Models\PhotoType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Knp\Snappy\Pdf;

class ControlCalidadController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (!empty($user->idprod)) {
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
                $q->where('n_variedad', 'like', '%' . $searchTerm . '%')
                  ->orWhere('n_especie', 'like', '%' . $searchTerm . '%');
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
        $recepciones = $query->with(['calidad.photos.photoType'])->paginate(10);

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
            'obs_ext' => 'nullable|string',
        ]);

        if($validated['materia_vegetal']==true){
            $validated['materia_vegetal']='SI';
        }
        if($validated['piedras']==true){

            $validated['piedras']='SI';
        }
        if($validated['barro']==true){
            $validated['barro']='SI';
        }
        if($validated['pedicelo_largo']==true){
            $validated['pedicelo_largo']='SI';
        }
        if($validated['racimo']==true){
            $validated['racimo']='SI';
        }
        if($validated['esponjas']==true){
            $validated['esponjas']='SI';
        }
        if($validated['llenado_tottes']==true){
            $validated['llenado_tottes']='SI';
        }

        $t_muestra = $validated['t_muestra'] ?? 100;
        $validated['t_muestra'] = $t_muestra;

        $calidad = Calidad::updateOrCreate(
            ['recepcion_id' => $validated['recepcion_id'], 't_muestra' => $t_muestra],
            $validated
        );

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
            'valor_id' => 'required|exists:valors,id',
            'cantidad_muestra' => 'nullable|integer',
            'exportable' => 'boolean',
            'temperatura' => 'nullable',
            'valor_presion' => 'nullable|numeric',
        ]);

        $parametro = Parametro::find($validated['parametro_id']);
        $valor = Valor::find($validated['valor_id']);

        if(in_array($validated["parametro_id"],["1","2","3","4","5","6"])){
            $tipo_detalle="cc";
        }
        else{
            $tipo_detalle="ss";
        }
        $calidad=Calidad::find($validated['calidad_id']);
        if($calidad->t_muestra==null){
            $t_muestra=100;
        }
        else{
            $t_muestra=$calidad->t_muestra;
        }

        $porcMuestra=$validated['cantidad_muestra']/$t_muestra*100;

        $categoria = $validated['exportable'] ? 'Exportable' : null;

        $detalleData = [
            'calidad_id' => $validated['calidad_id'],
            'porcentaje_muestra'=>$porcMuestra,
            'valor_ss'=>$validated['valor_presion'],
            'cantidad' => $validated['cantidad_muestra'],
            'tipo_detalle' => $tipo_detalle,
            'temperatura' => $validated['temperatura'],
            'categoria' => $categoria,
        ];

        $detalle = Detalle::updateOrCreate(
            [
                'calidad_id' => $validated['calidad_id'],
                'tipo_item' => $parametro->name,
                'detalle_item' => $valor->name,
                'tipo_detalle' => $tipo_detalle,
                'porcentaje_muestra'=>$porcMuestra,
                'valor_ss'=>$validated['valor_presion'],
                'categoria' => $categoria,

            ],
            $detalleData
        );

        $detalles = Detalle::where('calidad_id',$validated['calidad_id'])->with(['parametro', 'valor'])->get();
        $defecto_param_names = Parametro::whereIn('id', [3, 4, 5])->pluck('name')->toArray();
        $desorden_param_names = Parametro::whereIn('id', [11, 12])->pluck('name')->toArray();
        $curva_param_names = Parametro::whereIn('id', [1,2,6])->pluck('name')->toArray();
        $madurez_param_names = Parametro::whereIn('id', [7, 8, 9, 10, 13, 14, 15, 16, 17, 18])->pluck('name')->toArray();

        $defectos = $detalles->whereIn('tipo_item', $defecto_param_names);
        $desordenFisiologico = $detalles->whereIn('tipo_item', $desorden_param_names);
        $curvaCalibre = $detalles->whereIn('tipo_item', $curva_param_names);
        $indiceMadurez = $detalles->whereIn('tipo_item', $madurez_param_names);


        return redirect()->back()->with('success', 'Detalle guardado exitosamente.');
    }

    public function getDetalles(Recepcion $recepcion)
    {
        $calidad = $recepcion->calidad;

        if (!$calidad) {
            return response()->json(['defectos' => [], 'desordenFisiologico' => []]);
        }

        $detalles = $calidad->detalles()->with(['parametro', 'valor'])->get();

        $defecto_param_names = Parametro::whereIn('id', [3, 4, 5])->pluck('name')->toArray();
        $desorden_param_names = Parametro::whereIn('id', [11, 12])->pluck('name')->toArray();
        $curva_param_names = Parametro::whereIn('id', [1,2,6])->pluck('name')->toArray();
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
        if (!$recepcion->calidad) {
            return response()->json(null);
        }

        $calidad = Calidad::with('photos.photoType')->find($recepcion->calidad->id);

        return response()->json($calidad);
    }


        public function cargarFirmpro(Recepcion $recepcion)
    {
        $calidad = $recepcion->calidad;

        if (!$calidad) {
            return response()->json(['message' => 'No se encontró registro de calidad para esta recepción.'], 404);
        }

        $firmpro1=Http::post('https://api.greenexweb.cl/api/BuscarRecepcionCloud?filter[numero_recepcion][eq]='.$recepcion->numero_g_recepcion);

        $firmpro1 = $firmpro1->json();

        $categories=[];
        $series=[];
        if ($recepcion->n_variedad=='Dagen') {
            $rangos=[279,219,179,1,11,12];
        }else{
            $rangos=[279,219,179,1];
        }

        $l=[];
        $d=[];
        $b=[];

        foreach ($rangos as $rango){
            $nfrutos=0;
            $nfrutostot=0;
            $nfirmeza=0;
            $sumt=0;
            $light=0;
            $dark=0;
            $black=0;
            $tlight=0;
            $tdark=0;
            $tblack=0;

            foreach ($firmpro1 as $items){
                $n=1;

                foreach ($items as $item){

                    if ($n==4) {
                        $firmeza=$item;
                    }
                    if ($n==5) {
                        $calibre=$item;
                    }
                    if ($n==13) {
                        $color=$item;
                    }
                    if ($n==14) {

                        if($color=='Rojo'){
                            $tlight+=1;
                        }
                        if($color=='Rojo caoba'){
                            $tdark+=1;
                        }
                        if($color=='Santina'){
                            $tdark+=1;
                        }
                        if($color=='Caoba oscuro' || $color=='Caoba Oscuro'){
                            $tblack+=1;
                        }
                        if($color=='Negro'){
                            $tblack+=1;
                        }


                        if ($rango==279) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre<28) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {
                                if ($firmeza>=280) {
                                    if($color=='Rojo'){
                                        $light+=1;
                                    }
                                    if($color=='Rojo caoba'){
                                        $dark+=1;
                                    }
                                    if($color=='Santina'){
                                        $dark+=1;
                                    }
                                    if($color=='Caoba oscuro' || $color=='Caoba Oscuro'){
                                        $black+=1;
                                    }
                                    if($color=='Negro'){
                                        $black+=1;
                                    }
                                }

                            }

                        }
                        if ($rango==219) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre>=28 && $calibre<30) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {
                                if ($firmeza>=200 && $firmeza<280) {
                                    if($color=='Rojo'){
                                        $light+=1;
                                    }
                                    if($color=='Rojo caoba'){
                                        $dark+=1;
                                    }
                                    if($color=='Santina'){
                                        $dark+=1;
                                    }
                                    if($color=='Caoba oscuro' || $color=='Caoba Oscuro'){
                                        $black+=1;
                                    }
                                    if($color=='Negro'){
                                        $black+=1;
                                    }
                                }
                            }
                        }
                        if ($rango==179) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre>=30 && $calibre<33) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {
                                if ($firmeza>=180 && $firmeza<200) {
                                    if($color=='Rojo'){
                                        $light+=1;
                                    }
                                    if($color=='Rojo caoba'){
                                        $dark+=1;
                                    }
                                    if($color=='Santina'){
                                        $dark+=1;
                                    }
                                    if($color=='Caoba oscuro'|| $color=='Caoba Oscuro'){
                                        $black+=1;
                                    }
                                    if($color=='Negro'){
                                        $black+=1;
                                    }
                                }
                            }
                        }
                        if ($rango==1) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre>=33 && $calibre<36) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {
                                if ($firmeza>=1 && $firmeza<180) {
                                    if($color=='Rojo'){
                                        $light+=1;
                                    }
                                    if($color=='Rojo caoba'){
                                        $dark+=1;
                                    }
                                    if($color=='Santina'){
                                        $dark+=1;
                                    }
                                    if($color=='Caoba oscuro' || $color=='Caoba Oscuro'){
                                        $black+=1;
                                    }
                                    if($color=='Negro'){
                                        $black+=1;
                                    }
                                }
                            }
                        }
                        if ($rango==11) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre>=36 && $calibre<39) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {

                            }
                        }
                        if ($rango==12) {
                            if ($recepcion->n_variedad=='Dagen') {
                                if ($calibre>=39) {
                                    $sumt+=$firmeza;
                                    $nfrutos+=1;
                                }
                            } else {

                            }
                        }
                    }
                    $n+=1;
                }
                $nfrutostot+=1;
            }
            if ($recepcion->n_variedad=='Dagen') {
                if($sumt>0 && $nfrutos>0){

                    if ($rango==279) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'PRECALIBRE',
                            'fecha'=>Carbon::now()
                        ]);
                    }
                    if ($rango==219) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'L',
                            'fecha'=>Carbon::now()
                        ]);

                    }
                    if ($rango==179) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'XL',
                            'fecha'=>Carbon::now()
                        ]);
                    }
                    if ($rango==1) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'J',
                            'fecha'=>Carbon::now()
                        ]);
                    }
                    if ($rango==11) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'2J',
                            'fecha'=>Carbon::now()
                        ]);
                    }
                    if ($rango==12) {
                        Detalle::create([
                            'calidad_id'=>$calidad->id,
                            'embalaje'=>$calidad->embalaje ?? 1,
                            'valor_ss'=>$sumt/$nfrutos,
                            'porcentaje_muestra'=>$sumt/$nfrutos,
                            'tipo_item'=>'FIRMEZAS',
                            'tipo_detalle'=>'ss',
                            'detalle_item'=>'3J',
                            'fecha'=>Carbon::now()
                        ]);
                    }
                }

            }else{

                if ($tlight>0) {
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>$light*100/$tlight,
                        'porcentaje_muestra'=>$light*100/$tlight,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'LIGHT',
                        'fecha'=>Carbon::now()
                    ]);
                    //$l[]=$light*100/$tlight;
                }else{
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>0,
                        'porcentaje_muestra'=>0,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'LIGHT',
                        'fecha'=>Carbon::now()
                    ]);
                }

                if ($tdark>0) {
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>$dark*100/$tdark,
                        'porcentaje_muestra'=>$dark*100/$tdark,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'DARK',
                        'fecha'=>Carbon::now()
                    ]);
                    //$d[]=$dark*100/$tdark;
                }else{
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>0,
                        'porcentaje_muestra'=>0,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'DARK',
                        'fecha'=>Carbon::now()
                    ]);
                }

                if ($tblack>0) {
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>$black*100/$tblack,
                        'porcentaje_muestra'=>$black*100/$tblack,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'BLACK',
                        'fecha'=>Carbon::now()
                    ]);
                    //$b[]=$black*100/$tblack;
                }else{
                    Detalle::create([
                        'calidad_id'=>$calidad->id,
                        'embalaje'=>$calidad->embalaje ?? 1,
                        'valor_ss'=>0,
                        'porcentaje_muestra'=>0,
                        'tipo_item'=>'DISTRIBUCIÓN DE FIRMEZA',
                        'tipo_detalle'=>'cc',
                        'detalle_item'=>'BLACK',
                        'fecha'=>Carbon::now()
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Firmpro data loaded successfully.']);
    }

   public function generateReport(Recepcion $recepcion)
    {
        $calidad = $recepcion->calidad;

        $temperatura_pulpa = null;
        $porcentaje_exportable = 100;
        $defectos_calidad_sum = 0;
        $defectos_condicion_sum = 0;
        $danos_plaga_sum = 0;
        $distribucion_calibres = collect();
        $distribucion_color = collect();
        $promedio_firmezas = collect();
        $promedio_brix = collect();
        $distribucion_firmeza_color = ['light' => collect(), 'dark' => collect(), 'black' => collect()];
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

            $total_defectos_sum = $defectos_calidad_sum + $defectos_condicion_sum + $danos_plaga_sum;

            $porcentaje_exportable = 100 - $total_defectos_sum;
            if ($porcentaje_exportable < 0) $porcentaje_exportable = 0;

            $distribucion_calibres = $calidad->detalles()->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->get();
            $distribucion_color = $calidad->detalles()->where('tipo_item', 'COLOR DE CUBRIMIENTO')->get();
            $promedio_firmezas = $calidad->detalles()->where('tipo_item', 'FIRMEZAS')->get();
            $promedio_brix = $calidad->detalles()->where('tipo_item', 'SOLIDOS SOLUBLES')->get();

            $distribucion_firmeza_color = ['light' => collect(), 'dark' => collect(), 'black' => collect()];
        }

        $html = view('reports.reception_report', compact(
            'recepcion',
            'temperatura_pulpa',
            'porcentaje_exportable',
            'defectos_calidad_sum',
            'defectos_condicion_sum',
            'danos_plaga_sum',
            'distribucion_calibres',
            'distribucion_color',
            'promedio_firmezas',
            'promedio_brix',
            'distribucion_firmeza_color'
        ))->render();

                try {
                                    $pdfPath = storage_path('app/public/reporte_recepcion_' . $recepcion->numero_g_recepcion . '.pdf');
            Browsershot::html($html)
                ->setNodeBinary('C:\Program Files\nodejs\node.exe') // VERIFY THIS PATH
                ->setNpmBinary('C:\Program Files\nodejs\npm.cmd')   // VERIFY THIS PATH
                ->setChromePath('C:\Program Files\Google\Chrome\Application\chrome.exe') // VERIFY THIS PATH
                ->showBackground()
                ->noSandbox()
                ->waitUntilNetworkIdle()
                ->delay(10000) // Increased delay
                ->setViewport(1920, 1080) // Set a specific viewport size
                ->setOption('landscape', false)
                ->setOption('printBackground', true)
                ->savePdf($pdfPath); // Save to file instead of downloading

            return response('PDF generated and saved to: ' . $pdfPath, 200); // Return a success message
        } catch (Exception $e) {
            Log::error('Browsershot PDF generation failed: ' . $e->getMessage());
            // Optionally, return an error response to the user
            // return response('Error generating PDF: ' . $e->getMessage(), 500);
        }
    }
}
