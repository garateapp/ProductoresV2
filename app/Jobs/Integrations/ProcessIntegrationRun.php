<?php

namespace App\Jobs\Integrations;

use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\IntegrationProfileVersion;
use App\Services\Integrations\Engine\IntegrationProcessor;
use App\Services\Integrations\Engine\SourceAdapterFactory;
use App\Services\Integrations\Engine\OutputExporterFactory;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class ProcessIntegrationRun implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $runId,
    ) {}

    public function handle(IntegrationProcessor $processor): void
    {
        $run = IntegrationRun::with(['profile.client', 'profileVersion'])->findOrFail($this->runId);

        $run->update([
            'estado' => IntegrationRunStatus::PREPARING,
            'started_at' => now(),
        ]);

        $profile = $run->profile;
        $profileVersion = $run->profileVersion;

        $adapterConfig = [
            'table' => $profile->source_adapter_config['table'] ?? null,
            'columns' => $profileVersion->inputFields()->where('activo', true)->pluck('clave')->toArray(),
            'chunk_size' => $profile->idempotency_config['chunk_size'] ?? 500,
        ];

        $sourceAdapter = SourceAdapterFactory::create($profile->source_adapter ?? 'internal_database');
        $sourceAdapter->validateConfiguration($adapterConfig);

        $totalRecords = $sourceAdapter->count($adapterConfig);

        $run->update([
            'estado' => IntegrationRunStatus::PROCESSING,
            'total_registros' => $totalRecords,
        ]);

        $headers = $profileVersion->outputFields()
            ->where('activo', true)
            ->orderBy('posicion')
            ->pluck('clave_externa')
            ->toArray();

        $exporterKey = $profile->exporter ?? 'excel';
        $exporter = OutputExporterFactory::create($exporterKey);
        $exporterConfig = [
            'disk' => 'local',
            'filename' => $profile->codigo . '_' . now()->format('Ymd_His') . '.' . $this->extensionFor($exporterKey),
        ];

        $filePath = $exporter->initialize($exporterConfig, $headers);
        $exporter->writeHeaders($headers);

        $jobs = [];
        $chunkSize = $profile->idempotency_config['chunk_size'] ?? 500;
        $chunkIndex = 0;

        foreach ($sourceAdapter->getRecords($adapterConfig) as $record) {
            $sourceIdentifier = $sourceAdapter->getStableIdentifier($record);
            $idempotencyKey = $processor->buildIdempotencyKey(
                $profileVersion->id,
                $sourceIdentifier,
            );

            $jobs[] = new ProcessIntegrationRecord(
                runId: $this->runId,
                profileVersionId: $profileVersion->id,
                inputData: $record,
                sourceIdentifier: $sourceIdentifier,
                idempotencyKey: $idempotencyKey,
                chunkIndex: $chunkIndex,
                totalRecords: $totalRecords,
            );

            $chunkIndex++;

            if (count($jobs) >= 100) {
                Bus::batch($jobs)
                    ->onQueue('integrations')
                    ->dispatch();

                $jobs = [];
            }
        }

        if (!empty($jobs)) {
            Bus::batch($jobs)
                ->onQueue('integrations')
                ->dispatch();
        }

        $exportPath = $exporter->finalize();

        $run->update([
            'archivo_generado' => $exportPath,
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $run = IntegrationRun::find($this->runId);
        if ($run) {
            $run->update([
                'estado' => IntegrationRunStatus::FAILED,
                'errores' => array_merge($run->errores ?? [], [['message' => $exception->getMessage()]]),
                'finished_at' => now(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['integration', 'run:' . $this->runId];
    }

    private function extensionFor(string $exporterKey): string
    {
        return match ($exporterKey) {
            'csv' => 'csv',
            'json' => 'json',
            default => 'xlsx',
        };
    }
}
