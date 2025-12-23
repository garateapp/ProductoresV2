<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Proceso;
use App\Models\Variedad;
use App\Models\Service;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Services\ReportNotificationService;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;

class ProcesoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = ! empty($user->idprod);
       $isAdmin = method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador') || ($user->hasRole('Calidad') || $user->hasRole('Gerencia')));

        $query = Proceso::query();

        $serviceProducerNames = collect();
        $serviceProducerCodes = collect();
        $isServiceUser = false;
        $normalizeProducerCode = function ($value) {
            if ($value === null) {
                return null;
            }

            $onlyDigits = preg_replace('/[^0-9]/', '', (string) $value);

            return $onlyDigits !== '' ? $onlyDigits : null;
        };

        $ownedServiceProducers = collect();
        $memberServiceProducers = collect();
        $allServiceProducers = collect();

        if (! $isProducer) {
            $ownedServiceProducers = Service::where('owner_id', $user->id)
                ->with(['users:id,idprod,name,csg'])
                ->get()
                ->pluck('users')
                ->flatten();
            $memberServiceProducers = $user->services()
                ->with(['users:id,idprod,name,csg'])
                ->get()
                ->pluck('users')
                ->flatten();
            $allServiceProducers = $ownedServiceProducers->merge($memberServiceProducers);

            if ($allServiceProducers->isNotEmpty()) {
                $serviceProducerNames = $allServiceProducers->pluck('name')->filter()->unique()->values();
                $serviceProducerCodes = $allServiceProducers
                    ->flatMap(function ($producer) use ($normalizeProducerCode) {
                        return collect([
                            $producer->csg,
                            $normalizeProducerCode($producer->csg),
                            $producer->idprod,
                            $normalizeProducerCode($producer->idprod),
                        ]);
                    })
                    ->filter()
                    ->unique()
                    ->values();
                $isServiceUser = $serviceProducerNames->isNotEmpty() || $serviceProducerCodes->isNotEmpty();
            }
        }

        $allowedProducerNames = collect();
        $allowedProducerCodes = collect();

        if ($isServiceUser) {
            $allowedProducerNames = $serviceProducerNames;
            $allowedProducerCodes = $serviceProducerCodes;
        } elseif ($isProducer) {
            $allowedProducerNames = collect([$user->name])->filter()->unique();
            $allowedProducerCodes = collect([
                $user->csg,
                $normalizeProducerCode($user->csg),
                $user->idprod,
                $normalizeProducerCode($user->idprod),
            ])->filter()->unique();
        }

        $shouldRestrict = $isServiceUser || $isProducer;

        if ($shouldRestrict) {
            if ($allowedProducerNames->isNotEmpty() || $allowedProducerCodes->isNotEmpty()) {
                $this->applyProcesoProducerFilters($query, $allowedProducerNames, $allowedProducerCodes);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif (! $isAdmin) {
            $query->whereRaw('1 = 0');
        }

        // General search filter
        if ($request->has('search') && $request->input('search') !== '' && $request->input('search') !== null) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('especie', 'like', '%'.$searchTerm.'%')
                    ->orWhere('variedad', 'like', '%'.$searchTerm.'%')
                    ->orWhere('n_proceso', 'like', '%'.$searchTerm.'%')
                    ->orWhere('agricola', 'like', '%'.$searchTerm.'%');
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
         $query->orderBy('n_proceso', 'desc');
         Log::info($query->toSql());
        $chartData = $chartDataQuery->selectRaw('especie, SUM(exp) as exportacion, SUM(comercial) as comercial, SUM(desecho) as desecho, SUM(merma) as merma')
            ->groupBy('especie')
            ->get();

        $procesos = $query->paginate(10)->withQueryString(); // mantener filtros y pagina en los links

        // Mapear envios de email/WhatsApp del informe por proceso (solo pagina actual)
        $currentProcesses = collect($procesos->items());
        $processIds = $currentProcesses->pluck('id')->filter()->values();
        $processNumbers = $currentProcesses->pluck('n_proceso')->filter()->values();
        $idByProcessNumber = $currentProcesses
            ->filter(fn ($item) => ! empty($item->n_proceso))
            ->pluck('id', 'n_proceso');

        $logs = collect();
        if ($processIds->isNotEmpty() || $processNumbers->isNotEmpty()) {
            $logs = NotificationLog::query()
                ->where('context->channel', 'process')
                ->where(function ($q) use ($processIds, $processNumbers) {
                    if ($processIds->isNotEmpty()) {
                        $q->whereIn('context->proceso_id', $processIds);
                    }
                    if ($processNumbers->isNotEmpty()) {
                        $q->orWhereIn('context->n_proceso', $processNumbers);
                    }
                })
                ->get();
        }

        $statusByProcess = [];
        foreach ($logs as $log) {
            $context = $log->context ?? [];
            $processId = $context['proceso_id'] ?? null;
            if (! $processId && isset($context['n_proceso'])) {
                $processId = $idByProcessNumber[$context['n_proceso']] ?? null;
            }
            if (! $processId) {
                continue;
            }

            $statusByProcess[$processId] = $statusByProcess[$processId] ?? ['email' => false, 'whatsapp' => false];
            if ($log->type === 'email' && $log->status === 'success') {
                $statusByProcess[$processId]['email'] = true;
            }
            if ($log->type === 'whatsapp' && $log->status === 'success') {
                $statusByProcess[$processId]['whatsapp'] = true;
            }
        }

        $procesos->getCollection()->transform(function ($proceso) use ($statusByProcess) {
            $proceso->notifications = [
                'email_sent' => $statusByProcess[$proceso->id]['email'] ?? false,
                'whatsapp_sent' => $statusByProcess[$proceso->id]['whatsapp'] ?? false,
            ];

            return $proceso;
        });

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
    public function uploadInformes(Request $request, ReportNotificationService $reportNotificationService)
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(['Administrador', 'Admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:pdf', 'max:20480'],
        ]);

        $files = $request->file('files', []);
        $summary = [
            'processed' => count($files),
            'updated' => 0,
            'not_found' => [],
            'invalid_name' => [],
        ];

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);

            $procesoMatches = null;
            $hasCompany = false;
            if (preg_match('/^(\d+)-(\d+)-/i', $baseName, $procesoMatches)) {
                $hasCompany = true;
            } elseif (preg_match('/^(\d+)-/i', $baseName, $procesoMatches)) {
                $hasCompany = false;
            } else {
                $summary['invalid_name'][] = $originalName;
                continue;
            }

            $procesoId = (int) ($procesoMatches[1] ?? 0);
            $empresaId = $hasCompany ? (int) ($procesoMatches[2] ?? 0) : null;

            if (! $procesoId) {
                $summary['invalid_name'][] = $originalName;
                continue;
            }

            $procesoQuery = Proceso::where('n_proceso', $procesoId);
            if ($hasCompany && $empresaId) {
                $procesoQuery->where(function ($query) use ($empresaId) {
                    $query->where('id_empresa', $empresaId);

                });
            }
            $proceso = $procesoQuery->first();


            if (! $proceso) {

                $summary['not_found'][] = $originalName;

                continue;

            }

            $sanitizedName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);

            $storedPath = $file->storeAs('pdf-procesos', $sanitizedName, 'public');

            $proceso->informe = $storedPath;//Storage::disk('public')->url($storedPath);

            $proceso->save();


            $summary['updated']++;
             try {
            $reportNotificationService->notifyProcessReport(
                $proceso,
                $proceso->informe,
                basename($proceso->informe)
            );
        } catch (\Throwable $e) {
            Log::error('Process report resend failed', [
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo reenviar el informe. Intenta nuevamente.',
            ], 500);
        }
            try {
              //  $reportNotificationService->notifyProcessReport($proceso, $storedPath, $originalName);
            } catch (\Throwable $e) {
                Log::error('Proceso notification dispatch failed', [
                    'proceso_id' => $proceso->id ?? null,
                    'n_proceso' => $proceso->n_proceso ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary['not_found_preview'] = array_slice($summary['not_found'], 0, 10);
        $summary['invalid_name_preview'] = array_slice($summary['invalid_name'], 0, 10);

        $message = "Archivos procesados: {$summary['processed']}. Informes actualizados: {$summary['updated']}.";
        if (!empty($summary['not_found'])) {
            $message .= ' Sin coincidencias: ' . count($summary['not_found']) . '.';
        }
        if (!empty($summary['invalid_name'])) {
            $message .= ' Nombres invalidos: ' . count($summary['invalid_name']) . '.';
        }

        return redirect()
            ->route('procesos.index')
            ->with('success', $message)
            ->with('upload_report', $summary);
    }

    public function resendReport(Request $request, Proceso $proceso, ReportNotificationService $reportNotificationService)
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(['Administrador', 'Admin'])) {
            abort(403);
        }

        if (! $proceso->informe) {
            return response()->json([
                'message' => 'El proceso no tiene informe disponible para reenviar.',
            ], 422);
        }

        if (! Storage::disk('public')->exists($proceso->informe)) {
            return response()->json([
                'message' => 'El archivo del informe no se encuentra en el servidor.',
            ], 422);
        }

        try {
            $reportNotificationService->notifyProcessReport(
                $proceso,
                $proceso->informe,
                basename($proceso->informe)
            );
        } catch (\Throwable $e) {
            Log::error('Process report resend failed', [
                'proceso_id' => $proceso->id,
                'n_proceso' => $proceso->n_proceso,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo reenviar el informe. Intenta nuevamente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Informe reenviado correctamente al productor.',
        ]);
    }
    public function sync_proces()
    {
        // 1. Configuración de Fecha (Usando Carbon es más idiomático en Laravel)
        // Aunque la variable no se usa en la consulta SQL, es bueno usar Carbon.
        //$fecha_limite = Carbon::now()->subDays(3);

        // 2. Consulta de Datos (Usando Query Builder, corregida y simplificada)
        // El problema de tu lógica era que intentabas sumar categorías
        // agrupadas por proceso en el código PHP. Es mejor obtener los datos
        // ya consolidados por PROCESO, no por categoría.

        $procesos_data = DB::connection('sqlsrv')
            ->table('V_PKG_Produccion_Completo', 'ppc')
            ->select(
                'n_productor_proceso AS agricola',
                'c_productor',
                'numero_proceso AS n_proceso',
                'ppc.n_especie_proceso AS especie',
                'ppc.n_variedad_proceso AS variedad',
                //'ppc.LPP_recepcion',

                DB::raw("CAST(ppc.fecha_proceso AS DATE) AS fecha"), // Asegurar que es solo fecha
                'id_empresa',
                DB::raw("SUM(CASE WHEN t_categoria = 'Exportacion' THEN ppc.peso_neto ELSE 0 END) AS exp"),
                DB::raw("SUM(CASE WHEN t_categoria = 'Mercado Interno' THEN ppc.peso_neto ELSE 0 END) AS comercial"),
                DB::raw("SUM(CASE WHEN t_categoria = 'Desecho' THEN ppc.peso_neto ELSE 0 END) AS desecho"),
                DB::raw("SUM(CASE WHEN t_categoria = 'Sin Procesar' THEN ppc.peso_neto ELSE 0 END) AS kilos_netos"),
                DB::raw("GETDATE() AS FechaConsulta")
            )
            ->where('ppc.tipo_proceso', 'PRN')
            ->where('ppc.Estado', 'En Proceso')->orWhere('ppc.Estado', 'Finalizado')
            ->groupBy(
                'n_productor_proceso',
                'c_productor',
                'numero_proceso',
                'ppc.n_especie_proceso',
                'ppc.n_variedad_proceso',
                'ppc.fecha_proceso',
                'id_empresa',
                //'ppc.LPP_recepcion'
            )
            ->get();

        // 3. Inicialización para conteo de sincronización
        $registros_sincronizados = 0;

        // 4. Procesamiento de Datos (Optimización)
        foreach ($procesos_data as $proceso) {
            // Claves de búsqueda para la sincronización (únicas)
            //$proceso_actual = Proceso::where('n_proceso', $proceso->n_proceso)->where('id_empresa', $proceso->id_empresa)->();

            $update_data = [
                'agricola' => $proceso->agricola,
                'especie' => $proceso->especie,
                'variedad' => $proceso->variedad,
                'fecha' => $proceso->fecha,
                'exp' =>  (int)$proceso->exp,
                'comercial' => (int) $proceso->comercial,
                'desecho' => (int) $proceso->desecho,
                'kilos_netos' => (int) $proceso->kilos_netos,
                'c_productor' => $proceso->c_productor,
                //'LPP_recepcion' => $proceso->LPP_recepcion,
                'lote_recepcion' => 0,
            ];



            $registro = Proceso::where('n_proceso', $proceso->n_proceso)
                ->where('id_empresa', $proceso->id_empresa)
                ->where('temporada', 'actual')
                ->first();

            if (! $registro) {
                $registro = Proceso::where('n_proceso', $proceso->n_proceso)
                    ->where('id_empresa', $proceso->id_empresa)
                    ->first();
            }

            if (! $registro) {
                $registro = new Proceso([
                    'n_proceso' => $proceso->n_proceso,
                    'id_empresa' => $proceso->id_empresa,
                ]);
            }

            $registro->fill(array_merge($update_data, ['temporada' => 'actual']));
            $registro->save();
            $registros_sincronizados++;
        }

        // 5. Registro de Sincronización (Descomentar si tienes el modelo Sync)
        /*
        Sync::create([
            'tipo' => 'AUTOMATICO', // Cambiado a AUTOMATICO si es un job/tarea programada
            'entidad' => 'PROCESOS',
            'fecha' => Carbon::now(),
            'cantidad' => $registros_sincronizados,
        ]);
        */

        // 6. Retorno
        return redirect()->route('procesos.index')->with('success', "Sincronización de Procesos completada. Registros afectados: {$registros_sincronizados}");
    }


    // public function sync_proces()
    // {
    //     $fechaActual = new DateTime();

    //     // Restar 5 días a la fecha actual
    //     $fechaActual->modify('-3 days');

    //     // Formatear la fecha para mostrarla

    //     $procesos = DB::connection('sqlsrv')
    //         ->table('V_PKG_Produccion_Completo', 'ppc') // Use an alias 'ppc' for clarity and to match your original SQL
    //         ->select(
    //             'n_productor_proceso AS Agricola',
    //             'c_productor',
    //             'numero_proceso AS NProceso',
    //             'ppc.n_especie_proceso AS Especie',
    //             'ppc.n_variedad_proceso AS Variedad',
    //             'ppc.fecha_proceso AS Fecha',
    //             't_categoria AS Categoria',
    //             'id_empresa',
    //             'Estado'
    //         )
    //         ->selectRaw('SUM(ppc.peso_neto) AS Kilos_Netos')
    //         ->selectRaw('getdate() AS FechaConsulta') // Use selectRaw for SQL functions like GETDATE()
    //         ->where('ppc.tipo_proceso', 'PRN')
    //         ->where('ppc.Estado', 'En Proceso')
    //         ->groupBy(
    //             'n_productor_proceso',
    //             'c_productor',
    //             'numero_proceso',
    //             'ppc.n_especie_proceso',
    //             'ppc.n_variedad_proceso',
    //             'ppc.fecha_proceso',
    //             't_categoria',
    //             'id_empresa',
    //             'Estado'
    //         )
    //         ->get();
    //     // $procesos=Http::post('https://api.greenexweb.cl/api/DatosProduccion');
    //     // $procesos = $procesos->json();

    //     $ri=Proceso::all();
    //     $totali=$ri->count();
    //     $n_proceso_anterior=0;
    //     foreach ($procesos as $proceso){

    //         $agricola=Null;//1
    //         $n_proceso=Null;//2
    //         $especie=Null;//3
    //         $variedad=Null;//4
    //         $kilos_netos=Null;//5
    //         $categoria=Null;//6
    //         //7
    //         $id_empresa=Null;//8
    //         $c_productor=Null;

    //         $m=1;

    //         foreach ($proceso as $item){
    //             Log::info($item);
    //             if($m==1){
    //                 $agricola=$item;
    //             }
    //             if($m==2){
    //                 $n_proceso=$item;
    //                 if($n_proceso_anterior!=$n_proceso){

    //                     $n_proceso_anterior=$n_proceso;
    //                     $sumExportacion=0;
    //                     $sumSinProcesar=0;
    //                     $sumDesecho=0;
    //                     $sumMercadoInterno=0;
    //                 }
    //             }
    //             if($m==3){
    //                 $especie=$item;
    //             }
    //             if($m==4){
    //                 $variedad=$item;
    //             }
    //             if($m==5){
    //                 $fecha=$item;
    //             }
    //             if($m==6){
    //                 $kilos_netos=$item;
    //             }
    //             if($m==7){
    //                 $categoria=$item;
    //             }
    //             if($m==8){
    //                 $id_empresa=$item;
    //             }
    //             if($m==9){
    //                 $c_productor=$item;
    //             }

    //            if($m==9){
    //                     $cont=Proceso::where('n_proceso',$n_proceso)->where('temporada','actual')->where('id_empresa',$id_empresa)->first();



    //                     if($cont){


    //                         if($categoria=='Sin Procesar'){
    //                             $sumSinProcesar=$sumSinProcesar+$kilos_netos;
    //                             $cont->forceFill([
    //                                 'agricola' => $agricola,//1
    //                                 'n_proceso' => $n_proceso,//2
    //                                 'especie' => $especie,//3
    //                                 'variedad' => $variedad,//4
    //                                 'fecha' => $fecha,//5
    //                                 'kilos_netos' => $sumSinProcesar,//6
    //                                 'id_empresa' => $id_empresa,//8
    //                                  'temporada' => 'actual',//9,
    //                                  'c_productor'=>$c_productor
    //                             ])->save();
    //                         }elseif($categoria=='Exportacion'){

    //                             $sumExportacion=$sumExportacion+$kilos_netos;

    //                             $cont->forceFill([
    //                                 'agricola' => $agricola,//1
    //                                 'n_proceso' => $n_proceso,//2
    //                                 'especie' => $especie,//3
    //                                 'variedad' => $variedad,//4
    //                                 'fecha' => $fecha,//5
    //                                 'exp' => $sumExportacion,//6
    //                                 'id_empresa' => $id_empresa,//8
    //                                  'temporada' => 'actual',//9,
    //                                  'c_productor'=>$c_productor
    //                             ])->save();
    //                         }elseif($categoria=='Mercado Interno'){
    //                             $sumMercadoInterno=$sumMercadoInterno+$kilos_netos;
    //                             $cont->forceFill([
    //                                 'agricola' => $agricola,//1
    //                                 'n_proceso' => $n_proceso,//2
    //                                 'especie' => $especie,//3
    //                                 'variedad' => $variedad,//4
    //                                 'fecha' => $fecha,//5
    //                                 'comercial' => $sumMercadoInterno,//6
    //                                 'id_empresa' => $id_empresa,//8
    //                                  'temporada' => 'actual',//9,
    //                                  'c_productor'=>$c_productor
    //                             ])->save();
    //                         }elseif($categoria=='Desecho'){
    //                             $sumDesecho=$sumDesecho+$kilos_netos;
    //                             $cont->forceFill([
    //                                 'agricola' => $agricola,//1
    //                                 'n_proceso' => $n_proceso,//2
    //                                 'especie' => $especie,//3
    //                                 'variedad' => $variedad,//4
    //                                 'fecha' => $fecha,//5
    //                                 'desecho' => $sumDesecho,//6
    //                                 'id_empresa' => $id_empresa,//8
    //                                  'temporada' => 'actual',//9,
    //                                  'c_productor'=>$c_productor
    //                             ])->save();
    //                         }

    //                     }else{

    //                         Log::info(''.$agricola.' '.$n_proceso.' '.$especie.' '.$variedad.' '.$fecha.' '.$kilos_netos.' '.$categoria.' '.$id_empresa.' '.$c_productor);
    //                             if($kilos_netos>0){
    //                                 if($categoria=='Sin Procesar'){
    //                                     $sumSinProcesar=$sumSinProcesar+$kilos_netos;
    //                                     $rec=Proceso::create([
    //                                         'agricola' => $agricola,//1
    //                                         'n_proceso' => $n_proceso,//2
    //                                         'especie' => $especie,//3
    //                                         'variedad' => $variedad,//4
    //                                         'fecha' => $fecha,//5
    //                                         'kilos_netos' => $sumSinProcesar,//6
    //                                         'exp' => 0,//6
    //                                         'comercial' => 0,//6
    //                                         'desecho' => 0,//6
    //                                         'merma' => 0,//6
    //                                         'id_empresa' => $id_empresa,//8
    //                                          'temporada' => 'actual',//9,
    //                                          'c_productor'=>$c_productor
    //                                     ]);
    //                                 }elseif($categoria=='Exportacion'){
    //                                     $sumExportacion=$sumExportacion+$kilos_netos;
    //                                     Log::info(''.$sumExportacion);
    //                                     $rec=Proceso::create([
    //                                         'agricola' => $agricola,//1
    //                                         'n_proceso' => $n_proceso,//2
    //                                         'especie' => $especie,//3
    //                                         'variedad' => $variedad,//4
    //                                         'fecha' => $fecha,//5
    //                                         'kilos_netos' => 0,//6
    //                                         'exp' => $sumExportacion,//6
    //                                         'comercial' => 0,//6
    //                                         'desecho' => 0,//6
    //                                         'merma' => 0,//6
    //                                         'id_empresa' => $id_empresa,//8
    //                                          'temporada' => 'actual',//9,
    //                                          'c_productor'=>$c_productor
    //                                     ]);
    //                                 }elseif($categoria=='Mercado Interno'){
    //                                     $sumMercadoInterno=$sumMercadoInterno+$kilos_netos;
    //                                     $rec=Proceso::create([
    //                                         'agricola' => $agricola,//1
    //                                         'n_proceso' => $n_proceso,//2
    //                                         'especie' => $especie,//3
    //                                         'variedad' => $variedad,//4
    //                                         'fecha' => $fecha,//5
    //                                         'kilos_netos' => 0,//6
    //                                         'exp' => 0,
    //                                         'comercial' => $sumMercadoInterno,//6
    //                                         'desecho' => 0,//6
    //                                         'merma' => 0,//6
    //                                         'id_empresa' => $id_empresa,//8
    //                                          'temporada' => 'actual',//9,
    //                                          'c_productor'=>$c_productor
    //                                     ]);
    //                                 }elseif($categoria=='Desecho'){
    //                                         $sumDesecho=$sumDesecho+$kilos_netos;
    //                                         $rec=Proceso::create([
    //                                             'agricola' => $agricola,//1
    //                                             'n_proceso' => $n_proceso,//2
    //                                             'especie' => $especie,//3
    //                                             'variedad' => $variedad,//4
    //                                             'fecha' => $fecha,//5
    //                                             'kilos_netos' => 0,//6
    //                                             'exp' => 0,
    //                                             'comercial' => 0,//6
    //                                             'desecho' => $sumDesecho,//6
    //                                             'merma' => 0,//6
    //                                             'id_empresa' => $id_empresa,//8
    //                                              'temporada' => 'actual',//9,
    //                                              'c_productor'=>$c_productor
    //                                         ]);

    //                                 }
    //                             }

    //                     }

    //             }
    //             $m+=1;

    //         }
    //     }


    //     $rf=Proceso::all();
    //     $total=$rf->count()-$ri->count();
    //     // Sync::create([
    //     //     'tipo'=>'MANUAL',
    //     //     'entidad'=>'PROCESOS',
    //     //     'fecha'=>Carbon::now(),
    //     //     'cantidad'=>$total
    //     // ]);

    //     return redirect()->route('procesos.index');

    // }

    // Wrapper para exponer sincronización (y dry-run) desde UI
    public function procesos_sync(Request $request)
    {
        try {
            if ($request->boolean('dry_run')) {
                $output = [];
                $created = 0; $updated = 0; $skipped = 0; $total = 0;
                $speciesStats = [];
                $dateStats = [];
                 $resp = DB::connection('sqlsrv')
    ->table('V_PKG_Produccion_Completo', 'ppc') // Use an alias 'ppc' for clarity and to match your original SQL
    ->select(
        'n_productor_proceso AS Agricola',
        'c_productor',
        'numero_proceso AS NProceso',
        'ppc.n_especie_proceso AS Especie',
        'ppc.n_variedad_proceso AS Variedad',
        'ppc.fecha_proceso AS Fecha',
        't_categoria AS Categoria',
        'id_empresa',
        'Estado',
        'ppc.lote_recepcion AS lote_recepcion',
    )
    ->selectRaw('SUM(ppc.peso_neto) AS Kilos_Netos')
    ->selectRaw('getdate() AS FechaConsulta') // Use selectRaw for SQL functions like GETDATE()
    ->where('ppc.tipo_proceso', 'PRN')
    ->where('ppc.Estado', 'En Proceso')
    ->groupBy(
        'n_productor_proceso',
        'c_productor',
        'numero_proceso',
        'ppc.n_especie_proceso',
        'ppc.n_variedad_proceso',
        'ppc.fecha_proceso',
        't_categoria',
        'id_empresa',
        'lote_recepcion',
        'Estado'
    )
    ->get();
                // $resp = Http::post('https://api.greenexweb.cl/api/DatosProduccion');

                $rows = $resp ?? [];
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
            Log::error($e->getTraceAsString());
            return redirect()->route('procesos.index')->with('error', 'Error al sincronizar procesos');
        }
    }

    private function applyProcesoProducerFilters($query, $allowedNames, $allowedCodes): void
    {
        $query->where(function ($subQuery) use ($allowedNames, $allowedCodes) {
            $hasCodes = $allowedCodes->isNotEmpty();
            $hasNames = $allowedNames->isNotEmpty();

            if ($hasCodes) {
                $subQuery->whereIn('c_productor', $allowedCodes);
            }

            if ($hasNames) {
                $nameFilter = function ($nameQuery) use ($allowedNames) {
                    $nameQuery->whereIn('agricola', $allowedNames)
                        ->orWhereIn('LPP_recepcion', $allowedNames);
                };

                if ($hasCodes) {
                    $subQuery->orWhere($nameFilter);
                } else {
                    $subQuery->where($nameFilter);
                }
            }
        });
    }
}
