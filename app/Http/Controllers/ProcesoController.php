<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Proceso;
use App\Models\Variedad;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProcesoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (!empty($user->idprod)) {
            $isProducer = true;
        }

        $query = Proceso::query();

        // Base access scope: producer's own + service-associated producers (owner or member)
        $allowedProducerIds = collect();
        if (!empty($user->idprod)) {
            $allowedProducerIds->push($user->idprod);
        }
        // Services the user owns
       $ownedServiceUserIds = Service::where('owner_id', $user->id)
            ->with(['users:id,idprod'])
            ->get()
            ->pluck('users')
            ->flatten()
            ->pluck('idprod')
            ->filter();
        // Services the user belongs to
        $memberServiceUserIds = $user->services()->with(['users:name'])->get()
            ->pluck('users')
            ->flatten();


        $allowedProducerIds = $memberServiceUserIds->map(function ($id) {
            return $id->name;
        });


        if ($allowedProducerIds->isNotEmpty()) {
            $query->whereIn('agricola', $allowedProducerIds->all());
        }

        // General search filter
        if ($request->has('search') && $request->input('search') !== '' && $request->input('search') !== null) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('especie', 'like', '%'.$searchTerm.'%')
                    ->orWhere('variedad', 'like', '%'.$searchTerm.'%');
            });
        }

        $variedades = collect();

        // Filter by selected especie
        if ($request->has('especie_id') && $request->input('especie_id') !== '' && $request->input('especie_id') !== null) {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('especie', $especie->name);
                $variedades = $especie->variedads;
            }
        }

        // Filter by selected variedad
        if ($request->has('variedad_id') && $request->input('variedad_id') !== '' && $request->input('variedad_id') !== null) {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('variedad', $variedad->name);
            }
        }

        $totalProcesos = $query->count();
        $totalKgProcesados = (int) $query->sum('kilos_netos');
        $totalExportacion = (int) $query->sum('exp');
        $totalComercial = (int) $query->sum('comercial');
        $totalMerma = (int) $query->sum('merma');

        // Calculate totals for the chart
        $chartDataQuery = clone $query; // Clone the query
         $query->orderBy('fecha', 'desc');
        $chartData = $chartDataQuery->selectRaw('especie, SUM(exp) as exportacion, SUM(comercial) as comercial, SUM(desecho) as desecho, SUM(merma) as merma')
            ->groupBy('especie')
            ->get();

        $procesos = $query->paginate(10); // Use the original query for pagination

        $especies = Especie::all();

        // Filter species for producer if applicable
        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
        }

        return Inertia::render('Procesos/Index', [
            'procesos' => $procesos,
            'especies' => $especies->toArray(),
            'variedades' => $variedades,
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'isProducer' => $isProducer,
            'totalProcesos' => $totalProcesos,
            'totalKgProcesados' => $totalKgProcesados,
            'totalExportacion' => $totalExportacion,
            'totalComercial' => $totalComercial,
            'totalMerma' => $totalMerma,
            'chartData' => $chartData->toArray(), // Pass chart data to frontend
        ]);
    }
    public function sync_proces()
    {   $fechaActual = new DateTime();

        // Restar 5 días a la fecha actual
        $fechaActual->modify('-3 days');

        // Formatear la fecha para mostrarla


        $procesos=Http::post('https://api.greenexweb.cl/api/DatosProduccion');
        $procesos = $procesos->json();

        $ri=Proceso::all();
        $totali=$ri->count();
        $n_proceso_anterior=0;
        foreach ($procesos as $proceso){

            $agricola=Null;//1
            $n_proceso=Null;//2
            $especie=Null;//3
            $variedad=Null;//4
            $kilos_netos=Null;//5
            $categoria=Null;//6
            //7
            $id_empresa=Null;//8
            $c_productor=Null;

            $m=1;

            foreach ($proceso as $item){

                if($m==1){
                    $agricola=$item;
                }
                if($m==2){
                    $n_proceso=$item;
                    if($n_proceso_anterior!=$n_proceso){

                        $n_proceso_anterior=$n_proceso;
                        $sumExportacion=0;
                        $sumSinProcesar=0;
                        $sumDesecho=0;
                        $sumMercadoInterno=0;
                    }
                }
                if($m==3){
                    $especie=$item;
                }
                if($m==4){
                    $variedad=$item;
                }
                if($m==5){
                    $fecha=$item;
                }
                if($m==6){
                    $kilos_netos=$item;
                }
                if($m==7){
                    $categoria=$item;
                }
                if($m==8){
                    $id_empresa=$item;
                }
                if($m==9){
                    $c_productor=$item;
                }

               if($m==9){

                        $cont=Proceso::where('n_proceso',$n_proceso)->where('temporada','actual')->where('id_empresa',$id_empresa)->first();



                        if($cont){


                            if($categoria=='Sin Procesar'){
                                $sumSinProcesar=$sumSinProcesar+$kilos_netos;
                                $cont->forceFill([
                                    'agricola' => $agricola,//1
                                    'n_proceso' => $n_proceso,//2
                                    'especie' => $especie,//3
                                    'variedad' => $variedad,//4
                                    'fecha' => $fecha,//5
                                    'kilos_netos' => $sumSinProcesar,//6
                                    'id_empresa' => $id_empresa,//8
                                     'temporada' => 'actual',//9,
                                     'c_productor'=>$c_productor
                                ])->save();
                            }elseif($categoria=='Exportacion'){

                                $sumExportacion=$sumExportacion+$kilos_netos;

                                $cont->forceFill([
                                    'agricola' => $agricola,//1
                                    'n_proceso' => $n_proceso,//2
                                    'especie' => $especie,//3
                                    'variedad' => $variedad,//4
                                    'fecha' => $fecha,//5
                                    'exp' => $sumExportacion,//6
                                    'id_empresa' => $id_empresa,//8
                                     'temporada' => 'actual',//9,
                                     'c_productor'=>$c_productor
                                ])->save();
                            }elseif($categoria=='Mercado Interno'){
                                $sumMercadoInterno=$sumMercadoInterno+$kilos_netos;
                                $cont->forceFill([
                                    'agricola' => $agricola,//1
                                    'n_proceso' => $n_proceso,//2
                                    'especie' => $especie,//3
                                    'variedad' => $variedad,//4
                                    'fecha' => $fecha,//5
                                    'comercial' => $sumMercadoInterno,//6
                                    'id_empresa' => $id_empresa,//8
                                     'temporada' => 'actual',//9,
                                     'c_productor'=>$c_productor
                                ])->save();
                            }elseif($categoria=='Desecho'){
                                $sumDesecho=$sumDesecho+$kilos_netos;
                                $cont->forceFill([
                                    'agricola' => $agricola,//1
                                    'n_proceso' => $n_proceso,//2
                                    'especie' => $especie,//3
                                    'variedad' => $variedad,//4
                                    'fecha' => $fecha,//5
                                    'desecho' => $sumDesecho,//6
                                    'id_empresa' => $id_empresa,//8
                                     'temporada' => 'actual',//9,
                                     'c_productor'=>$c_productor
                                ])->save();
                            }

                        }else{


                                if($kilos_netos>0){
                                    if($categoria=='Sin Procesar'){

                                        $sumSinProcesar=$sumSinProcesar+$kilos_netos;
                                        $rec=Proceso::create([
                                            'agricola' => $agricola,//1
                                            'n_proceso' => $n_proceso,//2
                                            'especie' => $especie,//3
                                            'variedad' => $variedad,//4
                                            'fecha' => $fecha,//5
                                            'kilos_netos' => $sumSinProcesar,//6
                                            'exp' => 0,//6
                                            'comercial' => 0,//6
                                            'desecho' => 0,//6
                                            'merma' => 0,//6
                                            'id_empresa' => $id_empresa,//8
                                             'temporada' => 'actual',//9,
                                             'c_productor'=>$c_productor
                                        ]);
                                    }elseif($categoria=='Exportacion'){

                                        $sumExportacion=$sumExportacion+$kilos_netos;
                                        $rec=Proceso::create([
                                            'agricola' => $agricola,//1
                                            'n_proceso' => $n_proceso,//2
                                            'especie' => $especie,//3
                                            'variedad' => $variedad,//4
                                            'fecha' => $fecha,//5
                                            'kilos_netos' => 0,//6
                                            'exp' => $sumExportacion,//6
                                            'comercial' => 0,//6
                                            'desecho' => 0,//6
                                            'merma' => 0,//6
                                            'id_empresa' => $id_empresa,//8
                                             'temporada' => 'actual',//9,
                                             'c_productor'=>$c_productor
                                        ]);
                                    }elseif($categoria=='Mercado Interno'){
                                        $sumMercadoInterno=$sumMercadoInterno+$kilos_netos;
                                        $rec=Proceso::create([
                                            'agricola' => $agricola,//1
                                            'n_proceso' => $n_proceso,//2
                                            'especie' => $especie,//3
                                            'variedad' => $variedad,//4
                                            'fecha' => $fecha,//5
                                            'kilos_netos' => 0,//6
                                            'exp' => 0,
                                            'comercial' => $sumMercadoInterno,//6
                                            'desecho' => 0,//6
                                            'merma' => 0,//6
                                            'id_empresa' => $id_empresa,//8
                                             'temporada' => 'actual',//9,
                                             'c_productor'=>$c_productor
                                        ]);
                                    }elseif($categoria=='Desecho'){
                                            $sumDesecho=$sumDesecho+$kilos_netos;
                                            $rec=Proceso::create([
                                                'agricola' => $agricola,//1
                                                'n_proceso' => $n_proceso,//2
                                                'especie' => $especie,//3
                                                'variedad' => $variedad,//4
                                                'fecha' => $fecha,//5
                                                'kilos_netos' => 0,//6
                                                'exp' => 0,
                                                'comercial' => 0,//6
                                                'desecho' => $sumDesecho,//6
                                                'merma' => 0,//6
                                                'id_empresa' => $id_empresa,//8
                                                 'temporada' => 'actual',//9,
                                                 'c_productor'=>$c_productor
                                            ]);

                                    }
                                }

                        }

                }
                $m+=1;

            }
        }


        $rf=Proceso::all();
        $total=$rf->count()-$ri->count();
        Sync::create([
            'tipo'=>'MANUAL',
            'entidad'=>'PROCESOS',
            'fecha'=>Carbon::now(),
            'cantidad'=>$total
        ]);

        return redirect()->route('procesos.index');

    }

    // Wrapper para exponer sincronización (y dry-run) desde UI
    public function procesos_sync(Request $request)
    {
        try {
            if ($request->boolean('dry_run')) {
                $output = [];
                $created = 0; $updated = 0; $skipped = 0; $total = 0;
                $speciesStats = [];
                $dateStats = [];

                $resp = Http::post('https://api.greenexweb.cl/api/DatosProduccion');
                $rows = $resp->json() ?? [];
                foreach ($rows as $proceso) {
                    $total++;
                    $m = 0;
                    $agricola = null; $n_proceso = null; $especie = null; $variedad = null; $fecha = null; $kilos_netos = 0; $categoria = null; $id_empresa = null; $c_productor = null;
                    foreach ($proceso as $item) {
                        $m++;
                        if ($m===1) $agricola = $item;
                        if ($m===2) $n_proceso = $item;
                        if ($m===3) $especie = $item;
                        if ($m===4) $variedad = $item;
                        if ($m===5) $fecha = $item;
                        if ($m===6) $kilos_netos = (int) $item;
                        if ($m===7) $categoria = $item;
                        if ($m===8) $id_empresa = $item;
                        if ($m===9) $c_productor = $item;
                    }
                    if ($n_proceso && $id_empresa) {
                        $exists = Proceso::where('n_proceso', $n_proceso)->where('temporada','actual')->where('id_empresa', $id_empresa)->exists();
                        $sp = $especie ?: 'DESCONOCIDA';
                        if (!isset($speciesStats[$sp])) $speciesStats[$sp] = ['total'=>0,'create'=>0,'update'=>0];
                        $speciesStats[$sp]['total']++;
                        $dateKey = $fecha ? date('Y-m-d', strtotime($fecha)) : 'SIN_FECHA';
                        if (!isset($dateStats[$dateKey])) $dateStats[$dateKey] = ['total'=>0,'create'=>0,'update'=>0];
                        $dateStats[$dateKey]['total']++;
                        if ($exists) {
                            $updated++; $speciesStats[$sp]['update']++; $dateStats[$dateKey]['update']++;
                            $output[] = "Actualizar: #{$n_proceso} {$especie} {$variedad} ({$categoria})";
                        } else {
                            $created++; $speciesStats[$sp]['create']++; $dateStats[$dateKey]['create']++;
                            $output[] = "Crear: #{$n_proceso} {$especie} {$variedad} ({$categoria})";
                        }
                    } else {
                        $skipped++; $output[] = 'Fila incompleta (sin n_proceso o id_empresa) - omitida';
                    }
                }
                uasort($speciesStats, fn($a,$b) => $b['total'] <=> $a['total']);
                ksort($dateStats);
                $speciesLines = array_map(fn($name,$st) => sprintf('%s: total %d (crear %d, actualizar %d)', $name, $st['total'], $st['create'], $st['update']), array_keys($speciesStats), array_values($speciesStats));
                $dateLines = array_map(fn($d,$st) => sprintf('%s: total %d (crear %d, actualizar %d)', $d, $st['total'], $st['create'], $st['update']), array_keys($dateStats), array_values($dateStats));
                $summary = "Total filas: {$total}\nA crear: {$created}\nA actualizar: {$updated}\nOmitidas: {$skipped}\n\nResumen por especie:\n".
                    implode("\n", $speciesLines).
                    "\n\nResumen por fecha:\n".
                    implode("\n", $dateLines).
                    "\n\nDetalles (máx 200):\n".
                    implode("\n", array_slice($output,0,200));
                return redirect()->route('procesos.index')->with('success', 'Dry-run de procesos ejecutado.')->with('sync_output', $summary);
            }
            return $this->sync_proces();
        } catch (\Throwable $e) {
            Log::error('Procesos sync error: '.$e->getMessage());
            return redirect()->route('procesos.index')->with('error', 'Error al sincronizar procesos');
        }
    }
}
