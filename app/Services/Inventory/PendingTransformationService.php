<?php

namespace App\Services\Inventory;

use App\Jobs\ProcessTransformationJob;
use App\Models\InventoryLocation;
use App\Models\InventoryTechnicalSheet;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Recuperación de consumos de semielaborados que quedaron sin ejecutar porque el
 * worker de la cola no estaba corriendo (pendientes en la tabla jobs) o porque
 * fallaron (failed_jobs).
 *
 * El reintento se ejecuta de forma síncrona, sin depender del worker, y solo borra
 * el trabajo de la cola cuando la transformación termina correctamente. Si vuelve
 * a fallar, el trabajo permanece disponible para un nuevo reintento.
 */
class PendingTransformationService
{
    /**
     * El nombre de la clase dentro del payload JSON viene escapado
     * (App\\Jobs\\ProcessTransformationJob), así que se busca por el nombre de la
     * clase sin namespace: en LIKE la barra invertida es carácter de escape y en
     * el texto almacenado hay dos.
     */
    private const PAYLOAD_MARKER = 'ProcessTransformationJob';

    public function __construct(private readonly FailedJobProviderInterface $failer) {}

    /**
     * @return array{pending: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>, pending_count: int, failed_count: int}
     */
    public function overview(): array
    {
        $pendingRows = $this->pendingRows();
        $failedRows = $this->failedRows();

        return [
            'pending' => $pendingRows->map(fn ($row) => $this->describePending($row))->values()->all(),
            'failed' => $failedRows->map(fn ($row) => $this->describeFailed($row))->values()->all(),
            'pending_count' => $pendingRows->count(),
            'failed_count' => $failedRows->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->pendingRows()
            ->map(fn ($row) => $this->describePending($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function failed(): array
    {
        return $this->failedRows()
            ->map(fn ($row) => $this->describeFailed($row))
            ->values()
            ->all();
    }

    /**
     * Ejecuta un trabajo pendiente de la tabla jobs.
     *
     * @return array{ok: bool, message: string}
     */
    public function processPending(int $jobId): array
    {
        $row = DB::table('jobs')
            ->where('id', $jobId)
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%')
            ->whereNull('reserved_at')
            ->first();

        if (! $row) {
            return ['ok' => false, 'message' => "El trabajo #{$jobId} ya no está pendiente o no corresponde a una transformación."];
        }

        $result = $this->run($row->payload);

        if ($result['ok']) {
            DB::table('jobs')->where('id', $jobId)->delete();
        }

        return $result;
    }

    /**
     * Reintenta un trabajo que ya estaba en failed_jobs.
     *
     * @return array{ok: bool, message: string}
     */
    public function retryFailed(string $uuid): array
    {
        $job = $this->failer->find($uuid);

        if (! $job) {
            return ['ok' => false, 'message' => 'El trabajo fallido ya no existe.'];
        }

        $result = $this->run($job->payload);

        if ($result['ok']) {
            $this->failer->forget($uuid);
        }

        return $result;
    }

    /**
     * Intenta ejecutar todos los trabajos pendientes y fallidos.
     *
     * @return array{processed: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function processAll(): array
    {
        $results = [];
        $processed = 0;
        $failedCount = 0;

        foreach ($this->pendingRows() as $row) {
            $results[] = ['id' => $row->id, 'origin' => 'jobs'] + $this->processPending((int) $row->id);
        }

        foreach ($this->failedRows() as $row) {
            $results[] = ['id' => $row->uuid, 'origin' => 'failed_jobs'] + $this->retryFailed((string) $row->uuid);
        }

        foreach ($results as $result) {
            if ($result['ok']) {
                $processed++;
            } else {
                $failedCount++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function run(string $rawPayload): array
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'No se pudo leer el payload del trabajo.'];
        }

        try {
            $retry = ProcessTransformationJob::dataForRetry($payload);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            app()->call([new ProcessTransformationJob($retry['data'], (int) $retry['user_id']), 'handle']);

            return ['ok' => true, 'message' => 'Consumo registrado correctamente.'];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function pendingRows(): Collection
    {
        if (! Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%')
            ->whereNull('reserved_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function failedRows(): Collection
    {
        return collect($this->failer->all())
            ->filter(fn ($job) => str_contains((string) ($job->payload ?? ''), self::PAYLOAD_MARKER))
            ->values();
    }

    private function describePending(object $row): array
    {
        return $this->describe(
            id: (string) $row->id,
            state: 'pending',
            payload: $row->payload,
            createdAt: $row->created_at ?? null,
            error: null,
        );
    }

    private function describeFailed(object $row): array
    {
        return $this->describe(
            id: (string) ($row->uuid ?? $row->id),
            state: 'failed',
            payload: $row->payload,
            createdAt: null,
            error: $this->firstErrorLine((string) ($row->exception ?? '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $id, string $state, ?string $payload, $createdAt, ?string $error): array
    {
        $decoded = json_decode((string) $payload, true);
        $data = [];

        if (is_array($decoded)) {
            try {
                $data = ProcessTransformationJob::dataForRetry($decoded)['data'] ?? [];
            } catch (Throwable) {
                $data = [];
            }
        }

        $sheet = isset($data['technical_sheet_id'])
            ? InventoryTechnicalSheet::query()->find($data['technical_sheet_id'])
            : null;
        $location = isset($data['location_id'])
            ? InventoryLocation::query()->find($data['location_id'])
            : null;

        $inputs = collect($data['inputs'] ?? [])
            ->map(fn (array $input) => [
                'lpn_code' => (string) ($input['lpn_code'] ?? ''),
                'consumed' => (float) ($input['consumed'] ?? 0),
                'waste_total' => round(collect($input['wastes'] ?? [])->sum(fn ($waste) => (float) ($waste['quantity'] ?? 0)), 4),
            ])
            ->values()
            ->all();

        return [
            'id' => $id,
            'state' => $state,
            'technical_sheet_id' => isset($data['technical_sheet_id']) ? (int) $data['technical_sheet_id'] : null,
            'technical_sheet' => trim(($sheet?->nombre ?? '').' · '.($sheet ? 'v'.$sheet->version : ''), ' ·'),
            'location' => $location ? trim($location->codigo.' · '.$location->nombre, ' ·') : null,
            'quantity' => isset($data['quantity']) ? (float) $data['quantity'] : null,
            'inputs' => $inputs,
            'created_at' => $createdAt ? Carbon::createFromTimestamp((int) $createdAt)->toDateTimeString() : null,
            'error' => $error,
        ];
    }

    private function firstErrorLine(string $exception): ?string
    {
        if (trim($exception) === '') {
            return null;
        }

        $first = trim(strtok($exception, "\n") ?: '');

        return $first === '' ? null : $first;
    }
}