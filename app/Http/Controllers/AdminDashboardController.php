<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Models\Recepcion;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\Contract;
use App\Models\Detalle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $services = Service::withCount('users')->get(['id','name','owner_id']);
        $producerRecords = User::role('Productor')
            ->where('is_active', true)
            ->get(['id','name','rut','idprod','csg']);

        $producers = $producerRecords
            ->groupBy(fn ($producer) => $producer->rut ?: 'id:'.$producer->id)
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection<int, \App\Models\User> $group */
                $primary = $group->firstWhere('idprod') ?? $group->first();

                $names = $group->pluck('name')->filter()->unique()->values();
                $idprods = $group->pluck('idprod')->filter()->unique()->values();
                $csgs = $group->pluck('csg')->filter()->unique()->values();

                return [
                    'key' => $primary->rut ?: 'id:'.$primary->id,
                    'rut' => $primary->rut,
                    'name' => $names->first() ?? $primary->name,
                    'names' => $names,
                    'primary_id' => $primary->id,
                    'producer_ids' => $group->pluck('id')->values(),
                    'idprods' => $idprods,
                    'csgs' => $csgs,
                    'count' => $group->count(),
                ];
            })
            ->values();

        $recepcionesTotal = Recepcion::count();
        $procesosTotal = Proceso::count();

        $recepciones = Recepcion::orderByDesc('fecha_g_recepcion')->get(['id','numero_g_recepcion','fecha_g_recepcion','n_especie','n_variedad','n_emisor','peso_neto']);
        $procesos = Proceso::orderByDesc('fecha')->get(['id','n_proceso','fecha','especie','variedad','kilos_netos']);

        $certifications = ProducerCertification::with(['user:id,name','certifyingHouse','certificateType'])
        ->orderByDesc('expiration_date')->get();
        $contracts = Contract::with('user:id,name')
        ->orderByDesc('fecha_contrato')->get(['id','user_id','contract_file_path','fecha_contrato','vencimiento']);

        // Charts: top especies por kilos en recepciones y procesos
        $recepBySpecies = Recepcion::selectRaw('n_especie as especie, SUM(peso_neto) as kilos')
            ->groupBy('n_especie')->orderByDesc('kilos')->get();
        $procBySpecies = Proceso::selectRaw('especie, SUM(kilos_netos) as kilos')
            ->groupBy('especie')->orderByDesc('kilos')->get();

        // Stacked by species: exp, comercial, merma, desecho
        $procStackBySpecies = Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
            ->groupBy('especie')
            ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))

            ->get();
            Log::info('procStackBySpecies', ['procStackBySpecies' => Proceso::selectRaw('especie, SUM(exp) as exp, SUM(comercial) as comercial, SUM(merma) as merma, SUM(desecho) as desecho')
            ->groupBy('especie')
            ->orderByDesc(DB::raw('SUM(exp)+SUM(comercial)+SUM(merma)+SUM(desecho)'))->toSql()]);
        // Weekly kilos recepcionados por semana y especie
        $recepWeeklyRaw = Recepcion::selectRaw("DATE_FORMAT(fecha_g_recepcion, '%x-%v') as semana, n_especie as especie, SUM(peso_neto) as kilos, MIN(fecha_g_recepcion) as min_fecha")
            ->groupBy(DB::raw("DATE_FORMAT(fecha_g_recepcion, '%x-%v')"), 'n_especie')
            ->orderBy('min_fecha')
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
                'producers' => $producerRecords->count(),
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
                'calibreCurve' => $this->buildCalibreCurve(),
            ],
        ]);
    }

    private function buildCalibreCurve(): array
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
            ->whereNotIn('ppc.id_calibre', [1,73,75,104,91,96])
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
        $seriesGroups = $rows->groupBy(function ($item) {
            $especie = $item->especie ?: 'SIN ESPECIE';
            $variedad = $item->variedad ?: 'SIN VARIEDAD';

            return $especie.'|'.$variedad;
        });

        $calibresBySpecies = [];
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
            $calibresBySpecies[$sp] = $this->sortCalibres(
                $values,
                stripos((string) $sp, 'cherr') !== false || stripos((string) $sp, 'cereza') !== false
            );
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
