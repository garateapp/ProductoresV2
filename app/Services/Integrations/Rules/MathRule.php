<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class MathRule extends BaseRule
{
    private const ALLOWED_OPERATIONS = ['sum', 'subtract', 'multiply', 'divide', 'average'];

    protected function getType(): string
    {
        return 'math';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $operation = $config['operation'] ?? 'sum';
        $fields = $config['fields'] ?? [];
        $constants = $config['constants'] ?? [];
        $precision = (int) ($config['precision'] ?? 4);

        if (!in_array($operation, self::ALLOWED_OPERATIONS)) {
            return RuleResult::error("Operación no permitida: {$operation}");
        }

        $values = [];

        foreach ($fields as $field) {
            $val = $this->getInputValue($field, $context, 0);
            $values[] = is_numeric($val) ? (float) $val : 0;
        }

        foreach ($constants as $constant) {
            $values[] = is_numeric($constant) ? (float) $constant : 0;
        }

        if (empty($values)) {
            return RuleResult::error('Sin valores para operación matemática');
        }

        $result = match ($operation) {
            'sum' => array_sum($values),
            'subtract' => array_reduce($values, fn ($carry, $v) => $carry - $v, 2 * $values[0] - $values[0]),
            'multiply' => array_reduce($values, fn ($carry, $v) => $carry * $v, 1),
            'divide' => $values[0] / (($values[1] ?? 1) ?: 1),
            'average' => array_sum($values) / count($values),
        };

        return RuleResult::ok(['__math__' => round($result, $precision)]);
    }
}
