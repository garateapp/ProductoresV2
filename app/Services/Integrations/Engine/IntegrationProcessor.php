<?php

namespace App\Services\Integrations\Engine;

use App\Models\IntegrationProfileVersion;
use App\Models\IntegrationRun;
use App\Models\IntegrationRunRecord;
use App\Enums\IntegrationRunRecordStatus;

class IntegrationProcessor
{
    public function __construct(
        private readonly TransformationEngine $engine,
        private readonly MappingResolver $mappingResolver,
    ) {}

    public function processRecord(
        IntegrationRun $run,
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        string $sourceIdentifier,
        string $idempotencyKey,
    ): IntegrationRunRecord {
        $existing = IntegrationRunRecord::where('run_id', $run->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            $existing->update([
                'estado' => IntegrationRunRecordStatus::DUPLICATE,
                'intentos' => $existing->intentos + 1,
            ]);

            return $existing;
        }

        $record = IntegrationRunRecord::create([
            'run_id' => $run->id,
            'source_identifier' => $sourceIdentifier,
            'idempotency_key' => $idempotencyKey,
            'estado' => IntegrationRunRecordStatus::PROCESSING,
            'input_original' => $inputData,
            'intentos' => 1,
        ]);

        try {
            $mappingVersions = $this->loadMappingVersions($profileVersion);
            $result = $this->engine->transform($profileVersion, $inputData, $mappingVersions);

            $record->update([
                'input_normalizado' => $result->normalizedInput,
                'output_generado' => $result->output,
                'errores' => $result->errors,
                'advertencias' => $result->warnings,
                'estado' => $this->determineRecordStatus($result),
                'processed_at' => now(),
            ]);

            $this->saveRulesTrace($record, $result);
            $this->saveMappingsTrace($record, $result);

            if ($result->hasPendingFields()) {
                $this->createPendingMappings($run, $result, $record);
            }
        } catch (\Throwable $e) {
            $record->update([
                'estado' => IntegrationRunRecordStatus::FAILED,
                'errores' => [['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]],
                'processed_at' => now(),
            ]);
        }

        return $record->fresh();
    }

    public function buildIdempotencyKey(int $profileVersionId, string $sourceIdentifier): string
    {
        return hash('sha256', implode('|', [(string) $profileVersionId, $sourceIdentifier]));
    }

    private function loadMappingVersions(IntegrationProfileVersion $profileVersion): array
    {
        $versions = [];

        $rules = $profileVersion->rules()
            ->whereIn('tipo', ['mapping', 'composite_mapping', 'multi_output_mapping'])
            ->where('activo', true)
            ->get();

        foreach ($rules as $rule) {
            $config = $rule->configuracion ?? [];
            $mappingVersionId = $config['mapping_set_version_id'] ?? null;

            if ($mappingVersionId) {
                $version = \App\Models\IntegrationMappingSetVersion::with(['items.inputs'])
                    ->find($mappingVersionId);

                if ($version) {
                    $versions[$mappingVersionId] = $version;
                }
            }
        }

        return $versions;
    }

    private function determineRecordStatus(TransformationResult $result): IntegrationRunRecordStatus
    {
        if ($result->hasErrors()) {
            return IntegrationRunRecordStatus::FAILED;
        }

        if ($result->hasPendingFields()) {
            return IntegrationRunRecordStatus::PENDING_MAPPING;
        }

        return IntegrationRunRecordStatus::SUCCESS;
    }

    private function saveRulesTrace(IntegrationRunRecord $record, TransformationResult $result): void
    {
        foreach ($result->rulesExecuted as $ruleExecuted) {
            $record->rulesTrace()->create([
                'rule_id' => $ruleExecuted['rule_id'],
                'rule_name' => $ruleExecuted['rule_name'],
                'rule_type' => $ruleExecuted['rule_type'],
                'estado' => $ruleExecuted['status'],
                'duracion_ms' => $ruleExecuted['duration_ms'],
                'error' => $ruleExecuted['error'] ? ['message' => $ruleExecuted['error']] : null,
            ]);
        }
    }

    private function saveMappingsTrace(IntegrationRunRecord $record, TransformationResult $result): void
    {
        foreach ($result->mappingsUsed as $mappingUsed) {
            $record->mappingsTrace()->create([
                'mapping_set_version_id' => $mappingUsed['mapping_set_version_id'] ?? null,
                'mapping_set_name' => $mappingUsed['mapping_set_name'] ?? 'Unknown',
                'input_keys' => $mappingUsed['input_keys'] ?? [],
                'output_values' => $mappingUsed['output_values'] ?? [],
                'fallback_usado' => $mappingUsed['fallback_used'] ?? null,
            ]);
        }
    }

    private function createPendingMappings(IntegrationRun $run, TransformationResult $result, IntegrationRunRecord $record): void
    {
        foreach ($result->pendingFields as $pending) {
            \App\Models\IntegrationPendingMapping::firstOrCreate(
                [
                    'client_id' => $run->profile->client_id,
                    'profile_id' => $run->profile_id,
                    'campo' => $pending['field'],
                    'valor_interno' => $pending['value'],
                ],
                [
                    'run_record_id' => $record->id,
                    'frecuencia' => 1,
                ]
            );
        }
    }
}
