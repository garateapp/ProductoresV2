<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Recepcion;

use App\Models\Calidad;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Http;
use App\Models\Service;
use App\Models\Variedad; // Add this line
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RecepcionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = false;

        if (!empty($user->idprod)) {
            $isProducer = true;
        }

        $query = Recepcion::query();

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
            $query->whereIn('n_emisor', $allowedProducerIds->all());
        }
        //dd($query->toSql(), $query->getBindings());
        // Filtro por variedad, especie o lote
        if ($request->has('search') && $request->input('search') !== '' && $request->input('search') !== null) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_variedad', 'like', '%'.$searchTerm.'%')
                    ->orWhere('n_especie', 'like', '%'.$searchTerm.'%')
                    ->orWhere('id_g_recepcion', 'like', '%'.$searchTerm.'%');
                // Add lote if it exists in recepcions table
                // ->orWhere('lote', 'like', '%' . $searchTerm . '%');
            });
        }

        // Initialize variedades collection
        $variedades = collect();

        // Filtro por especie seleccionada (desde los botones)
        if ($request->has('especie_id') && $request->input('especie_id') !== '' && $request->input('especie_id') !== null) {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $query->where('n_especie', $especie->name);
                // Load varieties for the selected species
                $variedades = $especie->variedads; // Assuming Especie model has a hasMany relationship to Variedad
            }
        }

        // Filtro por variedad seleccionada (desde los botones de variedad)
        if ($request->has('variedad_id') && $request->input('variedad_id') !== '' && $request->input('variedad_id') !== null) {
            $variedad = Variedad::find($request->input('variedad_id'));
            if ($variedad) {
                $query->where('n_variedad', $variedad->name);
            }
        }
       // dd($query->toSql(), $query->getBindings());
       $query->orderBy('fecha_g_recepcion', 'desc');
        // Calculate totals before pagination
        $totalRecepciones = $query->count();
        $totalKilos = (int) $query->sum('peso_neto');

        $recepciones = $query->paginate(10); // Paginación de 10 elementos por página

        $especies = Especie::all();

        // Filtrar especies si el usuario es productor
        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            if (!empty($producerEspeciesIds)) {
                $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
            }
        }

        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones,
            'especies' => $especies->toArray(), // Convertir a array aquí
            'variedades' => $variedades, // Pass varieties to the frontend
            'filters' => $request->only(['search', 'especie_id', 'variedad_id']),
            'isProducer' => $isProducer,
            'totalRecepciones' => $totalRecepciones, // Pass total recepciones
            'totalKilos' => $totalKilos,             // Pass total kilos
        ]);

    }

    // Alias público para sincronización manual desde UI
    public function recepction_sync(Request $request)
    {
        try {
            if ($request->boolean('dry_run')) {
                $output = [];
                $created = 0; $updated = 0; $skipped = 0; $total = 0;
                $speciesStats = [];// [name => ['total'=>n,'create'=>n,'update'=>n]]
                $dateStats = [];// ['Y-m-d' => ['total'=>n,'create'=>n,'update'=>n]]
                // Obtener data origen (mismo endpoint que production_refresh)
                $resp = Http::post('https://api.greenexweb.cl/api/ObtenerRecepcion');
                $rows = $resp->json() ?? [];
                foreach ($rows as $items) {
                    $total++;
                    $i = 0;
                    $id_g_recepcion = null; $tipo_g_recepcion = null; $numero_g_recepcion = null; $fecha_g_recepcion = null; $id_emisor = null; $r_emisor = null; $n_emisor = null; $Codigo_Sag_emisor = null; $tipo_documento_recepcion = null; $numero_documento_recepcion = null; $n_especie = null; $n_variedad = null; $cantidad = 0; $peso_neto = 0; $nota_calidad = 0; $n_estado = null;
                    foreach ($items as $item) {
                        $i++;
                        switch ($i) {
                            case 1: $id_g_recepcion = $item; break;
                            case 2: $tipo_g_recepcion = $item; break;
                            case 3: $numero_g_recepcion = $item; break;
                            case 4: $fecha_g_recepcion = $item; break;
                            case 5: $id_emisor = $item; break;
                            case 6: $r_emisor = $item; break;
                            case 8: $n_emisor = $item; break;
                            case 9: $Codigo_Sag_emisor = $item; break;
                            case 10: $tipo_documento_recepcion = $item; break;
                            case 11: $numero_documento_recepcion = $item; break;
                            case 12: $n_especie = $item; break;
                            case 13: $n_variedad = $item; break;
                            case 14: $cantidad = (int) $item; break;
                            case 15: $peso_neto = (int) $item; break;
                            case 16: $nota_calidad = (int) $item; break;
                            case 17: $n_estado = $item; break;
                        }
                    }
                    if ($id_g_recepcion) {
                        $exists = Recepcion::where('id_g_recepcion', $id_g_recepcion)->where('temporada', 'actual')->exists();
                        // Especie stats
                        $sp = $n_especie ?: 'DESCONOCIDA';
                        if (!isset($speciesStats[$sp])) { $speciesStats[$sp] = ['total'=>0,'create'=>0,'update'=>0]; }
                        $speciesStats[$sp]['total']++;
                        // Fecha stats (normalizar a Y-m-d)
                        $dateKey = $fecha_g_recepcion ? (date('Y-m-d', strtotime($fecha_g_recepcion))) : 'SIN_FECHA';
                        if (!isset($dateStats[$dateKey])) { $dateStats[$dateKey] = ['total'=>0,'create'=>0,'update'=>0]; }
                        $dateStats[$dateKey]['total']++;

                        if ($exists) {
                            $updated++;
                            $speciesStats[$sp]['update']++;
                            $dateStats[$dateKey]['update']++;
                            $output[] = "Actualizar: #{$numero_g_recepcion} ({$n_especie} {$n_variedad})";
                        } else {
                            $created++;
                            $speciesStats[$sp]['create']++;
                            $dateStats[$dateKey]['create']++;
                            $output[] = "Crear: #{$numero_g_recepcion} ({$n_especie} {$n_variedad})";
                        }
                    } else {
                        $skipped++; $output[] = 'Fila sin id_g_recepcion - omitida';
                    }
                }
                // Ordenar stats por total desc
                uasort($speciesStats, function($a,$b){ return $b['total'] <=> $a['total']; });
                ksort($dateStats);
                $speciesLines = [];
                foreach ($speciesStats as $name => $st) {
                    $speciesLines[] = sprintf('%s: total %d (crear %d, actualizar %d)', $name, $st['total'], $st['create'], $st['update']);
                }
                $dateLines = [];
                foreach ($dateStats as $d => $st) {
                    $dateLines[] = sprintf('%s: total %d (crear %d, actualizar %d)', $d, $st['total'], $st['create'], $st['update']);
                }

                $summary = "Total filas: {$total}\nA crear: {$created}\nA actualizar: {$updated}\nOmitidas: {$skipped}\n\nResumen por especie:\n".
                    implode("\n", $speciesLines).
                    "\n\nResumen por fecha:\n".
                    implode("\n", $dateLines).
                    "\n\nDetalles (máx 200):\n".
                    implode("\n", array_slice($output,0,200));
                return redirect()->route('recepciones.index')->with('success', 'Dry-run ejecutado. No se aplicaron cambios.')->with('sync_output', $summary);
            }
            return $this->production_refresh();
        } catch (\Throwable $e) {
            Log::error('Recepciones sync error: '.$e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->route('recepciones.index')->with('error', 'Error al sincronizar recepciones');
        }
    }
    public function production_refresh()
    {
        $productions=Http::post('https://api.greenexweb.cl/api/ObtenerRecepcion');
        $productions = $productions->json();
        $ri=Recepcion::all();
        $totali=$ri->count();

        foreach ($productions as $production){
            $id_g_recepcion=Null;//1
            $tipo_g_recepcion=Null;//2
            $numero_g_recepcion=Null;//3
            $fecha_g_recepcion=Null;//4
            $id_emisor=Null;//5
            $r_emisor=Null;//6
            //7
            $n_emisor=Null;//8
            $Codigo_Sag_emisor=Null;//9
            $tipo_documento_recepcion=Null;//10
            $numero_documento_recepcion=Null;//11
            $n_especie=Null;//12
            $n_variedad=Null;//13
            $cantidad=Null;//14
            $peso_neto=Null;//15
            $nota_calidad=Null;//16
            $n_estado=Null;//17

            $m=1;
            foreach ($production as $item){



                if($m==2){
                    $id_g_recepcion=$item;
                }
                if($m==3){
                    $tipo_g_recepcion=$item;
                }
                if($m==4){
                    $numero_g_recepcion=$item;
                }
                if($m==5){
                    $fecha_g_recepcion=$item;
                }
                if($m==6){
                    $id_emisor=$item;
                }
                if($m==7){
                    $r_emisor=$item;
                }
                if($m==8){
                    $Codigo_Sag_emisor=$item;
                }
                if($m==9){
                    $n_emisor=$item;
                }
                if($m==11){
                    $tipo_documento_recepcion=$item;
                }
                if($m==12){
                    $numero_documento_recepcion=$item;
                }
                if($m==13){
                    $n_especie=$item;

                }
                if($m==14){
                    $n_variedad=$item;
                }
                if($m==15){
                    $cantidad=$item;
                }
                if($m==16){
                    $peso_neto=$item;
                }
                if($m==17){
                    $nota_calidad=$item;
                }
               if($m==18){
                    $n_estado=$item;

                        $espec=Especie::where('name',$n_especie)->first();
                        if($espec){
                            $espec->forceFill([
                                'name'=> $n_especie
                            ]);

                            $user=User::where('csg',$Codigo_Sag_emisor)->first();
                            if(!IS_NULL($user)){
                                $this->attachUserIfPossible($espec, ['comercializado', 'users'], $user->id);
                            }

                            $varie=Variedad::where('name',$n_variedad)->first();
                            if($varie){
                                $varie->forceFill([
                                    'name'=> $n_variedad,
                                    'especie_id='=> $espec->id
                                ]);

                            }else{
                                $varie=Variedad::create([
                                    'name'=> $n_variedad,
                                    'especie_id'=>$espec->id
                                ]);

                            }

                            if(!IS_NULL($user)){
                                if($varie){
                                    $this->attachUserIfPossible($varie, ['comercializado', 'users'], $user->id);
                                }
                            }

                        }else{
                            $especie=Especie::create([
                            'name'=> $n_especie
                            ]);
                            $user=User::where('csg',$Codigo_Sag_emisor)->first();

                            if(!IS_NULL($user)){
                                $this->attachUserIfPossible($especie, ['comercializado', 'users'], $user->id);
                            }
                            $varie=Variedad::where('name',$n_variedad)->first();
                            if($varie){
                                $varie->forceFill([
                                    'name'=> $n_variedad,
                                    'especie_id='=> $especie->id
                                ]);
                            }else{
                                $varie=Variedad::create([
                                    'name'=> $n_variedad,
                                    'especie_id'=>$especie->id
                                ]);
                            }

                            if(!IS_NULL($user)){
                                if($varie){
                                    $this->attachUserIfPossible($varie, ['comercializado', 'users'], $user->id);
                                }
                            }
                        }

                        $cont=Recepcion::where('id_g_recepcion',$id_g_recepcion)->where('temporada','actual')->first();

                        if($cont){

                            $cont->forceFill([
                                'id_g_recepcion' => $id_g_recepcion,//1
                                'tipo_g_recepcion' => $tipo_g_recepcion,//2
                                'numero_g_recepcion' => $numero_g_recepcion,//3
                                'fecha_g_recepcion' => $fecha_g_recepcion,//4
                                'id_emisor' => $id_emisor,//5
                                'r_emisor' => $r_emisor,//6
                                'n_emisor' => $n_emisor,//8
                                'Codigo_Sag_emisor' => $Codigo_Sag_emisor,//9
                                'tipo_documento_recepcion' => $tipo_documento_recepcion,//10
                                'numero_documento_recepcion' => $numero_documento_recepcion,//11
                                'n_especie' => $n_especie,//12
                                'n_variedad' => $n_variedad,
                                'cantidad' => $cantidad,
                                'peso_neto' => $peso_neto,
                                'nota_calidad' => $nota_calidad,
                                'temporada'=>'actual'

                            ])->save();
                          /*  if(IS_NULL($cont->calidad)){
                                Calidad::create([
                                    'recepcion_id'=>$cont->id
                                ]);
                            }*/
                            }
                        else{

                                $rec=Recepcion::create([
                                    'id_g_recepcion' => $id_g_recepcion,//1
                                    'tipo_g_recepcion' => $tipo_g_recepcion,//2
                                    'numero_g_recepcion' => $numero_g_recepcion,//3
                                    'fecha_g_recepcion' => $fecha_g_recepcion,//4
                                    'id_emisor' => $id_emisor,//5
                                    'r_emisor' => $r_emisor,//6
                                    'n_emisor' => $n_emisor,//8
                                    'Codigo_Sag_emisor' => $Codigo_Sag_emisor,//9
                                    'tipo_documento_recepcion' => $tipo_documento_recepcion,//10
                                    'numero_documento_recepcion' => $numero_documento_recepcion,//11
                                    'n_especie' => $n_especie,//12
                                    'n_variedad' => $n_variedad,
                                    'cantidad' => $cantidad,
                                    'peso_neto' => $peso_neto,
                                    'nota_calidad' => $nota_calidad,
                                    'n_estado' => $n_estado,
                                    'temporada'=>'actual'

                                ]);
                                Calidad::create([
                                    'recepcion_id'=>$rec->id
                                ]);

                        }

                }
                $m+=1;

            }
        }


        $rf=Recepcion::all();
        $total=$rf->count()-$ri->count();
        // Sync::create([
        //     'tipo'=>'MANUAL',
        //     'entidad'=>'RECEPCIONES',
        //     'fecha'=>Carbon::now(),
        //     'cantidad'=>$total
        // ]);

        return redirect()->route('recepciones.index');

        //return view('productors.production',compact('productions'));


    }

    private function attachUserIfPossible($model, array $relationNames, int $userId): void
    {
        if (! $model) {
            return;
        }

        foreach ($relationNames as $relation) {
            if (! method_exists($model, $relation)) {
                continue;
            }

            $relationInstance = $model->{$relation}();
            if ($relationInstance instanceof BelongsToMany) {
                $relationInstance->syncWithoutDetaching([$userId]);
                return;
            }
        }
    }
}
