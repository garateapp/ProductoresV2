<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\RuleResult;
use App\Contracts\Integrations\TransformationContext;

class FormatRule extends BaseRule
{
    protected function getType(): string
    {
        return 'format';
    }

    protected function doTransform(array $config, TransformationContext $context): RuleResult
    {
        $type = $config['type'] ?? 'date';
        $sourceField = $config['source_field'] ?? null;

        if (!$sourceField) {
            return RuleResult::error('Campo origen no configurado para formato');
        }

        $value = $this->getInputValue($sourceField, $context);
        if ($value === null || $value === '') {
            return RuleResult::skip();
        }

        $formatted = match ($type) {
            'date' => $this->formatDate($value, $config),
            'datetime' => $this->formatDatetime($value, $config),
            'number' => $this->formatNumber($value, $config),
            'decimal' => $this->formatDecimal($value, $config),
            'boolean' => $this->formatBoolean($value, $config),
            'text' => $this->formatText($value, $config),
            default => $value,
        };

        return RuleResult::ok([$sourceField => $formatted]);
    }

    private function formatDate(mixed $value, array $config): string
    {
        $inputFormat = $config['input_format'] ?? 'Y-m-d';
        $outputFormat = $config['output_format'] ?? 'd/m/Y';

        try {
            $date = \DateTime::createFromFormat($inputFormat, (string) $value);
            if (!$date) {
                $date = new \DateTime((string) $value);
            }
            return $date->format($outputFormat);
        } catch (\Exception) {
            return (string) $value;
        }
    }

    private function formatDatetime(mixed $value, array $config): string
    {
        $outputFormat = $config['output_format'] ?? 'Y-m-d H:i:s';
        try {
            $date = new \DateTime((string) $value);
            return $date->format($outputFormat);
        } catch (\Exception) {
            return (string) $value;
        }
    }

    private function formatNumber(mixed $value, array $config): string
    {
        $decimals = (int) ($config['decimals'] ?? 0);
        $decPoint = $config['decimal_separator'] ?? '.';
        $thousandsSep = $config['thousands_separator'] ?? '';

        return number_format((float) $value, $decimals, $decPoint, $thousandsSep);
    }

    private function formatDecimal(mixed $value, array $config): string
    {
        $decimals = (int) ($config['precision'] ?? 4);
        return number_format((float) $value, $decimals, '.', '');
    }

    private function formatBoolean(mixed $value, array $config): string
    {
        $trueValue = $config['true_value'] ?? '1';
        $falseValue = $config['false_value'] ?? '0';

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $trueValue : $falseValue;
    }

    private function formatText(mixed $value, array $config): string
    {
        $text = (string) $value;
        $transform = $config['text_transform'] ?? 'none';

        return match ($transform) {
            'uppercase' => mb_strtoupper($text),
            'lowercase' => mb_strtolower($text),
            'capitalize' => ucfirst(mb_strtolower($text)),
            'trim' => trim($text),
            default => $text,
        };
    }
}
