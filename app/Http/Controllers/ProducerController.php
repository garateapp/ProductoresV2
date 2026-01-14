<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use App\Mail\ProducerWelcome;

class ProducerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::role('Productor');

        // Active filter (default: only active)
        $showInactive = $request->boolean('show_inactive', false);
        if (! $showInactive) {
            $query->where('is_active', true);
        }

        // Filtering
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('rut', 'like', '%'.$request->search.'%');
            });
        }

        // Sorting
        if ($request->has('sort_by') && $request->has('sort_order')) {
            $query->orderBy($request->sort_by, $request->sort_order);
        } else {
            $query->orderBy('name', 'asc'); // Default sort
        }

        // Pagination
        $producers = $query->paginate(10); // You can adjust the per-page value

        return Inertia::render('Producers/Index', [
            'producers' => $producers->through(function ($producer) {
                return [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'email' => $producer->email,
                    'rut' => $producer->rut,
                    'user' => $producer->user,
                    'idprod' => $producer->idprod,
                    'csg' => $producer->csg,
                    'emnotification' => $producer->emnotification,
                    'is_active' => (bool) $producer->is_active,
                    'kilos_netos' => $producer->kilos_netos,
                    'comercial' => $producer->comercial,
                    'desecho' => $producer->desecho,
                    'merma' => $producer->merma,
                    'exp' => $producer->exp,
                    'predio' => $producer->predio,
                    'comuna' => $producer->comuna,
                    'provincia' => $producer->provincia,
                    'direccion' => $producer->direccion,
                    'antiguedad' => $producer->antiguedad,
                    'fitosanitario' => $producer->fitosanitario,
                    'certificaciones' => $producer->certificaciones,
                    'status' => $producer->status,
                    'enviomasivo' => $producer->enviomasivo,
                ];
            }),
            'filters' => $request->all(['search', 'sort_by', 'sort_order', 'show_inactive']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Producers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'is_active' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
        ]);

        $producer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rut' => $request->rut,
            'user' => $request->user,
            'idprod' => $request->idprod,
            'csg' => $request->csg,
            'emnotification' => $request->emnotification,
            'is_active' => $request->boolean('is_active', true),
            'kilos_netos' => $request->kilos_netos,
            'comercial' => $request->comercial,
            'desecho' => $request->desecho,
            'merma' => $request->merma,
            'exp' => $request->exp,
            'predio' => $request->predio,
            'comuna' => $request->comuna,
            'provincia' => $request->provincia,
            'direccion' => $request->direccion,
            'antiguedad' => $request->antiguedad,
            'fitosanitario' => $request->fitosanitario,
            'certificaciones' => $request->certificaciones,
            'status' => $request->status,
            'enviomasivo' => $request->enviomasivo,
        ]);

        $producer->assignRole('Productor');

        if ($request->boolean('stay')) {
            return redirect()->route('producers.edit', $producer->id);
        }

        return redirect()->route('producers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $producer)
    {
        $producer->load('agronomists');
        if($producer->user=='' or $producer->user==null){
            $producer->user="gre-".$producer->rut;
        }
        return Inertia::render('Producers/Edit', [
            'producer' => [
                'id' => $producer->id,
                'name' => $producer->name,
                'email' => $producer->email,
                'rut' => $producer->rut,
                'user' => $producer->user,
                'idprod' => $producer->idprod,
                'csg' => $producer->csg,
                'emnotification' => $producer->emnotification,
                'is_active' => (bool) $producer->is_active,
                'kilos_netos' => $producer->kilos_netos,
                'comercial' => $producer->comercial,
                'desecho' => $producer->desecho,
                'merma' => $producer->merma,
                'exp' => $producer->exp,
                'predio' => $producer->predio,
                'comuna' => $producer->comuna,
                'provincia' => $producer->provincia,
                'direccion' => $producer->direccion,
                'antiguedad' => $producer->antiguedad,
                'fitosanitario' => $producer->fitosanitario,
                'certificaciones' => $producer->certificaciones,
                'status' => $producer->status,
                'enviomasivo' => $producer->enviomasivo,
                'telefonos' => $producer->telefonos()->get(),
                'agronomists' => $producer->agronomists->map(function ($agronomist) {
                    return [
                        'id' => $agronomist->id,
                        'name' => $agronomist->name,
                        'email' => $agronomist->email,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $producer)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'rut' => 'nullable|string|max:255',
            'user' => 'nullable|string|max:255',
            'idprod' => 'nullable|string|max:255',
            'csg' => 'nullable|string|max:255',
            'emnotification' => 'boolean',
            'is_active' => 'boolean',
            'kilos_netos' => 'nullable|integer',
            'comercial' => 'nullable|integer',
            'desecho' => 'nullable|integer',
            'merma' => 'nullable|integer',
            'exp' => 'nullable|integer',
            'predio' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'provincia' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'antiguedad' => 'nullable|integer',
            'fitosanitario' => 'nullable|string|max:255',
            'certificaciones' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'enviomasivo' => 'boolean',
            'send_welcome_email' => 'boolean',
            'stay' => 'boolean',
        ]);

        $producer->update([
            'name' => $request->name,
            'email' => $request->email,
            'rut' => $request->rut,
            'user' => $request->user,
            'idprod' => $request->idprod,
            'csg' => $request->csg,
            'emnotification' => $request->boolean('emnotification'),
            'is_active' => $request->boolean('is_active', $producer->is_active),
            'kilos_netos' => $request->kilos_netos,
            'comercial' => $request->comercial,
            'desecho' => $request->desecho,
            'merma' => $request->merma,
            'exp' => $request->exp,
            'predio' => $request->predio,
            'comuna' => $request->comuna,
            'provincia' => $request->provincia,
            'direccion' => $request->direccion,
            'antiguedad' => $request->antiguedad,
            'fitosanitario' => $request->fitosanitario,
            'certificaciones' => $request->certificaciones,
            'status' => $request->status,
            'enviomasivo' => $request->boolean('enviomasivo'),
        ]);

        if ($request->boolean('send_welcome_email')) {
            if (empty($producer->email)) {
                return redirect()
                    ->route('producers.edit', $producer->id)
                    ->with('error', 'El productor no tiene correo registrado.');
            }

            Mail::to($producer->email)->send(new ProducerWelcome(
                $producer,
                $producer->user ?: $producer->email,
                'gre1234',
                'https://appgreenex.cl'
            ));
        }

        if ($request->boolean('stay')) {
            return redirect()->route('producers.edit', $producer->id);
        }

        return redirect()->route('producers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $producer)
    {
        $producer->delete();

        return redirect()->route('producers.index');
    }

    public function syncActive(Request $request)
    {
        $dryRun = $request->boolean('dry_run', false);
        $params = $dryRun ? ['--dry-run' => true] : [];
        Artisan::call('producers:sync-active', $params);
        $output = Artisan::output();

        return redirect()->route('producers.index')
            ->with('success', 'Sincronización de estados ejecutada'.($dryRun ? ' (prueba)' : '').'.')
            ->with('sync_output', $output);
    }

    public function dashboard(User $producer)
    {
        $producer->load(['services']);
        $code = $producer->idprod;
        $name = $producer->name;
        $rut = $producer->rut;

        $relatedCodes = collect();

        if (! empty($rut)) {
            $relatedCodes = User::role('Productor')
                ->where('rut', $rut)
                ->pluck('idprod')
                ->filter()
                ->unique();
        }

        if (! empty($code)) {
            $relatedCodes->push($code);
        }

        $relatedCodes = $relatedCodes->filter()->unique()->values();
        $hasProcessFilter = $relatedCodes->isNotEmpty() || ! empty($name);

        $applyProcessFilters = function ($query) use ($relatedCodes, $name) {
            $query->where(function ($inner) use ($relatedCodes, $name) {
                if ($relatedCodes->isNotEmpty()) {
                    $inner->whereIn('c_productor', $relatedCodes);
                }

                if (! empty($name)) {
                    if ($relatedCodes->isNotEmpty()) {
                        $inner->orWhere('agricola', $name);
                    } else {
                        $inner->where('agricola', $name);
                    }
                }
            });
        };

        $recepciones = \App\Models\Recepcion::query()
            ->when($relatedCodes->isNotEmpty(), fn ($q) => $q->whereIn('id_emisor', $relatedCodes))
            ->when($relatedCodes->isEmpty() && ! empty($code), fn ($q) => $q->where('id_emisor', $code))
            ->orderByDesc('fecha_g_recepcion')
            ->limit(50)
            ->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','cantidad','peso_neto','informe']);

        $procesosQuery = \App\Models\Proceso::query()->where('estado', 'Finalizado');
        if ($hasProcessFilter) {
            $applyProcessFilters($procesosQuery);
        }

        $procesos = $procesosQuery
            ->orderByDesc('fecha')
            ->limit(50)
            ->get(['id','n_proceso','fecha','especie','variedad','kilos_netos','informe','exp','comercial','merma']);

        $certifications = \App\Models\ProducerCertification::with(['certifyingHouse','certificateType','especie'])
            ->where('user_id', $producer->id)
            ->orderByDesc('expiration_date')
            ->limit(100)
            ->get();

        $markets = \App\Models\CsgEspecieCountryStatus::with(['especie:id,name','country:id,name'])
            ->where('user_id', $producer->id)
            ->limit(200)
            ->get();

        $contracts = \App\Models\Contract::where('user_id', $producer->id)
            ->orderByDesc('fecha_contrato')
            ->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento','comision']);

        $recepBySpecies = collect();
        $procStackBySpecies = collect();
        $recepWeeklyBySpecies = ['weeks' => [], 'series' => []];
        $procCategoryTotals = null;

        if ($relatedCodes->isNotEmpty()) {
            $recepBySpecies = \App\Models\Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
                ->whereIn('id_emisor', $relatedCodes)
                ->groupBy('n_especie')
                ->orderByDesc('kilos')
                ->limit(10)
                ->get();

            $recepWeeklyRaw = \App\Models\Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
                ->whereIn('id_emisor', $relatedCodes)
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

        if ($hasProcessFilter) {
            $procStackQuery = \App\Models\Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho');
            $applyProcessFilters($procStackQuery);
            $procStackBySpecies = $procStackQuery
                ->groupBy('especie')
                ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))
                ->limit(10)
                ->get();

            $procTotalsQuery = \App\Models\Proceso::selectRaw('SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho');
            $applyProcessFilters($procTotalsQuery);
            $procCategoryTotals = $procTotalsQuery
                ->first();
        }
        $calibreCurve = $this->buildCalibreCurveForProducer($relatedCodes, $name);

        return Inertia::render('Producers/Dashboard', [
            'producer' => $producer,
            'recepciones' => $recepciones,
            'procesos' => $procesos,
            'certifications' => $certifications,
            'markets' => $markets,
            'contracts' => $contracts,
            'stats' => [
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

    public function sendWelcomeEmail(User $producer)
    {
        if (empty($producer->email)) {
            return redirect()
                ->route('producers.edit', $producer->id)
                ->with('error', 'El productor no tiene correo registrado.');
        }

        Mail::to($producer->email)->send(new ProducerWelcome(
            $producer,
            $producer->user ?: $producer->email,
            'gre1234',
            'https://appgreenex.cl'
        ));

        return redirect()
            ->route('producers.edit', $producer->id)
            ->with('success', 'Correo de bienvenida enviado.');
    }

    private function buildCalibreCurveForProducer($relatedCodes, $producerName): array
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
            ->whereNotIn('ppc.id_calibre', [73, 75, 104, 91, 96])
            ->when($relatedCodes->isNotEmpty() || ! empty($producerName), function ($query) use ($relatedCodes, $producerName) {
                $query->where(function ($q) use ($relatedCodes, $producerName) {
                    $applied = false;
                    if ($relatedCodes->isNotEmpty()) {
                        $q->whereIn('ppc.c_productor', $relatedCodes);
                        $applied = true;
                    }
                    if (! empty($producerName)) {
                        $applied
                            ? $q->orWhere('ppc.n_productor_proceso', $producerName)
                            : $q->where('ppc.n_productor_proceso', $producerName);
                    }
                });
            })
            ->groupBy('ppc.n_especie_proceso', 'ppc.n_variedad_proceso', 'ppc.n_calibre')
            ->get()
            ->map(function ($row) {
                if ($row->calibre !== null) {
                    $row->calibre = preg_replace('/\.$/', '', (string) $row->calibre);
                }
                return $row;
            });

        $rows = $rows->map(function ($row) {
            $row->calibre = $this->normalizeCalibreForSpecies($row->calibre, $row->especie);
            return $row;
        })->groupBy(function ($row) {
            return ($row->especie ?: 'SIN_ESPECIE').'|'.($row->variedad ?: 'SIN_VARIEDAD').'|'.($row->calibre ?? 'SIN_CALIBRE');
        })->map(function ($items) {
            $first = $items->first();
            return (object) [
                'especie' => $first->especie,
                'variedad' => $first->variedad,
                'calibre' => $first->calibre,
                'kilos' => $items->sum('kilos'),
            ];
        })->values();

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

        foreach ($calibresBySpecies as $sp => $values) {
            $calibresBySpecies[$sp] = $this->sortCalibres($values, stripos((string) $sp, 'cherr') !== false || stripos((string) $sp, 'cereza') !== false);
        }

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
        $normalized = array_map(fn ($c) => strtoupper(trim((string) $c)), $categories);
        $order = ['L','XL','J','2J','3J','4J','5J','6J','7J'];
        $isCherryDetected = $isCherry || count(array_intersect($order, $normalized)) > 0;

        $position = function (string $value) use ($order, $isCherryDetected) {
            $idx = array_search($value, $order, true);
            if ($isCherryDetected && $idx !== false) {
                return $idx;
            }
            if (is_numeric($value)) {
                return 1000 + (float) $value;
            }
            return 2000;
        };

        usort($normalized, function ($a, $b) use ($position) {
            $pa = $position($a);
            $pb = $position($b);
            if ($pa === $pb) {
                return strnatcasecmp($a, $b);
            }
            return $pa <=> $pb;
        });

        return $normalized;
    }
}
