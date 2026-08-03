<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;
use App\Models\IntegrationMappingSetVersion;
use App\Services\Integrations\Engine\MappingResolver;

class MultiOutputMappingRule extends BaseRule
{
    protected function getType(): string
    {
        return 'multi_output_mapping';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $mappingVersionId = $config['mapping_set_version_id'] ?? null;
        $sourceField = $config['source_field'] ?? null;

        if (!$mappingVersionId || !$sourceField) {
            return RuleResult::error('Configuración de homologación multi-salida incompleta');
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

        if (!$result->found || !$result->outputValues) {
            return RuleResult::error("Sin homologación multi-salida para {$sourceField}: {$value}");
        }

        $output = [];
        foreach ($result->outputValues as $key => $val) {
            $output[$key] = $val;
        }

        return RuleResult::ok(
            $output,
            mappingUsed: [
                'mapping_set_version_id' => $mappingVersion->id,
                'mapping_set_name' => $mappingVersion->mappingSet?->nombre ?? 'Unknown',
                'input_keys' => [$sourceField => (string) $value],
                'output_values' => $result->outputValues,
            ]
        );
    }
}
