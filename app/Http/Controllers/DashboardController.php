<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\Recepcion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
/*************  ✨ Windsurf Command ⭐  *************/
/**
 * Handle an incoming request.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\Response
 */
/**
 * This function renders the dashboard page based on the user's role.
 * If the user is a producer, it renders the producer's dashboard page.
 * If the user is not a producer, it renders the main dashboard page.
 * The function takes a request object as a parameter and returns a response object.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\Response
 */
/*******  77bcf021-acf4-46a7-8163-b2e78d458c8d  *******/
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return Inertia::render('Dashboard', [
                'isProducer' => false,
                'recepciones' => [],
                'procesos' => [],
                'contracts' => [],
                'certifications' => [],
                'stats' => [],
            ]);
        }

        $isProducer = ! empty($user->idprod);
        $payload = [
            'isProducer' => $isProducer,
            'recepciones' => [],
            'procesos' => [],
            'contracts' => [],
            'certifications' => [],
            'stats' => [],
            'charts' => [
                'calibreCurve' => ['categories' => [], 'series' => [], 'species' => [], 'varietiesBySpecies' => [], 'calibresBySpecies' => []],
            ],
        ];

        if ($isProducer) {
            $producerNames = collect([$user->name])->filter()->unique()->values();
            $producerCodes = collect([
                $user->csg,
                $this->normalizeProducerCode($user->csg),
                $user->idprod,
                $this->normalizeProducerCode($user->idprod),
            ])->filter()->unique()->values();
            $producerIds = collect([$user->idprod, $this->normalizeProducerCode($user->idprod)])
                ->filter()
                ->unique()
                ->values();

            $recepcionBase = Recepcion::query();
            $this->applyRecepcionFilters($recepcionBase, $producerNames, $producerCodes, $producerIds);

            $totalRecepciones = (clone $recepcionBase)->count();
            $totalKilos = (int) (clone $recepcionBase)->sum('peso_neto');
            $recentRecepciones = (clone $recepcionBase)
                ->orderByDesc('fecha_g_recepcion')
                ->limit(5)
                ->get([
                    'id',
                    'numero_g_recepcion',
                    'fecha_g_recepcion',
                    'n_especie',
                    'n_variedad',
                    'peso_neto',
                    'n_productor_rotulado',
                ]);

            $procesoBase = Proceso::query()->where('estado', 'Finalizado');
            $this->applyProcesoFilters($procesoBase, $producerNames, $producerCodes);

            $totalProcesos = (clone $procesoBase)->count();
            $totalKilosProcesados = (int) (clone $procesoBase)->sum('kilos_netos');
            $recentProcesos = (clone $procesoBase)
                ->orderByDesc('fecha')
                ->limit(5)
                ->get([
                    'id',
                    'n_proceso',
                    'fecha',
                    'especie',
                    'variedad',
                    'kilos_netos',
                    'exp',
                    'comercial',
                    'merma',
                ]);

            $contracts = Contract::where('user_id', $user->id)
                ->orderByDesc('fecha_contrato')
                ->limit(5)
                ->get(['id', 'fecha_contrato', 'vencimiento', 'comision', 'contract_file_path']);

            $certifications = ProducerCertification::with(['certifyingHouse:id,name', 'certificateType:id,name'])
                ->where('user_id', $user->id)
                ->orderByDesc('expiration_date')
                ->limit(5)
                ->get([
                    'id',
                    'certificate_code',
                    'expiration_date',
                    'audit_date',
                    'certificate_pdf_path',
                    'certifying_house_id',
                    'certificate_type_id',
                ]);

            $payload = array_merge($payload, [
                'recepciones' => $recentRecepciones,
                'procesos' => $recentProcesos,
                'contracts' => $contracts,
                'certifications' => $certifications,
                'stats' => [
                    'totalRecepciones' => $totalRecepciones,
                    'totalProcesos' => $totalProcesos,
                    'totalKilos' => $totalKilos,
                    'totalKilosProcesados' => $totalKilosProcesados,
                    'activeContracts' => $contracts->count(),
                    'activeCertifications' => $certifications->count(),
                ],
                'charts' => [
                    'calibreCurve' => $this->buildCalibreCurveForProducer($producerNames, $producerCodes),
                ],
            ]);
        }

        return Inertia::render('Dashboard', $payload);
    }

    private function normalizeProducerCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function applyRecepcionFilters($query, Collection $names, Collection $codes, Collection $ids): void
    {
        $query->where(function ($recepcionQuery) use ($names, $codes, $ids) {
            $applied = false;

            if ($ids->isNotEmpty()) {
                $recepcionQuery->whereIn('id_productor_rotulado', $ids->all());
                $applied = true;
            }

            if ($codes->isNotEmpty()) {
                $codeFilter = function ($codeQuery) use ($codes) {
                    $codeQuery->whereIn('csg_productor_rotulado', $codes->all());
                };
                if ($applied) {
                    $recepcionQuery->orWhere($codeFilter);
                } else {
                    $recepcionQuery->where($codeFilter);
                    $applied = true;
                }
            }

            if ($names->isNotEmpty()) {
                $nameFilter = function ($nameQuery) use ($names) {
                    $nameQuery->whereIn('n_productor_rotulado', $names->all());
                };
                if ($applied) {
                    $recepcionQuery->orWhere($nameFilter);
                } else {
                    $recepcionQuery->where($nameFilter);
                }
            }
        });
    }

    private function applyProcesoFilters($query, Collection $names, Collection $codes): void
    {
        $query->where(function ($procesoQuery) use ($names, $codes) {
            $applied = false;

            if ($names->isNotEmpty()) {
                $procesoQuery->whereIn('LPP_recepcion', $names->all());
                $applied = true;
            }

            if ($codes->isNotEmpty()) {
                if ($applied) {
                    $procesoQuery->orWhereIn('c_productor', $codes->all());
                } else {
                    $procesoQuery->whereIn('c_productor', $codes->all());
                }
            }
        });
    }

    private function buildCalibreCurveForProducer(Collection $allowedNames, Collection $allowedCodes): array
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
            ->when($allowedCodes->isNotEmpty() || $allowedNames->isNotEmpty(), function ($query) use ($allowedCodes, $allowedNames) {
                $query->where(function ($q) use ($allowedCodes, $allowedNames) {
                    $applied = false;
                    if ($allowedCodes->isNotEmpty()) {
                        $q->whereIn('ppc.c_productor', $allowedCodes);
                        $applied = true;
                    }
                    if ($allowedNames->isNotEmpty()) {
                        $applied
                            ? $q->orWhereIn('ppc.n_productor_proceso', $allowedNames)
                            : $q->whereIn('ppc.n_productor_proceso', $allowedNames);
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

        // Normalizar calibres (merge JD -> J, etc.) y consolidar duplicados
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
            return ['categories' => [], 'series' => [], 'species' => [], 'varietiesBySpecies' => [], 'calibresBySpecies' => []];
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
            usort($values, function ($a, $b) {
                $na = is_numeric($a) ? (float) $a : $a;
                $nb = is_numeric($b) ? (float) $b : $b;
                if (is_numeric($na) && is_numeric($nb)) {
                    return $na <=> $nb;
                }
                return strnatcasecmp((string) $na, (string) $nb);
            });
            $calibresBySpecies[$sp] = $values;
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

        // Fusionar JD, 2JD, 3JD, etc. con su equivalente sin D
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
