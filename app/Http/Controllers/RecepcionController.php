<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Recepcion;

use App\Models\Calidad;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Service;
use App\Models\Variedad; // Add this line
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
        $isAdmin = method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('Administrador'));

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

        if (! $isAdmin) {
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

            $serviceProducerNames = $allServiceProducers->pluck('name')->filter()->unique();
            $serviceProducerCodes = $allServiceProducers->pluck('csg')
                ->map($normalizeProducerCode)
                ->filter()
                ->unique();
            $serviceProducerIds = $allServiceProducers->pluck('idprod')->filter()->unique();
            $isServiceUser = $serviceProducerNames->isNotEmpty() || $serviceProducerCodes->isNotEmpty() || $serviceProducerIds->isNotEmpty();
        }

        if (! $isAdmin) {
            $allowedProducerNames = collect();
            $allowedProducerCodes = collect();
            $allowedProducerIds = collect();

            if ($isServiceUser) {
                $allowedProducerNames = $serviceProducerNames;
                $allowedProducerCodes = $serviceProducerCodes;
                $allowedProducerIds = $serviceProducerIds;
            } elseif ($isProducer) {
                $allowedProducerNames = collect([$user->name])->filter();
                $allowedProducerCodes = collect([$normalizeProducerCode($user->csg)])->filter();
                $allowedProducerIds = collect([$user->idprod])->filter();
            }

            if ($allowedProducerNames->isNotEmpty() || $allowedProducerCodes->isNotEmpty() || $allowedProducerIds->isNotEmpty()) {
                $query->where(function ($q) use ($allowedProducerNames, $allowedProducerCodes, $allowedProducerIds) {
                    if ($allowedProducerNames->isNotEmpty()) {
                        $q->whereIn('n_emisor', $allowedProducerNames->all());
                    }

                    if ($allowedProducerCodes->isNotEmpty()) {
                        if ($allowedProducerNames->isNotEmpty()) {
                            $q->orWhereIn('Codigo_Sag_emisor', $allowedProducerCodes->all());
                        } else {
                            $q->whereIn('Codigo_Sag_emisor', $allowedProducerCodes->all());
                        }
                    }

                    if ($allowedProducerIds->isNotEmpty()) {
                        if ($allowedProducerNames->isNotEmpty() || $allowedProducerCodes->isNotEmpty()) {
                            $q->orWhereIn('id_emisor', $allowedProducerIds->all());
                        } else {
                            $q->whereIn('id_emisor', $allowedProducerIds->all());
                        }
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

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
                SUM(COALESCE(cantidad, 0)) AS total_cantidad,
                SUM(COALESCE(peso_neto, 0)) AS total_peso_neto,
                nota_calidad,
                n_estado,
                Id_productor_rotulado AS id_productor_rotulado,
                n_productor_rotulado,
                csg_productor_rotulado
            ")
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
                'nota_calidad',
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
        return [
            'id_g_recepcion' => $row['id_g_recepcion'],
            'tipo_g_recepcion' => $row['tipo_g_recepcion'],
            'numero_g_recepcion' => $row['numero_g_recepcion'],
            'fecha_g_recepcion' => $this->normalizeDate($row['fecha_g_recepcion']),
            'id_emisor' => $row['id_emisor'],
            'r_emisor' => $row['r_emisor'],
            'n_emisor' => $row['n_emisor'],
            'Codigo_Sag_emisor' => $row['Codigo_Sag_emisor'],
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
    }

    private function createReceptionRecord(array $row): Recepcion
    {
        $this->ensureSpeciesAndVariety($row);

        $payload = $this->buildReceptionPayload($row);
        $payload['n_estado'] = $row['n_estado'];
        $payload['temporada'] = 'actual';

        $recepcion = Recepcion::create($payload);
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
}

