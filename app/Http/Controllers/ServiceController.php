<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Models\Recepcion;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\CsgEspecieCountryStatus;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::with('users', 'owner', 'phones', 'emails')->get();

        $search = $request->input('search');
        // For the index + modal flow, we need users (only active) list;
        // the modal itself will exclude users already assigned to the selected service.
        $availableUsersQuery = User::query()->where('is_active', true);

        if ($search) {
            $availableUsersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }




        $availableUsers = $availableUsersQuery->get();

        // If the authenticated user is a producer (idprod not null), load their recepciones and procesos
        $myRecepciones = collect();
        $myProcesos = collect();
        if (auth()->check() && ! empty(auth()->user()->idprod)) {
            $producerCode = auth()->user()->idprod;
            $producerName = auth()->user()->name;
            Log::info("producerCode: $producerCode, producerName: $producerName");
            $myRecepciones = \App\Models\Recepcion::query()
                ->where('id_emisor', $producerCode)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(50)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','cantidad','peso_neto','informe']);
            $myProcesos = \App\Models\Proceso::query()
                ->where('agricola', $producerName)
                ->where('estado', 'Finalizado')
                ->orderByDesc('fecha')
                ->limit(50)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma']);
        }

        return Inertia::render('Services/Index', [
            'services' => $services,
            'availableUsers' => $availableUsers,
            'filters' => [
                'search' => $search,
            ],
            'myRecepciones' => $myRecepciones,
            'myProcesos' => $myProcesos,
        ]);
    }

    public function create()
    {
        return Inertia::render('Services/Create', [
            'users' => User::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rut' => 'nullable|string|max:60',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $service = Service::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'rut' => $validated['rut'] ?? null,
                'owner_id' => $validated['owner_id'],
            ]);

            if (! empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if ($phone) { // Ensure phone is not null or empty
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            if (! empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if ($email) { // Ensure email is not null or empty
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function show(Service $service)
    {
        $service->load('users');
        $availableUsers = User::where('is_active', true)->whereDoesntHave('services', function ($query) use ($service) {
            $query->where('service_id', $service->id);
        })->get();

        // Aggregate recepciones y procesos de los usuarios asociados al servicio
        $producerCodes = $service->users->pluck('idprod')->filter()->unique()->values();
        $producerCsgs = $service->users->pluck('csg')->filter()->unique()->values();
        $producerNames = $service->users->pluck('name')->filter()->unique()->values();

        $serviceRecepciones = collect();
        if ($producerCodes->isNotEmpty()) {
            $serviceRecepciones = \App\Models\Recepcion::query()
                ->whereIn('id_emisor', $producerCodes)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(100)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','cantidad','peso_neto','informe']);
        }

        $serviceProcesos = collect();
        $hasProcesoFilters = $producerCsgs->isNotEmpty() || $producerCodes->isNotEmpty() || $producerNames->isNotEmpty();
        if ($hasProcesoFilters) {
            $serviceProcesosQuery = \App\Models\Proceso::query();
            $this->applyProcesoProducerFilters($serviceProcesosQuery, $producerCsgs, $producerCodes, $producerNames);
            $serviceProcesos = $serviceProcesosQuery
                ->orderByDesc('fecha')
                ->limit(100)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma','c_productor']);
        }

        return Inertia::render('Services/Show', [
            'service' => $service,
            'availableUsers' => $availableUsers,
            'recepciones' => $serviceRecepciones,
            'procesos' => $serviceProcesos,
        ]);
    }

    public function edit(Service $service)
    {
        $service->load('owner', 'phones', 'emails');

        return Inertia::render('Services/Edit', [
            'service' => $service,
            'users' => User::all(),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rut' => 'nullable|string|max:60',
            'owner_id' => 'required|exists:users,id',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:255',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
        ]);
        Log::info('Update Service Request:', $validated);
        DB::transaction(function () use ($validated, $service) {
            $service->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'rut' => $validated['rut'] ?? null,
                'owner_id' => $validated['owner_id'],
            ]);

            // Sync phones
            $service->phones()->delete();
            if (! empty($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    if ($phone) {
                        $service->phones()->create(['phone' => $phone]);
                    }
                }
            }

            // Sync emails
            $service->emails()->delete();
            if (! empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    if ($email) {
                        $service->emails()->create(['email' => $email]);
                    }
                }
            }
        });

        return redirect()->route('services.index');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index');
    }

    public function attachUser(Request $request, Service $service)
    {
        Log::info('Attach User Request:', $request->all());
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if the user is already attached to this service to prevent duplicate entry errors
        if (! $service->users()->where('user_id', $request->user_id)->exists()) {
            $service->users()->attach($request->user_id);
        }

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function detachUser(Service $service, User $user, Request $request)
    {
        Log::info('Detach User Request:', $request->all());
        $service->users()->detach($user->id);

        return redirect()->route('services.index', [
            'page' => $request->input('page'),
            'search' => $request->input('search'),
        ]);
    }

    public function getServiceProducers(Service $service, Request $request)
    {
        return response()->json([
            'test_message' => 'Data received successfully!',
        ]);
    }

    public function dashboard(Service $service)
    {
        $service->load('users');
        $producerIds = $service->users->pluck('id');
        $producerCodes = $service->users->pluck('idprod')->filter()->unique()->values();
        $producerCsgs = $service->users->pluck('csg')->filter()->unique()->values();
        $producerNames = $service->users->pluck('name')->filter()->unique()->values();

        $hasRecepcionFilters = $producerCodes->isNotEmpty();
        $hasProcesoFilters = $producerCsgs->isNotEmpty() || $producerCodes->isNotEmpty() || $producerNames->isNotEmpty();

        $recepciones = collect();
        if ($hasRecepcionFilters) {
            $recepciones = Recepcion::query()
                ->whereIn('id_emisor', $producerCodes)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(50)
                ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','cantidad','peso_neto','informe']);
        }

        $procesos = collect();
        if ($hasProcesoFilters) {
            $procesosQuery = Proceso::query()->where('estado', 'Finalizado');
            $this->applyProcesoProducerFilters($procesosQuery, $producerCsgs, $producerCodes, $producerNames);
            $procesos = $procesosQuery
                ->orderByDesc('fecha')
                ->limit(50)
                ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma','c_productor']);
        }

        $certifications = ProducerCertification::with(['certifyingHouse','certificateType','especie','user:id,name'])
            ->whereIn('user_id', $producerIds)
            ->orderByDesc('expiration_date')
            ->limit(100)
            ->get();

        $markets = CsgEspecieCountryStatus::with(['user:id,name','especie:id,name','country:id,name'])
            ->whereIn('user_id', $producerIds)
            ->limit(200)
            ->get();

        $contracts = Contract::whereIn('user_id', $producerIds)
            ->orderByDesc('fecha_contrato')
            ->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento','comision']);

        $recepBySpecies = collect();
        $recepWeeklyBySpecies = ['weeks' => [], 'series' => []];
        if ($hasRecepcionFilters) {
            $recepBySpecies = Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
                ->whereIn('id_emisor', $producerCodes)
                ->groupBy('n_especie')
                ->orderByDesc('kilos')
                ->limit(10)
                ->get();

            $recepWeeklyRaw = Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
                ->whereIn('id_emisor', $producerCodes)
                ->groupBy(DB::raw("DATE_FORMAT(fecha_g_recepcion, '%x-%v')"), 'n_especie')
                ->orderBy('min_fecha')
                ->limit(200)
                ->get();

            $weeks = $recepWeeklyRaw->pluck('semana')->unique()->values()->all();
            sort($weeks);
            $speciesList = $recepWeeklyRaw->pluck('especie')->unique()->values();

            $series = [];
            foreach ($speciesList as $sp) {
                $points = [];
                foreach ($weeks as $wk) {
                    $val = $recepWeeklyRaw->firstWhere(fn ($r) => $r->especie === $sp && $r->semana === $wk);
                    $points[] = $val ? (int) $val->kilos : 0;
                }
                $series[] = ['name' => $sp, 'data' => $points];
            }

            $recepWeeklyBySpecies = [
                'weeks' => $weeks,
                'series' => $series,
            ];
        }

        $procStackBySpecies = collect();
        $procCategoryTotals = null;
        if ($hasProcesoFilters) {
            $procStackQuery = Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho');
            $this->applyProcesoProducerFilters($procStackQuery, $producerCsgs, $producerCodes, $producerNames);
            $procStackBySpecies = $procStackQuery
                ->groupBy('especie')
                ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))
                ->limit(10)
                ->get();

            $procTotalsQuery = Proceso::selectRaw('SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho');
            $this->applyProcesoProducerFilters($procTotalsQuery, $producerCsgs, $producerCodes, $producerNames);
            $procCategoryTotals = $procTotalsQuery->first();
        }
        $calibreCurve = $this->buildCalibreCurveForService($producerCsgs, $producerCodes, $producerNames);
        return Inertia::render('Services/Dashboard', [
            'service' => $service,
            'recepciones' => $recepciones,
            'procesos' => $procesos,
            'certifications' => $certifications,
            'markets' => $markets,
            'contracts' => $contracts,
            'stats' => [
                'producers' => $service->users->count(),
                'recepciones' => $recepciones->count(),
                'procesos' => $procesos->count(),
                'certifications' => $certifications->count(),
                'contracts' => $contracts->count(),
            ],
            'charts' => [
                'recepBySpecies' => $recepBySpecies,
                'procStackBySpecies' => $procStackBySpecies,
                'recepWeeklyBySpecies' => $recepWeeklyBySpecies,
                'procCategoryTotals' => $procCategoryTotals,
                'calibreCurve' => $calibreCurve,
            ],
        ]);
    }

    /**
     * Apply the producer-based filters to a procesos query so charts and tables only include service users.
     */
    private function applyProcesoProducerFilters($query, $producerCsgs, $producerCodes, $producerNames): void
    {
        $query->where(function ($subQuery) use ($producerCsgs, $producerCodes, $producerNames) {
            $applied = false;
            if ($producerCsgs->isNotEmpty()) {
                $subQuery->whereIn('c_productor', $producerCsgs);
                $applied = true;
            }
            if ($producerCodes->isNotEmpty()) {
                if ($applied) {
                    $subQuery->orWhereIn('c_productor', $producerCodes);
                } else {
                    $subQuery->whereIn('c_productor', $producerCodes);
                }
                $applied = true;
            }
            if ($producerNames->isNotEmpty()) {
                if ($applied) {
                    $subQuery->orWhereIn('agricola', $producerNames);
                } else {
                    $subQuery->whereIn('agricola', $producerNames);
                }
            }
        });
    }

    private function buildCalibreCurveForService($producerCsgs, $producerCodes, $producerNames): array
    {
        $rows = DB::connection('sqlsrv')
            ->table('V_PKG_Produccion_Completo as ppc')
            ->select([
                DB::raw('ppc.n_especie_proceso AS especie'),
                DB::raw('ppc.n_variedad_proceso AS variedad'),
                DB::raw('ppc.n_calibre AS calibre'),
                DB::raw('SUM(ppc.peso_neto) as kilos'),
            ])
            ->where('ppc.tipo_proceso', 'PRN')
            ->where('ppc.estado', 'Finalizado')
            ->where('ppc.t_categoria', 'Exportacion')
            ->whereNotIn('ppc.id_calibre', [73, 75, 104, 91, 96])
            ->where(function ($query) use ($producerCsgs, $producerCodes, $producerNames) {
                $applied = false;
                if ($producerCsgs->isNotEmpty()) {
                    $query->whereIn('ppc.c_productor', $producerCsgs);
                    $applied = true;
                }
                if ($producerCodes->isNotEmpty()) {
                    $applied
                        ? $query->orWhereIn('ppc.c_productor', $producerCodes)
                        : $query->whereIn('ppc.c_productor', $producerCodes);
                    $applied = true;
                }
                if ($producerNames->isNotEmpty()) {
                    $applied
                        ? $query->orWhereIn('ppc.n_productor_proceso', $producerNames)
                        : $query->whereIn('ppc.n_productor_proceso', $producerNames);
                }
            })
            ->groupBy('ppc.n_especie_proceso', 'ppc.n_variedad_proceso', 'ppc.n_calibre')
            ->get()
            ->map(function ($row) {
                if ($row->calibre !== null) {
                    $row->calibre = preg_replace('/\.$/', '', (string) $row->calibre);
                }
                $row->calibre = $this->normalizeCalibreForSpecies($row->calibre, $row->especie);
                return $row;
            })
            ->groupBy(function ($row) {
                return ($row->especie ?: 'SIN_ESPECIE').'|'.($row->variedad ?: 'SIN_VARIEDAD').'|'.($row->calibre ?? 'SIN_CALIBRE');
            })
            ->map(function ($items) {
                $first = $items->first();
                return (object) [
                    'especie' => $first->especie,
                    'variedad' => $first->variedad,
                    'calibre' => $first->calibre,
                    'kilos' => $items->sum('kilos'),
                ];
            })
            ->values();

        if ($rows->isEmpty()) {
            return ['categories' => [], 'series' => []];
        }

        $categories = $rows->pluck('calibre')->filter()->unique()->values()->all();
        $hasCherry = $rows->contains(function ($row) {
            $sp = (string) $row->especie;
            return stripos($sp, 'cherr') !== false || stripos($sp, 'cereza') !== false;
        });
        $categories = $this->sortCalibres($categories, $hasCherry);

        $series = [];
        $calibresBySpecies = [];
        $seriesGroups = $rows->groupBy(function ($item) {
            $especie = $item->especie ?: 'SIN ESPECIE';
            $variedad = $item->variedad ?: 'SIN VARIEDAD';
            return $especie.'|'.$variedad;
        });

        foreach ($seriesGroups as $key => $items) {
            [$species, $variety] = explode('|', $key);
            $data = [];
            foreach ($categories as $calibre) {
                $match = $items->firstWhere('calibre', $calibre);
                $data[] = (float) ($match->kilos ?? 0);
            }
            $calibresBySpecies[$species] = array_values(array_unique(array_merge($calibresBySpecies[$species] ?? [], $items->pluck('calibre')->filter()->all())));
            $calibresBySpecies[$species] = $this->sortCalibres($calibresBySpecies[$species], stripos((string) $species, 'cherr') !== false || stripos((string) $species, 'cereza') !== false);
            $series[] = [
                'name' => trim($species.' - '.$variety),
                'especie' => $species,
                'variedad' => $variety,
                'data' => $data,
            ];
        }

        $speciesList = collect($series)->pluck('especie')->unique()->values()->all();
        $varietiesBySpecies = collect($series)
            ->groupBy('especie')
            ->map(fn ($items) => $items->pluck('variedad')->unique()->values()->all())
            ->toArray();

        return [
            'categories' => $categories,
            'series' => $series,
            'species' => $speciesList,
            'varietiesBySpecies' => $varietiesBySpecies,
            'calibresBySpecies' => $calibresBySpecies,
        ];
    }

    private function normalizeCalibreForSpecies($calibre, $species)
    {
        $normalized = trim((string) $calibre);
        $isCherry = stripos((string) $species, 'cherr') !== false || stripos((string) $species, 'cereza') !== false;

        if (! $isCherry || $normalized === '') {
            return $normalized;
        }

        $normalized = str_ireplace('XLD', 'XL', $normalized);
        $normalized = str_ireplace('LD', 'L', $normalized);
        $normalized = str_ireplace('JD', 'J', $normalized);
        $normalized = preg_replace('/\s+/', '', $normalized);

        return strtoupper($normalized);
    }

    private function sortCalibres(array $categories, bool $isCherry): array
    {
        $normalized = array_map(function ($c) {
            return strtoupper(trim((string) $c));
        }, $categories);

        $order = ['L','XL','J','2J','3J','4J','5J','6J','7J'];
        $isCherryDetected = $isCherry || count(array_intersect($order, $normalized)) > 0;

        if ($isCherryDetected) {
            usort($normalized, function ($a, $b) use ($order) {
                $ia = array_search($a, $order, true);
                $ib = array_search($b, $order, true);
                if ($ia === false && $ib === false) {
                    return strnatcasecmp($a, $b);
                }
                if ($ia === false) return 1;
                if ($ib === false) return -1;
                return $ia <=> $ib;
            });
            return $normalized;
        }

        usort($normalized, function ($a, $b) {
            $na = is_numeric($a) ? (float) $a : $a;
            $nb = is_numeric($b) ? (float) $b : $b;
            if (is_numeric($na) && is_numeric($nb)) {
                return $na <=> $nb;
            }
            return strnatcasecmp($a, $b);
        });

        return $normalized;
    }
}
