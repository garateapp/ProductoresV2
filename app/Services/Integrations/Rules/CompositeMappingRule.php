<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;
use App\Models\IntegrationMappingSetVersion;
use App\Services\Integrations\Engine\MappingResolver;

class CompositeMappingRule extends BaseRule
{
    protected function getType(): string
    {
        return 'composite_mapping';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $mappingVersionId = $config['mapping_set_version_id'] ?? null;
        $sourceFields = $config['source_fields'] ?? [];
        $separator = $config['separator'] ?? '';

        if (!$mappingVersionId || empty($sourceFields)) {
            return RuleResult::error('Configuración de homologación compuesta incompleta');
        }

        $mappingVersion = IntegrationMappingSetVersion::find($mappingVersionId);
        if (!$mappingVersion) {
            return RuleResult::error("Versión de diccionario no encontrada: {$mappingVersionId}");
        }

        $values = [];
        foreach ($sourceFields as $field) {
            $val = $this->getInputValue($field, $context);
            if ($val === null || $val === '') {
                return RuleResult::error("Valor origen vacío para campo compuesto: {$field}");
            }
            $values[$field] = $val;
        }

        $resolver = app(MappingResolver::class);
        $result = $resolver->resolve($mappingVersion, $values);

        if (!$result->found) {
            return RuleResult::error(
                'Sin homologación compuesta para: ' . implode(' + ', array_values($values))
            );
        }

        return RuleResult::ok(
            ['__composite__' => $result->value],
            mappingUsed: [
                'mapping_set_version_id' => $mappingVersion->id,
                'mapping_set_name' => $mappingVersion->mappingSet?->nombre ?? 'Unknown',
                'input_keys' => $values,
                'output_values' => [$result->value],
            ]
        );
    }
}
