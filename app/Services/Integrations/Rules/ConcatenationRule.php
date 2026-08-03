<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class ConcatenationRule extends BaseRule
{
    protected function getType(): string
    {
        return 'concatenation';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $parts = $config['parts'] ?? [];
        $separator = $config['separator'] ?? '';

        if (empty($parts)) {
            return RuleResult::error('Sin partes configuradas para concatenación');
        }

        $resolved = [];
        foreach ($parts as $part) {
            if (isset($part['type']) && $part['type'] === 'constant') {
                $resolved[] = $part['value'] ?? '';
            } else {
                $field = $part['field'] ?? $part ?? null;
                if ($field) {
                    $resolved[] = $this->getInputValue($field, $context, '');
                }
            }
        }

        return RuleResult::ok(['__concatenated__' => implode($separator, $resolved)]);
    }
}
