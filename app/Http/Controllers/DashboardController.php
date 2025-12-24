<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Proceso;
use App\Models\ProducerCertification;
use App\Models\Recepcion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
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
}
