<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class ConstantRule extends BaseRule
{
    protected function getType(): string
    {
        return 'constant';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $value = $config['value'] ?? null;

        return RuleResult::ok(['__value__' => $value]);
    }
}
