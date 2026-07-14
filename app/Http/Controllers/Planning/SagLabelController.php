<?php

namespace App\Http\Controllers\Planning;

use App\Enums\PlanningProcessStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Mail\PlanningSagLabelMail;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SagLabelController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request): Response
    {
        $this->authorizePlanning($request);

        $processes = PackingProcess::query()
            ->with(['shift', 'lots'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(100)
            ->through(function (PackingProcess $process): array {
                $firstLot = $process->lots->first();

                return [
                    'id' => (int) $process->id,
                    'fecha' => $process->fecha?->toDateString(),
                    'especie' => (string) ($process->especie ?? ''),
                    'estado' => $process->estado?->value ?? $process->estado,
                    'shift' => $process->shift ? [
                        'id' => (int) $process->shift->id,
                        'codigo' => (string) ($process->shift->codigo ?? ''),
                        'nombre' => (string) ($process->shift->nombre ?? ''),
                    ] : null,
                    'first_lot' => $firstLot ? [
                        'n_g_recepcion' => (string) ($firstLot->n_g_recepcion ?? ''),
                        'producer' => (string) ($firstLot->n_productor ?? ''),
                        'variedad' => (string) ($firstLot->n_variedad ?? ''),
                        'csg' => (string) ($firstLot->csg_productor ?? ''),
                        'sdp' => (string) ($firstLot->sdp_centrocosto ?? ''),
                    ] : null,
                ];
            });

        return Inertia::render('Planning/SagLabels/Index', [
            'processes' => $processes,
        ]);
    }

    public function send(Request $request, PackingProcess $process): RedirectResponse
    {
        $this->authorizePlanning($request);

        $process->loadMissing(['shift', 'lots.packingLine']);

        $status = $process->estado?->value ?? $process->estado;
        if ($status !== PlanningProcessStatus::CONFIRMADO->value) {
            return redirect()
                ->route('planning.sag-labels.index')
                ->with('error', 'El proceso no cumple condiciones para enviar etiqueta.');
        }

        $recipient = trim((string) config('planning.sag_label_email'));
        if ($recipient === '') {
            return redirect()
                ->route('planning.sag-labels.index')
                ->with('error', 'No hay correo SAG configurado.');
        }

        $firstLot = $process->lots->sortBy([
            ['orden', 'asc'],
            ['id', 'asc'],
        ])->first();

        if (! $firstLot) {
            return redirect()
                ->route('planning.sag-labels.index')
                ->with('error', 'El proceso no cumple condiciones para enviar etiqueta.');
        }

        try {
            $label = $this->buildSagLabelPayload($process, $firstLot);
            Mail::to($recipient)->send(new PlanningSagLabelMail($label));
        } catch (\Throwable $exception) {
            Log::warning('Planning SAG label email failed', [
                'process_id' => $process->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('planning.sag-labels.index')
                ->with('error', 'No fue posible enviar la etiqueta SAG.');
        }

        return redirect()
            ->route('planning.sag-labels.index')
            ->with('success', 'Etiqueta enviada a SAG correctamente.');
    }

    private function buildSagLabelPayload(PackingProcess $process, PackingProcessLot $lot): array
    {
        $producer = $this->resolveProducer($lot);
        $producerEntity = $this->resolveRemoteProducerEntity($producer);
        $exporterEntity = $this->resolveRemoteExporterEntity($producer);
        $packingEntity = $this->resolveRemoteEntityByTypeAndRut('Packing', '760678619');

        $lineName = trim((string) ($lot->packingLine?->nombre ?? ''));
        $shiftCode = trim((string) ($process->shift?->codigo ?? ''));
        $shiftName = trim((string) ($process->shift?->nombre ?? ''));
        $packedDate = $process->fecha?->format('d-m-Y') ?? '-';
        $packedTime = $lot->inicio_estimado?->format('H:i') ?: ((string) ($process->shift?->hora_inicio ?? '') !== '' ? substr((string) $process->shift?->hora_inicio, 0, 5) : '-');
        $processNumber = (string) ($process->id ?? '');
        $boxNumber = 'PROC-'.$processNumber;
        $species = $this->coalesceLabelValue([$process->especie ?? null]);
        $variety = $this->coalesceLabelValue([$lot->n_variedad ?? null]);
        $packagingCode = $this->coalesceLabelValue([$lot->c_embalaje ?? null]);
        $packagingName = $this->coalesceLabelValue([$lot->n_embalaje ?? null]);
        $sizeCode = $this->coalesceLabelValue([$lot->setup_calibre ?? null]);
        $category = $this->coalesceLabelValue([$lot->categoria_origen ?? null, $lot->source_categoria ?? null]);
        $netWeight = $lot->peso_neto !== null ? rtrim(rtrim(number_format((float) $lot->peso_neto, 3, ',', '.'), '0'), ',') : '-';
        $producerName = $this->coalesceLabelValue([
            $producerEntity?->nombre ?? null,
            $lot->n_productor ?? null,
            $producer?->name ?? null,
        ]);
        $csgCode = $this->coalesceLabelValue([
            $producerEntity?->codigo_sag ?? null,
            $producerEntity?->csg ?? null,
            $lot->csg_productor ?? null,
            $producer?->csg ?? null,
        ]);
        $sdpCode = $this->coalesceLabelValue([$lot->sdp_centrocosto ?? null]);
        $ggnCode = '';
        // $this->coalesceLabelValue([
        //     $lot->id_productor ?? null,
        //     $producerEntity?->rut ?? null,
        //     $producer?->rut ?? null,
        // ]);
        $township = $this->coalesceLabelValue([
            $producerEntity?->n_comuna ?? null,
            $producer?->comuna ?? null,
        ]);
        $province = $this->coalesceLabelValue([
            $producerEntity?->n_provincia ?? null,
            $producer?->provincia ?? null,
        ]);
        $region = $this->coalesceLabelValue([
            $producerEntity?->c_region ?? null,
            $producer?->predio ?? null,
        ]);
        $lotNumber = $this->coalesceLabelValue([$lot->n_g_recepcion ?? null]);
        $output = $this->coalesceLabelValue([$lot->destino ?? null]);
        $exporterName = $this->coalesceLabelValue([
            $exporterEntity?->nombre ?? null,
            $process->exportadora ?? null,
        ]);
        $exporterCode = $this->coalesceLabelValue([
            $exporterEntity?->codigo ?? null,
            $exporterEntity?->codigo_sag ?? null,
        ]);
        $packingName = $this->coalesceLabelValue([
            $packingEntity?->nombre ?? null,
            $packingEntity?->n_matriz ?? null,
        ]);
        $packingCode = $this->coalesceLabelValue([
            $packingEntity?->codigo_sag ?? null,
            $packingEntity?->codigo ?? null,
        ]);
        $packingTownship = $this->coalesceLabelValue([$packingEntity?->n_comuna ?? null]);
        $packingProvince = $this->coalesceLabelValue([$packingEntity?->n_provincia ?? null]);
        $packingRegion = $this->coalesceLabelValue([$packingEntity?->c_region ?? null]);

        return [
            'process_id' => (int) $process->id,
            'process_number' => $processNumber,
            'box_number' => $boxNumber,
            'species' => $species,
            'variety' => $variety,
            'producer_name' => $producerName,
            'csg_code' => $csgCode,
            'ggn_code' => $ggnCode,
            'sdp_code' => $sdpCode,
            'township' => $township,
            'province' => $province,
            'region' => $region,
            'lot_number' => $lotNumber,
            'process_name' => $processNumber,
            'output' => $output,
            'line_name' => $lineName !== '' ? $lineName : '-',
            'shift_name' => trim($shiftCode.($shiftName !== '' ? ' / '.$shiftName : '')) ?: '-',
            'packed_date' => $packedDate,
            'packed_time' => $packedTime,
            'size_code' => $sizeCode,
            'packaging_code' => $packagingCode,
            'packaging_name' => $packagingName,
            'label_name' => $packagingName,
            'net_weight' => $netWeight,
            'category' => $category,
            'exporter_name' => $exporterName,
            'exporter_code' => $exporterCode,
            'packing_name' => $packingName,
            'packing_code' => $packingCode,
            'packing_township' => $packingTownship,
            'packing_province' => $packingProvince,
            'packing_region' => $packingRegion,
            'trace_qr_text' => implode(';', [$species, $sizeCode, $packagingCode, $packagingName, $variety]),
        ];
    }

    private function resolveProducer(PackingProcessLot $lot): ?User
    {
        if (($lot->id_productor ?? null) !== null) {
            $producer = User::query()
                ->where('idprod', $lot->id_productor)
                ->first();

            if ($producer) {
                return $producer;
            }
        }

        $csg = trim((string) ($lot->csg_productor ?? ''));
        if ($csg !== '') {
            return User::query()
                ->where('csg', $csg)
                ->first();
        }

        return null;
    }

    private function resolveRemoteProducerEntity(?User $producer): ?object
    {
        $rut = trim((string) ($producer?->rut ?? ''));

        return $this->resolveRemoteEntityByTypeAndRut('Productor', $rut !== '' ? $rut : null);
    }

    private function resolveRemoteExporterEntity(?User $producer): ?object
    {
        $service = $producer?->services()
            ->orderBy('services.id')
            ->first();

        $rut = trim((string) ($service?->rut ?? ''));
        if ($rut === '') {
            $rut = '764255933';
        }

        return $this->resolveRemoteEntityByTypeAndRut('Exportador', $rut !== '' ? $rut : null);
    }

    private function resolveRemoteEntityByTypeAndRut(string $type, ?string $rut): ?object
    {
        $normalizedRut = $this->normalizeRut($rut);
        if ($normalizedRut === '') {
            return null;
        }

        return DB::connection('sqlsrv')
            ->table('V_ADM_Entidades')
            ->select([
                'codigo',
                'rut',
                'nombre',
                'sucursal',
                'tipo',
                'tipo_sucursal',
                'nombre_sucursal',
                'n_comuna',
                'n_provincia',
                'c_region',
                'codigo_sag',
                'r_matriz',
                'n_matriz',
                'csg',
            ])
            ->where('tipo', $type)
            ->whereRaw("REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '') = ?", [$normalizedRut])
            ->first();
    }

    private function normalizeRut(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    /**
     * @param array<int, mixed> $values
     */
    private function coalesceLabelValue(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '-';
    }
}
