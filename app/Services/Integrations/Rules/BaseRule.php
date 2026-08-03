<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;
use App\Models\IntegrationRule;

abstract class BaseRule
{
    abstract protected function getType(): string;

    abstract protected function doTransform(array $config, TransformationContext $context): RuleResult;

    public function execute(IntegrationRule $rule, TransformationContext $context): RuleResult
    {
        $config = $rule->configuracion ?? [];
        $startTime = microtime(true);

        try {
            $result = $this->doTransform($config, $context);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return new RuleResult(
                success: $result->success,
                output: $result->output,
                error: $result->error,
                warning: $result->warning,
                stopRecord: $result->stopRecord,
                markPending: $result->markPending,
                pendingField: $result->pendingField,
                pendingValue: $result->pendingValue,
                mappingUsed: $result->mappingUsed,
                durationMs: $duration,
            );
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return $this->handleError($rule, $e, $context, $duration);
        }
    }

    protected function handleError(IntegrationRule $rule, \Throwable $e, TransformationContext $context, int $duration): RuleResult
    {
        $policy = $rule->politica_error?->value ?? 'stop_record';
        $defaultValue = $rule->valor_defecto;
        $customMessage = $rule->mensaje_error_personalizado ?? $e->getMessage();

        return match ($policy) {
            'stop_record' => RuleResult::error($customMessage, true),
            'mark_pending' => RuleResult::pending(implode(', ', $rule->outputs->pluck('clave_destino')->toArray()), ''),
            'use_default' => RuleResult::ok(
                $this->buildDefaultOutput($rule, $defaultValue),
                $duration
            ),
            'skip_field' => RuleResult::skip(),
            'log_warning' => RuleResult::warning($customMessage, $this->buildDefaultOutput($rule, $defaultValue)),
            default => RuleResult::error($customMessage, true),
        };
    }

    protected function buildDefaultOutput(IntegrationRule $rule, ?string $defaultValue): array
    {
        $output = [];
        foreach ($rule->outputs as $ruleOutput) {
            $output[$ruleOutput->clave_destino] = $defaultValue;
        }
        return $output;
    }

    protected function getInputValue(string $key, TransformationContext $context, mixed $default = null): mixed
    {
        return $context->getInput($key) ?? $context->getNormalized($key) ?? $default;
    }

    protected function setOutput(string $key, mixed $value, TransformationContext $context): void
    {
        $context->setOutput($key, $value);
    }
}
