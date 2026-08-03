<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class ConditionalRule extends BaseRule
{
    private const ALLOWED_OPERATORS = ['==', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'contains', 'starts_with', 'ends_with', 'empty', 'not_empty'];

    protected function getType(): string
    {
        return 'conditional';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $conditions = $config['conditions'] ?? [];
        $elseValue = $config['else_value'] ?? null;

        if (empty($conditions)) {
            return RuleResult::error('Sin condiciones configuradas');
        }

        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($condition, $context)) {
                return RuleResult::ok(['__conditional__' => $condition['output'] ?? null]);
            }
        }

        return RuleResult::ok(['__conditional__' => $elseValue]);
    }

    private function evaluateCondition(array $condition, TransformationContext $context): bool
    {
        $operator = $condition['operator'] ?? '==';
        $field = $condition['field'] ?? null;
        $value = $condition['value'] ?? null;

        if (!in_array($operator, self::ALLOWED_OPERATORS)) {
            return false;
        }

        $fieldValue = $field ? $this->getInputValue($field, $context) : null;

        return match ($operator) {
            '==' => $fieldValue == $value,
            '!=' => $fieldValue != $value,
            '>' => (float) ($fieldValue ?? 0) > (float) ($value ?? 0),
            '>=' => (float) ($fieldValue ?? 0) >= (float) ($value ?? 0),
            '<' => (float) ($fieldValue ?? 0) < (float) ($value ?? 0),
            '<=' => (float) ($fieldValue ?? 0) <= (float) ($value ?? 0),
            'in' => is_array($value) && in_array((string) $fieldValue, array_map('strval', $value)),
            'not_in' => !is_array($value) || !in_array((string) $fieldValue, array_map('strval', $value)),
            'contains' => is_string($fieldValue) && is_string($value) && str_contains($fieldValue, $value),
            'starts_with' => is_string($fieldValue) && is_string($value) && str_starts_with($fieldValue, $value),
            'ends_with' => is_string($fieldValue) && is_string($value) && str_ends_with($fieldValue, $value),
            'empty' => empty($fieldValue),
            'not_empty' => !empty($fieldValue),
            default => false,
        };
    }
}
