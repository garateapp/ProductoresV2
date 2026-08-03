<?php

namespace App\Jobs\Integrations;

use App\Enums\IntegrationRunRecordStatus;
use App\Models\IntegrationProfileVersion;
use App\Models\IntegrationRun;
use App\Services\Integrations\Engine\IntegrationProcessor;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIntegrationRecord implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $runId,
        public int $profileVersionId,
        public array $inputData,
        public string $sourceIdentifier,
        public string $idempotencyKey,
        public int $chunkIndex = 0,
        public int $totalRecords = 0,
    ) {}

    public function handle(IntegrationProcessor $processor): void
    {
        $run = IntegrationRun::with('profile')->findOrFail($this->runId);
        $profileVersion = IntegrationProfileVersion::with(['rules.inputs', 'rules.outputs'])
            ->findOrFail($this->profileVersionId);

        $processor->processRecord(
            run: $run,
            profileVersion: $profileVersion,
            inputData: $this->inputData,
            sourceIdentifier: $this->sourceIdentifier,
            idempotencyKey: $this->idempotencyKey,
        );

        $this->updateRunProgress($run);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $run = IntegrationRun::find($this->runId);
        if ($run) {
            $record = $run->records()
                ->where('idempotency_key', $this->idempotencyKey)
                ->first();

            if ($record) {
                $record->update([
                    'estado' => IntegrationRunRecordStatus::FAILED,
                    'errores' => array_merge($record->errores ?? [], [['message' => $exception->getMessage()]]),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['integration', 'run:' . $this->runId, 'chunk:' . $this->chunkIndex];
    }

    private function updateRunProgress(IntegrationRun $run): void
    {
        $counts = $run->records()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN estado IN ('success', 'failed', 'pending_mapping') THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN estado = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN estado = 'pending_mapping' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN estado = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        $run->updateQuietly([
            'procesados' => (int) ($counts->processed ?? 0),
            'exitosos' => (int) ($counts->success ?? 0),
            'pendientes' => (int) ($counts->pending ?? 0),
            'fallidos' => (int) ($counts->failed ?? 0),
        ]);
    }
}
