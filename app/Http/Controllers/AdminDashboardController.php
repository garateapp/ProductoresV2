<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Models\Recepcion;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $services = Service::withCount('users')->get(['id','name','owner_id']);
        $producers = User::role('Productor')
            ->where('is_active', true)
            ->get(['id','name','idprod','csg']);

        $recepcionesTotal = Recepcion::count();
        $procesosTotal = Proceso::count();

        $recepciones = Recepcion::orderByDesc('fecha_g_recepcion')->limit(50)->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','peso_neto']);
        $procesos = Proceso::orderByDesc('fecha')->limit(50)->get(['id','n_proceso','fecha','especie','variedad','kilos_netos']);

        $certifications = ProducerCertification::with(['user:id,name','certifyingHouse','certificateType'])->orderByDesc('expiration_date')->limit(20)->get();
        $contracts = Contract::with('user:id,name')->orderByDesc('fecha_contrato')->limit(20)->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento']);

        // Charts: top especies por kilos en recepciones y procesos
        $recepBySpecies = Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
            ->groupBy('n_especie')->orderByDesc('kilos')->limit(10)->get();
        $procBySpecies = Proceso::selectRaw('especie, SUM(kilos_netos) as kilos')
            ->groupBy('especie')->orderByDesc('kilos')->limit(10)->get();

        // Stacked by species: exp, comercial, merma, desecho
        $procStackBySpecies = Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
            ->groupBy('especie')
            ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))
            ->limit(10)
            ->get();

        // Weekly kilos recepcionados por semana y especie
        $recepWeeklyRaw = Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
            ->groupBy(DB::raw("DATE_FORMAT(fecha_g_recepcion, '%x-%v')"), 'n_especie')
            ->orderBy('min_fecha')
            ->limit(200)
            ->get();
        $weeks = $recepWeeklyRaw->pluck('semana')->unique()->values()->all();
        sort($weeks);
        $speciesList = $recepWeeklyRaw->pluck('especie')->unique()->values();
        $recepWeeklyBySpecies = [ 'weeks' => $weeks, 'series' => [] ];
        foreach ($speciesList as $sp) {
            $points = [];
            foreach ($weeks as $wk) {
                $val = $recepWeeklyRaw->firstWhere(fn($r) => $r->especie === $sp && $r->semana === $wk);
                $points[] = $val ? (int) $val->kilos : 0;
            }
            $recepWeeklyBySpecies['series'][] = [ 'name' => $sp, 'data' => $points ];
        }

        // Pie totals by category (procesos)
        $procCategoryTotals = Proceso::selectRaw('SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')->first();

        return Inertia::render('Admin/Dashboard', [
            'services' => $services,
            'producers' => $producers,
            'recepciones' => $recepciones,
            'procesos' => $procesos,
            'certifications' => $certifications,
            'contracts' => $contracts,
            'stats' => [
                'services' => $services->count(),
                'producers' => $producers->count(),
                'recepciones' => $recepcionesTotal,
                'procesos' => $procesosTotal,
                'certifications' => ProducerCertification::count(),
                'contracts' => Contract::count(),
            ],
            'charts' => [
                'recepBySpecies' => $recepBySpecies,
                'procBySpecies' => $procBySpecies,
                'procStackBySpecies' => $procStackBySpecies,
                'recepWeeklyBySpecies' => $recepWeeklyBySpecies,
                'procCategoryTotals' => $procCategoryTotals,
            ],
        ]);
    }
}
