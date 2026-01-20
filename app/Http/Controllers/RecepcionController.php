<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Recepcion;

use App\Models\Calidad;
use App\Models\NotificationLog;
use App\Services\ReportNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Service;
use App\Models\Variedad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class RecepcionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isProducer = ! empty($user->idprod);
        $isAdmin = method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador') || ($user->hasRole('Calidad') || $user->hasRole('Gerencia')));
        $canSeeNotifications = method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador') || $user->hasRole('Calidad'));
        $isExportadoraAdmin = method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador'));
        $isAgronomo = method_exists($user, 'hasRole') && ($user->hasRole('Agronomo') || $user->hasRole('Agrónomo'));
        $isCC=method_exists($user, 'hasRole') && ($user->hasRole('Calidad') || $user->hasRole('Gerencia'));
        $query = Recepcion::query();

        $serviceProducerNames = collect();
        $serviceProducerCodes = collect();
        $serviceProducerIds = collect();
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
                $serviceProducerIds = $allServiceProducers->pluck('idprod')
                    ->merge($allServiceProducers->pluck('idprod')->map($normalizeProducerCode))
                    ->filter()
                    ->unique()
                    ->values();
                $isServiceUser = $serviceProducerNames->isNotEmpty() || $serviceProducerCodes->isNotEmpty() || $serviceProducerIds->isNotEmpty();
            }
        }

        $allowedProducerNames = collect();
        $allowedProducerCodes = collect();
        $allowedProducerIds = collect();

        if ($isServiceUser) {
            $allowedProducerNames = $serviceProducerNames;
            $allowedProducerCodes = $serviceProducerCodes;
            $allowedProducerIds = $serviceProducerIds;
        } elseif ($isProducer) {
            $allowedProducerNames = collect([$user->name])->filter()->unique();
            $allowedProducerCodes = collect([
                $user->csg,
                $normalizeProducerCode($user->csg),
                $user->idprod,
                $normalizeProducerCode($user->idprod),
            ])->filter()->unique();
            $allowedProducerIds = collect([$user->idprod, $normalizeProducerCode($user->idprod)])
                ->filter()
                ->unique();
        }

        $shouldRestrict = $isServiceUser || $isProducer;

        if ($shouldRestrict) {
            if ($allowedProducerNames->isNotEmpty() || $allowedProducerCodes->isNotEmpty() || $allowedProducerIds->isNotEmpty()) {
                $this->applyRecepcionProducerFilters($query, $allowedProducerNames, $allowedProducerCodes, $allowedProducerIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($isAgronomo) {
            $query->whereRaw('LOWER(exportadora) = ?', [mb_strtolower('Greenex Spa')]);
        } elseif (! $isAdmin) {
            $query->whereRaw('1 = 0');
        }

        // Filtro por variedad, especie o lote
        if ($request->has('search') && $request->input('search') !== '' && $request->input('search') !== null) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('n_variedad', 'like', '%'.$searchTerm.'%')
                    ->orWhere('n_especie', 'like', '%'.$searchTerm.'%')
                    ->orWhere('numero_g_recepcion', 'like', '%'.$searchTerm.'%')
                    ->orWhere('n_productor_rotulado', 'like', '%'.$searchTerm.'%');
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

        $exportadoraFilter = $request->input('exportadora');
        if ($isExportadoraAdmin && $exportadoraFilter) {
            $query->where('exportadora', $exportadoraFilter);
        }
       // dd($query->toSql(), $query->getBindings());
       $query->orderBy('fecha_g_recepcion', 'desc');
       Log::info($query->toSql(), $query->getBindings());
        // Calculate totals before pagination
        $totalRecepciones = $query->count();
        $totalKilos = (int) $query->sum('peso_neto');

        $recepciones = $query->paginate(10)->withQueryString(); // Paginaci¢n de 10 elementos por p gina

        if ($canSeeNotifications) {
            $recepcionIds = $recepciones->pluck('id')->filter()->values();
            $notificationMap = [];

            if ($recepcionIds->isNotEmpty()) {
                $logs = NotificationLog::query()
                    ->where('status', 'success')
                    ->where('context->channel', 'recepcion')
                    ->whereIn('context->recepcion_id', $recepcionIds->all())
                    ->get(['type', 'context']);

                foreach ($logs as $log) {
                    $recepcionId = (int) ($log->context['recepcion_id'] ?? 0);
                    if (! $recepcionId) {
                        continue;
                    }

                    $notificationMap[$recepcionId] = $notificationMap[$recepcionId] ?? [
                        'email_sent' => false,
                        'whatsapp_sent' => false,
                    ];

                    if ($log->type === 'email') {
                        $notificationMap[$recepcionId]['email_sent'] = true;
                    }

                    if ($log->type === 'whatsapp') {
                        $notificationMap[$recepcionId]['whatsapp_sent'] = true;
                    }
                }
            }

            $recepciones->getCollection()->transform(function ($recepcion) use ($notificationMap) {
                $recepcion->notifications = $notificationMap[$recepcion->id] ?? [
                    'email_sent' => false,
                    'whatsapp_sent' => false,
                ];

                return $recepcion;
            });
        }
 // Paginación de 10 elementos por página

        $especies = Especie::all();

        // Filtrar especies si el usuario es productor
        if ($isProducer) {
            $producerEspeciesIds = $user->especies()->pluck('especie_id')->toArray();
            if (!empty($producerEspeciesIds)) {
                $especies = $especies->whereIn('id', $producerEspeciesIds)->values();
            }
        }

        $exportadoras = [];
        if ($isExportadoraAdmin) {
            $exportadoras = Recepcion::query()
                ->select('exportadora')
                ->whereNotNull('exportadora')
                ->distinct()
                ->orderBy('exportadora')
                ->pluck('exportadora')
                ->values()
                ->all();
        }
        return Inertia::render('Recepciones/Index', [
            'recepciones' => $recepciones,
            'especies' => $especies->toArray(), // Convertir a array aquí
            'variedades' => $variedades, // Pass varieties to the frontend
            'exportadoras' => $exportadoras,
            'filters' => $request->only(['search', 'especie_id', 'variedad_id', 'exportadora']),
            'isProducer' => $isProducer,
            'totalRecepciones' => $totalRecepciones, // Pass total recepciones
            'totalKilos' => $totalKilos,             // Pass total kilos
        ]);

    }

    // Alias público para sincronización manual desde UI
    public function recepction_sync(Request $request)
    {
        try {
            $rows = $this->fetchReceptionRows();

            if ($request->boolean('dry_run')) {
                $stats = $this->processReceptionRows($rows, true);

                $speciesLines = [];
                foreach ($stats['species'] as $name => $data) {
                    $speciesLines[] = sprintf('%s: total %d (crear %d, actualizar %d)', $name, $data['total'], $data['create'], $data['update']);
                }

                $dateLines = [];
                foreach ($stats['dates'] as $date => $data) {
                    $dateLines[] = sprintf('%s: total %d (crear %d, actualizar %d)', $date, $data['total'], $data['create'], $data['update']);
                }

                $summary = sprintf(
                    "Total filas: %d\nA crear: %d\nA actualizar: %d\nOmitidas: %d\n\nResumen por especie:\n%s\n\nResumen por fecha:\n%s\n\nDetalles (máx 200):\n%s",
                    $stats['total'],
                    $stats['created'],
                    $stats['updated'],
                    $stats['skipped'],
                    implode("\n", $speciesLines),
                    implode("\n", $dateLines),
                    implode("\n", array_slice($stats['details'], 0, 200))
                );

                return redirect()->route('recepciones.index')
                    ->with('success', 'Dry-run ejecutado. No se aplicaron cambios.')
                    ->with('sync_output', $summary);
            }

            $stats = $this->processReceptionRows($rows, false);
             if (!$request->boolean('dry_run')) {
                $recep=Recepcion::where('nota_calidad',0)->get();

                    foreach($recep as $row){
                    $nota = DB::connection('sqlsrv')
                    ->table('PKG_G_Recepcion')
                    ->where('numero_i', $row->numero_g_recepcion)   // acceso con ->
                    ->value('nota_calidad');                         // <- clave

                    if (!is_null($nota)) {
                        // Asumiendo que el campo en Recepcion se llama 'nota_calidad'
                        Log::debug("Nota de calidad para la recepcion {$row->numero_g_recepcion}: {$nota}");
                        $row->nota_calidad = $nota;
                        $row->save(); // guarda el cambio
                    }
                }
            }
            return redirect()->route('recepciones.index')
                ->with('success', sprintf(
                    'Recepciones sincronizadas. Creadas: %d, actualizadas: %d.',
                    $stats['created'],
                    $stats['updated']
                ));
        } catch (\Throwable $e) {
            Log::error('Recepciones sync error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->route('recepciones.index')->with('error', 'Error al sincronizar recepciones');
        }
    }

    public function sendWhatsappTest(Request $request, Recepcion $recepcion, ReportNotificationService $notificationService)
    {
        $user = $request->user();
        if (! $user || strtolower((string) $user->email) !== 'carlos.alvarez@greenex.cl') {
            abort(403);
        }

        if (! $recepcion->informe) {
            return response()->json([
                'message' => 'La recepcion no tiene informe disponible.',
            ], 422);
        }

        $notificationService->sendReceptionWhatsappToPhone(
            $recepcion,
            $recepcion->informe,
            '56966291494'
        );

        return response()->json([
            'message' => 'Mensaje WhatsApp enviado.',
        ]);
    }

    public function production_refresh()
    {
        $beforeCount = Recepcion::count();
        $rows = $this->fetchReceptionRows();

        $stats = $this->processReceptionRows($rows, false);

        $afterCount = Recepcion::count();
        $difference = $afterCount - $beforeCount;


        return redirect()->route('recepciones.index')
            ->with('success', sprintf(
                'Recepciones sincronizadas. Creadas: %d, actualizadas: %d. Diferencia total: %d.',
                $stats['created'],
                $stats['updated'],
                $difference
            ));
    }

    private function fetchReceptionRows()
    {

        $cutoff = Carbon::now()->subDays(1)->format('Y-m-d');

        return DB::connection('sqlsrv')
            ->table('V_PKG_Recepcion_FG')
            ->selectRaw("
                id_empresa,
                id_g_recepcion,
                tipo_g_recepcion,
                numero_g_recepcion,
                fecha_g_recepcion,
                id_emisor,
                r_emisor,
                c_emisor,
                n_emisor,
                Codigo_Sag_emisor,
                tipo_documento_recepcion,
                numero_documento_recepcion,
                n_especie,
                n_variedad,
                n_exportadora,
                SUM(COALESCE(cantidad, 0)) AS total_cantidad,
                SUM(COALESCE(peso_neto, 0)) AS total_peso_neto,
                n_estado,
                Id_productor_rotulado AS id_productor_rotulado,
                n_productor_rotulado,
                csg_productor_rotulado
            ")
            ->whereRaw('ISDATE(fecha_g_recepcion) = 1 AND CONVERT(date, fecha_g_recepcion, 120) >= ?', [$cutoff])
            ->groupBy(
                'id_empresa',
                'id_g_recepcion',
                'tipo_g_recepcion',
                'numero_g_recepcion',
                'fecha_g_recepcion',
                'id_emisor',
                'r_emisor',
                'c_emisor',
                'n_emisor',
                'Codigo_Sag_emisor',
                'tipo_documento_recepcion',
                'numero_documento_recepcion',
                'n_especie',
                'n_variedad',
                'n_exportadora',
                'n_estado',
                'Id_productor_rotulado',
                'n_productor_rotulado',
                'csg_productor_rotulado'
            )
            ->orderBy('id_g_recepcion', 'ASC')
            ->get();
    }

    private function normalizeReceptionRow($row): array
    {
        $data = (array) $row;

        return [
            'id_g_recepcion' => $data['id_g_recepcion'] ?? null,
            'tipo_g_recepcion' => $data['tipo_g_recepcion'] ?? null,
            'numero_g_recepcion' => $data['numero_g_recepcion'] ?? null,
            'fecha_g_recepcion' => $this->normalizeDate($data['fecha_g_recepcion'] ?? null),
            'id_emisor' => $data['id_emisor'] ?? null,
            'r_emisor' => $data['r_emisor'] ?? null,
            'n_emisor' => $data['n_emisor'] ?? null,
            'Codigo_Sag_emisor' => $data['Codigo_Sag_emisor'] ?? null,
            'tipo_documento_recepcion' => $data['tipo_documento_recepcion'] ?? null,
            'numero_documento_recepcion' => $data['numero_documento_recepcion'] ?? null,
            'n_especie' => $data['n_especie'] ?? null,
            'n_variedad' => $data['n_variedad'] ?? null,
            'exportadora' => $data['n_exportadora'] ?? null,
            'cantidad' => (int) ($data['total_cantidad'] ?? $data['cantidad'] ?? 0),
            'peso_neto' => (int) ($data['total_peso_neto'] ?? $data['peso_neto'] ?? 0),
            'nota_calidad' => isset($data['nota_calidad']) ? (int) $data['nota_calidad'] : 0,
            'n_estado' => $data['n_estado'] ?? null,
            'id_productor_rotulado' => $data['id_productor_rotulado'] ?? null,
            'n_productor_rotulado' => $data['n_productor_rotulado'] ?? null,
            'csg_productor_rotulado' => $data['csg_productor_rotulado'] ?? null,
        ];
    }

    private function normalizeDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return is_string($value) ? $value : null;
        }
    }

    private function processReceptionRows($rows, bool $dryRun): array
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'species' => [],
            'dates' => [],
            'details' => [],
        ];

        foreach ($rows as $rowData) {
            $row = $this->normalizeReceptionRow($rowData);
            $stats['total']++;

            if (! $row['id_g_recepcion']) {
                $stats['skipped']++;
                $stats['details'][] = 'Fila sin id_g_recepcion - omitida';
                continue;
            }

            $speciesKey = $row['n_especie'] ?: 'DESCONOCIDA';
            if (! isset($stats['species'][$speciesKey])) {
                $stats['species'][$speciesKey] = ['total' => 0, 'create' => 0, 'update' => 0];
            }
            $stats['species'][$speciesKey]['total']++;

            $dateKey = 'SIN_FECHA';
            if ($row['fecha_g_recepcion']) {
                try {
                    $dateKey = Carbon::parse($row['fecha_g_recepcion'])->format('Y-m-d');
                } catch (\Exception $e) {
                    $dateKey = (string) $row['fecha_g_recepcion'];
                }
            }
            if (! isset($stats['dates'][$dateKey])) {
                $stats['dates'][$dateKey] = ['total' => 0, 'create' => 0, 'update' => 0];
            }
            $stats['dates'][$dateKey]['total']++;

            $existing = Recepcion::where('id_g_recepcion', $row['id_g_recepcion'])
                ->where('temporada', 'actual')
                ->first();

            if ($existing) {
                $stats['updated']++;
                $stats['species'][$speciesKey]['update']++;
                $stats['dates'][$dateKey]['update']++;
                $stats['details'][] = sprintf(
                    'Actualizar: #%s (%s %s)',
                    $row['numero_g_recepcion'] ?? 'N/A',
                    $row['n_especie'] ?? 'N/A',
                    $row['n_variedad'] ?? 'N/A'
                );

                if (! $dryRun) {
                    $this->updateReceptionRecord($existing, $row);
                }
            } else {
                $stats['created']++;
                $stats['species'][$speciesKey]['create']++;
                $stats['dates'][$dateKey]['create']++;
                $stats['details'][] = sprintf(
                    'Crear: #%s (%s %s)',
                    $row['numero_g_recepcion'] ?? 'N/A',
                    $row['n_especie'] ?? 'N/A',
                    $row['n_variedad'] ?? 'N/A'
                );

                if (! $dryRun) {
                    $this->createReceptionRecord($row);
                }
            }
        }

        ksort($stats['dates']);
        uasort($stats['species'], function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $stats;
    }

    private function ensureSpeciesAndVariety(array $row): void
    {
        if (! $row['n_especie']) {
            return;
        }

        $especie = Especie::firstOrCreate(['name' => $row['n_especie']], ['name' => $row['n_especie']]);
        if ($especie->name !== $row['n_especie']) {
            $especie->forceFill(['name' => $row['n_especie']])->save();
        }

        $user = null;
        if (! empty($row['Codigo_Sag_emisor'])) {
            $user = User::where('csg', $row['Codigo_Sag_emisor'])->first();
        }

        if ($user) {
            $this->attachUserIfPossible($especie, ['comercializado', 'users'], $user->id);
        }

        if ($row['n_variedad']) {
            $varie = Variedad::firstOrCreate(
                ['name' => $row['n_variedad']],
                ['especie_id' => $especie->id]
            );

            if ($varie->especie_id !== $especie->id) {
                $varie->forceFill(['especie_id' => $especie->id])->save();
            }

            if ($user) {
                $this->attachUserIfPossible($varie, ['comercializado', 'users'], $user->id);
            }
        }
    }

    private function buildReceptionPayload(array $row): array
    {
        //Log::info("row:",[$row]);
        return [
            'id_g_recepcion' => $row['id_g_recepcion'],
            'tipo_g_recepcion' => $row['tipo_g_recepcion'],
            'numero_g_recepcion' => $row['numero_g_recepcion'],
            'fecha_g_recepcion' => $this->normalizeDate($row['fecha_g_recepcion']),
            'id_emisor' => $row['id_emisor'],
            'r_emisor' => $row['r_emisor'],
            'n_emisor' => $row['n_emisor'],
            'Codigo_Sag_emisor' => $row['Codigo_Sag_emisor'],
            'exportadora' => $row['exportadora'],
            'id_productor_rotulado' => $row['id_productor_rotulado'],
            'n_productor_rotulado' => $row['n_productor_rotulado'],
            'csg_productor_rotulado' => $row['csg_productor_rotulado'],
            'tipo_documento_recepcion' => $row['tipo_documento_recepcion'],
            'numero_documento_recepcion' => $row['numero_documento_recepcion'],
            'n_especie' => $row['n_especie'],
            'n_variedad' => $row['n_variedad'],
            'cantidad' => $row['cantidad'],
            'peso_neto' => $row['peso_neto'],
            'nota_calidad' => $row['nota_calidad'],
        ];
    }

    private function updateReceptionRecord(Recepcion $recepcion, array $row): void
    {
        $this->ensureSpeciesAndVariety($row);

        $payload = $this->buildReceptionPayload($row);
        $recepcion->forceFill($payload)->save();
         $user=User::where('csg',$row['Codigo_Sag_emisor'])->first();
        if($user){
            $user->is_active=1;
            $user->save();
        }
    }

    private function createReceptionRecord(array $row): Recepcion
    {
        $this->ensureSpeciesAndVariety($row);

        $payload = $this->buildReceptionPayload($row);
        $payload['n_estado'] = $row['n_estado'];
        $payload['temporada'] = 'actual';

        $recepcion = Recepcion::create($payload);
        $user=User::where('csg',$row['Codigo_Sag_emisor'])->first();
        if($user){
            $user->is_active=1;
            $user->save();
        }
        Calidad::create(['recepcion_id' => $recepcion->id]);

        return $recepcion;
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

    private function applyRecepcionProducerFilters($query, $allowedNames, $allowedCodes, $allowedIds): void
    {
        $query->where(function ($subQuery) use ($allowedNames, $allowedCodes, $allowedIds) {
            $applied = false;

            // if ($allowedIds->isNotEmpty()) {
            //     $ids = $allowedIds->all();
            //     $subQuery->whereIn('id_productor_rotulado', $ids);
            //     $applied = true;
            // }

            // if ($allowedCodes->isNotEmpty()) {
            //     $codes = $allowedCodes->all();
            //     $codeFilter = function ($codeQuery) use ($codes) {
            //         $codeQuery->whereIn('csg_productor_rotulado', $codes);
            //     };

            //     if ($applied) {
            //         $subQuery->orWhere($codeFilter);
            //     } else {
            //         $subQuery->where($codeFilter);
            //         $applied = true;
            //     }
            // }

            if ($allowedNames->isNotEmpty()) {
                $names = $allowedNames->all();
                $nameFilter = function ($nameQuery) use ($names) {
                    $nameQuery->whereIn('n_productor_rotulado', $names);
                };

                if ($applied) {
                    $subQuery->orWhere($nameFilter);
                } else {
                    $subQuery->where($nameFilter);
                }
            }
        });
    }
}
