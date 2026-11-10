<?php

namespace App\Services\Garces;

use App\Models\GarcesProduccionEnvio;
use App\Services\Garces\PalletBox\GarcesPalletBoxService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío manual de producción a Garces Fruits.
 *
 * El listado de folios sale de la vista de producción, pero el JSON que se
 * envía lo construye GarcesPalletBoxService (RegisterBoxes). Los folios
 * seleccionados viajan juntos en un único payload: un elemento en `pallets`
 * por cada tarja/folio.
 */
class GarcesProduccionFolioService
{
    public const MAX_FOLIOS = 2000;

    public ?string $sourceError = null;

    public function __construct(private readonly GarcesPalletBoxService $palletBox) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.garces_pallet_box.enabled', false);
    }

    public function endpoint(): string
    {
        return (string) ($this->palletBox->endpoint() ?? '');
    }

    public function timeout(): int
    {
        return max(5, (int) config('services.garces_pallet_box.timeout', 60));
    }

    /**
     * Folios disponibles con su resumen, marcados como enviados o pendientes.
     */
    public function availableFolios(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->sourceError = null;

        $folios = $this->folioAggregates($filters);
        $envios = $this->enviosFor($folios->pluck('folio')->all());

        $estado = (string) ($filters['estado'] ?? '');

        $items = $folios
            ->map(function ($row) use ($envios) {
                $envio = $envios[$row->folio] ?? null;

                return [
                    'folio' => (string) $row->folio,
                    'fecha_proceso' => $row->fecha_proceso ? (string) $row->fecha_proceso : null,
                    'numero_proceso' => $row->numero_proceso !== null ? (string) $row->numero_proceso : null,
                    'productor' => $row->productor !== null ? (string) $row->productor : null,
                    'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
                    'especie' => $row->especie !== null ? (string) $row->especie : null,
                    'calibre' => $row->calibre !== null ? (string) $row->calibre : null,
                    'categoria' => $row->categoria !== null ? (string) $row->categoria : null,
                    'embalaje' => $row->embalaje !== null ? (string) $row->embalaje : null,
                    'envase' => $row->embalaje !== null ? (string) $row->embalaje : null,
                    'cajas' => (float) $row->cajas,
                    'kilos' => (float) $row->kilos,
                    'enviado' => $envio?->status === GarcesProduccionEnvio::STATUS_SENT,
                    'envio' => $envio ? [
                        'status' => $envio->status,
                        'sent_at' => $envio->sent_at?->toDateTimeString(),
                        'attempts' => $envio->attempts,
                        'last_error' => $envio->last_error,
                    ] : null,
                ];
            })
            ->when($estado === 'pendientes', fn ($c) => $c->where('enviado', false))
            ->when($estado === 'enviados', fn ($c) => $c->where('enviado', true))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $filters],
        );
    }

    /**
     * Arma el JSON RegisterBoxes de los folios seleccionados sin enviarlo (dry run).
     * Todos los folios van en un mismo payload.
     */
    public function previewFolios(array $folios): array
    {
        $folios = $this->normalizeFolios($folios);

        if ($folios->isEmpty()) {
            return $this->result('error', 'No se seleccionaron folios para previsualizar.');
        }

        $items = $this->palletBox->payloadsForFolios($folios->all());

        if ($items === []) {
            return $this->result('error', 'No se encontraron datos de producción para los folios seleccionados.');
        }

        $payload = $this->palletBox->mergeItems($items);
        $stats = $this->folioStats($items);

        return [
            'status' => 'preview',
            'message' => 'Vista previa: '.count($stats['folios']).' folio(s) en un solo JSON, '
                .count($payload['pallets'] ?? []).' pallet(s) y '.$stats['boxes'].' caja(s). No se envió nada.',
            'sent' => 0,
            'failed' => 0,
            'folios_sent' => [],
            'folios_failed' => [],
            'would_send' => count($payload['pallets'] ?? []),
            'folio_count' => count($stats['folios']),
            'records' => $stats['boxes'],
            'cajas' => $stats['cajas'],
            'kilos' => $stats['kilos'],
            'folios' => $stats['folios'],
            'missing_folios' => array_values(array_diff($folios->all(), $stats['folios'])),
            'folios_without_boxes' => $stats['without_boxes'],
            'enabled' => $this->isEnabled(),
            'endpoint' => $this->endpoint() !== '' ? $this->endpoint() : null,
            'generated_at' => now()->toDateTimeString(),
            'details' => $stats['details'],
            'payload' => $payload,
        ];
    }

    /**
     * Envía a Garces únicamente los folios seleccionados, en un único JSON
     * construido por GarcesPalletBoxService.
     */
    public function syncFolios(array $folios, ?int $userId = null): array
    {
        $folios = $this->normalizeFolios($folios);

        if ($folios->isEmpty()) {
            return $this->result('error', 'No se seleccionaron folios para enviar.');
        }

        if (! $this->isEnabled()) {
            return $this->result('disabled', 'Servicio deshabilitado (GARCES_PALLET_BOX_ENABLED=false).');
        }

        $endpoint = $this->endpoint();

        if ($endpoint === '') {
            return $this->result('error', 'Endpoint de Garces no configurado (GARCES_PALLET_BOX_BASE_URL).');
        }

        $items = $this->palletBox->payloadsForFolios($folios->all());

        if ($items === []) {
            return $this->result('error', 'No se encontraron datos de producción para los folios seleccionados.');
        }

        $payload = $this->palletBox->mergeItems($items);
        $stats = $this->folioStats($items);

        $response = $this->sendPayload($endpoint, $payload);
        $ok = $response['ok'];

        foreach ($stats['details'] as $detail) {
            $this->recordEnvio(
                folio: $detail['folio'],
                numeroProceso: $detail['numero_proceso'],
                fechaProceso: $detail['fecha_proceso'],
                records: $detail['boxes'],
                cajas: $detail['cajas'],
                kilos: $detail['kilos'],
                sent: $ok,
                error: $ok ? null : $response['error'],
                userId: $userId,
            );
        }

        $sinDatos = array_values(array_diff($folios->all(), $stats['folios']));

        $status = $ok ? 'completed' : 'error';
        $message = $ok
            ? count($stats['folios']).' folio(s) enviados en un mismo JSON ('.$stats['boxes'].' caja(s)).'
            : 'No se pudo enviar: '.($response['error'] ?: 'respuesta no exitosa de Garces.');

        if ($sinDatos !== []) {
            $message .= ' Sin datos en el origen: '.implode(', ', $sinDatos).'.';
        }

        Log::info('GarcesProduccionFolioService: envío manual', [
            'folios' => $stats['folios'],
            'pallets' => count($payload['pallets'] ?? []),
            'cajas' => $stats['boxes'],
            'ok' => $ok,
            'user_id' => $userId,
            'endpoint' => $endpoint,
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'sent' => $ok ? $stats['boxes'] : 0,
            'failed' => $ok ? 0 : $stats['boxes'],
            'folios_sent' => $ok ? $stats['folios'] : [],
            'folios_failed' => $ok ? [] : $stats['folios'],
            'missing_folios' => $sinDatos,
        ];
    }

    /**
     * Totales por folio a partir de los payloads generados.
     *
     * @param  array<int,array{folio:string,payload:array<string,mixed>}>  $items
     * @return array{folios:array<int,string>,boxes:int,cajas:float,kilos:float,without_boxes:array<int,string>,details:array<int,array<string,mixed>>}
     */
    protected function folioStats(array $items): array
    {
        $details = [];
        $withoutBoxes = [];
        $cajas = 0.0;
        $kilos = 0.0;
        $boxes = 0;

        foreach (collect($items)->groupBy(fn ($item) => (string) $item['folio']) as $folio => $payloads) {
            $folioCajas = 0.0;
            $folioKilos = 0.0;
            $folioBoxes = 0;
            $pallets = 0;
            $numeroProceso = null;
            $fechaProceso = null;

            foreach ($payloads as $item) {
                foreach ($item['payload']['pallets'] ?? [] as $pallet) {
                    $header = $pallet['header'] ?? [];
                    $detail = $pallet['detail'] ?? [];

                    $pallets++;
                    $folioCajas += (float) ($header['cajasTotal'] ?? 0);
                    $folioKilos += (float) ($header['kilosTotal'] ?? 0);
                    $folioBoxes += count($detail);
                    $numeroProceso ??= isset($detail[0]['numeroProceso']) ? (string) $detail[0]['numeroProceso'] : null;
                    $fechaProceso ??= $header['fechaTarja'] ?? null;
                }
            }

            if ($folioBoxes === 0) {
                $withoutBoxes[] = (string) $folio;
            }

            $cajas += $folioCajas;
            $kilos += $folioKilos;
            $boxes += $folioBoxes;

            $details[] = [
                'folio' => (string) $folio,
                'pallets' => $pallets,
                'boxes' => $folioBoxes,
                'cajas' => round($folioCajas, 3),
                'kilos' => round($folioKilos, 3),
                'numero_proceso' => $numeroProceso,
                'fecha_proceso' => $fechaProceso,
            ];
        }

        return [
            'folios' => array_map(fn ($detail) => (string) $detail['folio'], $details),
            'boxes' => $boxes,
            'cajas' => round($cajas, 3),
            'kilos' => round($kilos, 3),
            'without_boxes' => $withoutBoxes,
            'details' => $details,
        ];
    }

    /**
     * Folios con registros de producción, agrupados y resumidos.
     */
    protected function folioAggregates(array $filters): Collection
    {
        try {
            return $this->sourceQuery($filters)
                ->selectRaw(
                    'folio,
                    min(fecha_produccion) as fecha_proceso,
                    max(n_g_proceso) as numero_proceso,
                    min(n_productor) as productor,
                    min(n_variedad) as variedad,
                    min(id_especie) as especie,
                    CASE WHEN CHARINDEX(',', CAST(n_calibre AS NVARCHAR(200))) > 0
                        THEN LEFT(CAST(n_calibre AS NVARCHAR(200)), CHARINDEX(',', CAST(n_calibre AS NVARCHAR(200))) - 1)
                        ELSE CAST(n_calibre AS NVARCHAR(200)) END as calibre,
                    min(CAST(n_categoria AS NVARCHAR(200))) as categoria,
                    min(CAST(n_embalaje AS NVARCHAR(200))) as embalaje,
                    NULL as envase,
                    sum(cantidad) as cajas,
                    sum(peso_neto) as kilos'
                )
                ->groupBy('folio')
                ->orderByRaw('min(fecha_produccion) desc')
                ->orderByRaw('folio desc')
                ->limit(self::MAX_FOLIOS)
                ->get();
        } catch (Throwable $e) {
            $this->sourceError = $e->getMessage();

            Log::error('GarcesProduccionFolioService: no se pudo leer la vista de producción: '.$e->getMessage());

            return collect();
        }
    }

    protected function enviosFor(array $folios): Collection
    {
        if ($folios === []) {
            return collect();
        }

        return GarcesProduccionEnvio::query()
            ->whereIn('folio', $folios)
            ->get()
            ->keyBy('folio');
    }

    protected function sourceQuery(array $filters)
    {
        $view = (string) config('services.garces_pallet_box.sqlsrv_view');

        $query = DB::connection('sqlsrv')->table($view);
        $folios = array_filter(array_map('strval', (array) ($filters['folios'] ?? [])));

        if ($folios !== []) {
            $query->whereIn('folio', $folios);
        }
        $query->where('id_exportadora',4373);
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where('folio', 'like', "%{$term}%");
        }

        // sqlsrv compara la columna datetime contra un literal nvarchar (error de conversión),
        // por eso la fecha se convierte explícitamente.
        if ($desde = $this->normalizeDate($filters['desde'] ?? null)) {
            $query->whereRaw('fecha_produccion >= CONVERT(datetime, ?, 120)', [$desde.' 00:00:00']);
        }

        if ($hasta = $this->normalizeDate($filters['hasta'] ?? null)) {
            $query->whereRaw('fecha_produccion <= CONVERT(datetime, ?, 120)', [$hasta.' 23:59:59']);
        }

        return $query;
    }

    protected function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * POST del payload RegisterBoxes, igual que GarcesPalletBoxService::syncPending.
     *
     * @return array{ok:bool,error:?string}
     */
    protected function sendPayload(string $endpoint, array $payload): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey = (string) config('services.garces_pallet_box.api_key', '')) {
            $headers['x-api-key'] = $apiKey;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout())
                ->post($endpoint, $payload);
        } catch (Throwable $e) {
            Log::error('GarcesProduccionFolioService: error al enviar', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if (($body['isSucess'] ?? false) || ($body['isSuccess'] ?? false)) {
            return ['ok' => true, 'error' => null];
        }

        $error = $response->successful()
            ? 'Garces no confirmó el envío: '.mb_substr($response->body(), 0, 500)
            : 'HTTP '.$response->status().' '.mb_substr($response->body(), 0, 500);

        Log::warning('GarcesProduccionFolioService: respuesta no exitosa', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 1000),
        ]);

        return ['ok' => false, 'error' => $error];
    }

    protected function recordEnvio(
        string $folio,
        ?string $numeroProceso,
        mixed $fechaProceso,
        int $records,
        float $cajas,
        float $kilos,
        bool $sent,
        ?string $error,
        ?int $userId,
    ): void {
        $envio = GarcesProduccionEnvio::firstOrNew(['folio' => $folio]);

        $envio->fill([
            'numero_proceso' => $numeroProceso,
            'fecha_proceso' => $fechaProceso ?: $envio->fecha_proceso,
            'records' => $records,
            'cajas' => $cajas,
            'kilos' => $kilos,
            'status' => $sent ? GarcesProduccionEnvio::STATUS_SENT : GarcesProduccionEnvio::STATUS_FAILED,
            'attempts' => (int) $envio->attempts + 1,
            'last_error' => $sent ? null : mb_substr((string) $error, 0, 1000),
            'user_id' => $userId,
            'sent_at' => $sent ? now() : $envio->sent_at,
        ]);

        $envio->save();
    }

    protected function normalizeFolios(array $folios): Collection
    {
        return collect($folios)
            ->map(fn ($folio) => trim((string) $folio))
            ->filter()
            ->unique()
            ->take(self::MAX_FOLIOS)
            ->values();
    }

    protected function result(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'sent' => 0,
            'failed' => 0,
            'folios_sent' => [],
            'folios_failed' => [],
            'payload' => null,
            'details' => [],
        ];
    }
}
