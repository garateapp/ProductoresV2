<?php

namespace App\Services\Integrations\Engine;

use App\Contracts\Integrations\TransformationContext;
use App\Contracts\Integrations\TransformationEngineInterface;
use App\Contracts\Integrations\TransformationResult;
use App\Models\IntegrationProfileVersion;

class TransformationEngine implements TransformationEngineInterface
{
    public function __construct(
        private readonly RuleFactory $ruleFactory,
        private readonly MappingResolver $mappingResolver,
    ) {}

    public function transform(
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        array $mappingSetVersions = []
    ): TransformationResult {
        return $this->execute($profileVersion, $inputData, $mappingSetVersions, false);
    }

    public function simulate(
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        array $mappingSetVersions = []
    ): TransformationResult {
        return $this->execute($profileVersion, $inputData, $mappingSetVersions, true);
    }

    public function validateProfile(IntegrationProfileVersion $profileVersion): array
    {
        $errors = [];
        $rules = $profileVersion->rules()->with(['inputs', 'outputs'])->where('activo', true)->get();

        if ($rules->isEmpty()) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'El perfil no tiene reglas activas',
            ];
        }

        $inputFields = $profileVersion->inputFields()->where('activo', true)->get();
        $outputFields = $profileVersion->outputFields()->where('activo', true)->get();

        if ($inputFields->isEmpty()) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'El perfil no tiene campos de entrada definidos',
            ];
        }

        if ($outputFields->isEmpty()) {
            $errors[] = [
                'type' => 'warning',
                'message' => 'El perfil no tiene campos de salida definidos',
            ];
        }

        foreach ($rules as $rule) {
            try {
                $handler = $this->ruleFactory->create($rule->tipo?->value ?? $rule->tipo);
            } catch (\Throwable $e) {
                $errors[] = [
                    'type' => 'error',
                    'message' => "Regla '{$rule->nombre}': {$e->getMessage()}",
                ];
            }
        }

        return $errors;
    }

    private function execute(
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        array $mappingSetVersions,
        bool $simulate
    ): TransformationResult {
        $rules = $profileVersion->rules()
            ->with(['inputs.inputField', 'outputs.outputField'])
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        $normalizedInput = $this->normalizeInput($profileVersion, $inputData);
        $currentOutput = [];
        $rulesExecuted = [];
        $errors = [];
        $warnings = [];
        $mappingsUsed = [];
        $pendingFields = [];
        $ruleTimings = [];

        $context = new TransformationContext(
            profileVersion: $profileVersion,
            inputData: $inputData,
            normalizedData: $normalizedInput,
            currentOutput: $currentOutput,
            mappingSetVersions: $mappingSetVersions,
        );

        foreach ($rules as $rule) {
            try {
                $handler = $this->ruleFactory->create($rule->tipo?->value ?? $rule->tipo);
            } catch (\Throwable $e) {
                $errors[] = "Error al crear regla '{$rule->nombre}': {$e->getMessage()}";

                if ((string) ($rule->politica_error ?? '') === 'stop_record' || !$rule->politica_error) {
                    break;
                }

                continue;
            }

            $startTime = microtime(true);
            $result = $handler->execute($rule, $context);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $rulesExecuted[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->nombre,
                'rule_type' => $rule->tipo?->value ?? $rule->tipo,
                'status' => $result->success ? 'success' : ($result->markPending ? 'pending' : 'failed'),
                'duration_ms' => $durationMs,
                'error' => $result->error,
                'warning' => $result->warning,
            ];

            $ruleTimings[] = [
                'rule_name' => $rule->nombre,
                'duration_ms' => $durationMs,
            ];

            if ($result->error) {
                $errors[] = $result->error;
            }

            if ($result->warning) {
                $warnings[] = $result->warning;
            }

            if ($result->mappingUsed) {
                $mappingsUsed[] = $result->mappingUsed;
            }

            if ($result->markPending) {
                $pendingFields[] = [
                    'field' => $result->pendingField,
                    'value' => $result->pendingValue,
                ];
            }

            if ($result->stopRecord) {
                break;
            }

            if ($result->success && !empty($result->output)) {
                foreach ($result->output as $key => $value) {
                    $context->setOutput($key, $value);

                    foreach ($rule->outputs as $ruleOutput) {
                        $destKey = $ruleOutput->clave_destino;

                        if ($key === '__value__') {
                            $currentOutput[$destKey] = $value;
                        } elseif ($key === '__concatenated__') {
                            $currentOutput[$destKey] = $value;
                        } elseif ($key === '__math__') {
                            $currentOutput[$destKey] = $value;
                        } elseif ($key === '__conditional__') {
                            $currentOutput[$destKey] = $value;
                        } elseif ($key === '__composite__') {
                            $currentOutput[$destKey] = $value;
                        } elseif (!str_starts_with($key, '__')) {
                            $currentOutput[$destKey] = $value;
                        }
                    }
                }
            }
        }

        return new TransformationResult(
            output: $currentOutput,
            normalizedInput: $normalizedInput,
            rulesExecuted: $rulesExecuted,
            errors: $errors,
            warnings: $warnings,
            mappingsUsed: $mappingsUsed,
            pendingFields: $pendingFields,
            ruleTimings: $ruleTimings,
            success: empty($errors),
        );
    }

    private function normalizeInput(IntegrationProfileVersion $profileVersion, array $inputData): array
    {
        $normalized = [];
        $fields = $profileVersion->inputFields()->where('activo', true)->get();

        foreach ($fields as $field) {
            $value = data_get($inputData, $field->clave);
            $normalized[$field->clave] = $value;
        }

        return $normalized;
    }
}
