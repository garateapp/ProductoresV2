<?php

namespace App\Services\Integrations\Rules;

class RuleConfigurations
{
    public static function direct(array $config): array
    {
        return [
            'source_field' => $config['source_field'] ?? null,
        ];
    }

    public static function constant(array $config): array
    {
        return [
            'value' => $config['value'] ?? null,
            'value_type' => $config['value_type'] ?? 'string',
        ];
    }

    public static function mapping(array $config): array
    {
        return [
            'mapping_set_version_id' => $config['mapping_set_version_id'] ?? null,
            'source_field' => $config['source_field'] ?? null,
            'output_field' => $config['output_field'] ?? null,
        ];
    }

    public static function compositeMapping(array $config): array
    {
        return [
            'mapping_set_version_id' => $config['mapping_set_version_id'] ?? null,
            'source_fields' => $config['source_fields'] ?? [],
            'separator' => $config['separator'] ?? '',
        ];
    }

    public static function multiOutputMapping(array $config): array
    {
        return [
            'mapping_set_version_id' => $config['mapping_set_version_id'] ?? null,
            'source_field' => $config['source_field'] ?? null,
        ];
    }

    public static function concatenation(array $config): array
    {
        return [
            'parts' => $config['parts'] ?? [],
            'separator' => $config['separator'] ?? '',
        ];
    }

    public static function math(array $config): array
    {
        return [
            'operation' => $config['operation'] ?? 'sum',
            'fields' => $config['fields'] ?? [],
            'constants' => $config['constants'] ?? [],
            'precision' => $config['precision'] ?? 4,
        ];
    }

    public static function conditional(array $config): array
    {
        return [
            'conditions' => $config['conditions'] ?? [],
            'else_value' => $config['else_value'] ?? null,
        ];
    }

    public static function format(array $config): array
    {
        return [
            'type' => $config['type'] ?? 'date',
            'input_format' => $config['input_format'] ?? null,
            'output_format' => $config['output_format'] ?? null,
            'source_field' => $config['source_field'] ?? null,
        ];
    }

    public static function relatedField(array $config): array
    {
        return [
            'relation' => $config['relation'] ?? null,
            'source_field' => $config['source_field'] ?? null,
            'value_field' => $config['value_field'] ?? null,
        ];
    }

    public static function custom(array $config): array
    {
        return [
            'rule_key' => $config['rule_key'] ?? null,
            'custom_config' => $config['custom_config'] ?? [],
        ];
    }

    public static function validateType(string $type, array $config): array
    {
        $method = match ($type) {
            'direct' => 'direct',
            'constant' => 'constant',
            'mapping' => 'mapping',
            'composite_mapping' => 'compositeMapping',
            'multi_output_mapping' => 'multiOutputMapping',
            'concatenation' => 'concatenation',
            'math' => 'math',
            'conditional' => 'conditional',
            'format' => 'format',
            'related_field' => 'relatedField',
            'custom' => 'custom',
            default => throw new \InvalidArgumentException("Unknown rule type: {$type}"),
        };

        return self::$method($config);
    }
}
