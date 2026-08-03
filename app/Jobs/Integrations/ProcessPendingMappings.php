<?php

namespace App\Jobs\Integrations;

use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use App\Models\IntegrationMappingItem;
use App\Models\IntegrationMappingSetVersion;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPendingMappings implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public int $runId,
        public array $recordIds = [],
        public bool $useLatestVersion = false,
    ) {}

    public function handle(): void
    {
        $run = IntegrationRun::with('profileVersion')->findOrFail($this->runId);

        $pendingRecords = $run->records()
            ->whereIn('id', $this->recordIds)
            ->where('estado', 'pending_mapping')
            ->get();

        foreach ($pendingRecords as $record) {
            $errors = $record->errores ?? [];

            if (empty($errors)) {
                continue;
            }

            $record->update([
                'estado' => 'processing',
                'intentos' => $record->intentos + 1,
            ]);

            try {
                $output = $record->output_generado ?? [];
                $pendingFields = $record->input_original ?? [];

                $resolved = true;

                foreach ($pendingFields as $field => $value) {
                    if ($this->useLatestVersion) {
                        $mapping = \App\Models\IntegrationPendingMapping::where('run_record_id', $record->id)
                            ->where('campo', $field)
                            ->whereNotNull('valor_asignado')
                            ->first();

                        if ($mapping) {
                            $output[$field] = $mapping->valor_asignado;
                        } else {
                            $resolved = false;
                        }
                    }
                }

                if ($resolved) {
                    $record->update([
                        'output_generado' => $output,
                        'estado' => 'reprocessed',
                        'processed_at' => now(),
                        'errores' => null,
                    ]);
                } else {
                    $record->update([
                        'estado' => 'pending_mapping',
                    ]);
                }
            } catch (\Throwable $e) {
                $record->update([
                    'estado' => 'failed',
                    'errores' => array_merge($record->errores ?? [], [['message' => $e->getMessage()]]),
                ]);
            }
        }
    }

    public function tags(): array
    {
        return ['integration', 'pending-mapping', 'run:' . $this->runId];
    }
}
