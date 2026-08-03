<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\MappingResolutionResult;
use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;
use App\Models\IntegrationMappingSetVersion;
use App\Services\Integrations\Engine\MappingResolver;

class MappingRule extends BaseRule
{
    protected function getType(): string
    {
        return 'mapping';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $mappingVersionId = $config['mapping_set_version_id'] ?? null;
        $sourceField = $config['source_field'] ?? null;

        if (!$mappingVersionId || !$sourceField) {
            return RuleResult::error('Configuración de homologación incompleta');
        }

        $mappingVersion = IntegrationMappingSetVersion::find($mappingVersionId);
        if (!$mappingVersion) {
            return RuleResult::error("Versión de diccionario no encontrada: {$mappingVersionId}");
        }

        $value = $this->getInputValue($sourceField, $context);
        if ($value === null || $value === '') {
            return RuleResult::error("Valor origen vacío para campo: {$sourceField}");
        }

        $resolver = app(MappingResolver::class);
        $result = $resolver->resolve($mappingVersion, [$sourceField => $value]);

        if (!$result->found) {
            $fallbackStrategy = $mappingVersion->estrategia_fallback?->value ?? 'error';
            $fallbackResult = $resolver->resolveFallback($mappingVersion, [$sourceField => $value]);

            return match ($fallbackStrategy) {
                'pending' => RuleResult::pending($sourceField, (string) $value),
                'default' => RuleResult::ok([$sourceField => $mappingVersion->valor_defecto], mappingUsed: $this->buildMappingTrace($mappingVersion, [$sourceField => (string) $value], null, 'default')),
                'keep_original' => RuleResult::ok([$sourceField => $value], mappingUsed: $this->buildMappingTrace($mappingVersion, [$sourceField => (string) $value], null, 'keep_original')),
                'null' => RuleResult::ok([$sourceField => null], mappingUsed: $this->buildMappingTrace($mappingVersion, [$sourceField => (string) $value], null, 'null')),
                'warning' => RuleResult::warning("Sin homologación para {$sourceField}: {$value}", [$sourceField => $fallbackResult]),
                default => RuleResult::error("Sin homologación para {$sourceField}: {$value}"),
            };
        }

        return RuleResult::ok(
            [$sourceField => $result->value],
            mappingUsed: $this->buildMappingTrace($mappingVersion, [$sourceField => (string) $value], $result->value)
        );
    }

    protected function buildMappingTrace(IntegrationMappingSetVersion $version, array $inputKeys, mixed $outputValue, ?string $fallback = null): array
    {
        return [
            'mapping_set_version_id' => $version->id,
            'mapping_set_name' => $version->mappingSet?->nombre ?? 'Unknown',
            'input_keys' => $inputKeys,
            'output_values' => is_array($outputValue) ? $outputValue : [$outputValue],
            'fallback_used' => $fallback,
        ];
    }
}
