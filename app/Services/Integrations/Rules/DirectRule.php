<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class DirectRule extends BaseRule
{
    protected function getType(): string
    {
        return 'direct';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $sourceField = $config['source_field'] ?? null;

        if (!$sourceField) {
            return RuleResult::error('Campo origen no configurado');
        }

        $value = $this->getInputValue($sourceField, $context);

        return RuleResult::ok([$sourceField => $value]);
    }
}
