<?php

namespace App\Enums;

enum IntegrationRuleType: string
{
    case DIRECT = 'direct';
    case CONSTANT = 'constant';
    case MAPPING = 'mapping';
    case COMPOSITE_MAPPING = 'composite_mapping';
    case MULTI_OUTPUT_MAPPING = 'multi_output_mapping';
    case CONCATENATION = 'concatenation';
    case MATH = 'math';
    case CONDITIONAL = 'conditional';
    case FORMAT = 'format';
    case RELATED_FIELD = 'related_field';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DIRECT => 'Directa',
            self::CONSTANT => 'Constante',
            self::MAPPING => 'Homologación simple',
            self::COMPOSITE_MAPPING => 'Homologación compuesta',
            self::MULTI_OUTPUT_MAPPING => 'Homologación multi-salida',
            self::CONCATENATION => 'Concatenación',
            self::MATH => 'Operación matemática',
            self::CONDITIONAL => 'Condicional',
            self::FORMAT => 'Formato',
            self::RELATED_FIELD => 'Campo relacionado',
            self::CUSTOM => 'Regla personalizada',
        };
    }

    public function needsMappingSet(): bool
    {
        return in_array($this, [
            self::MAPPING,
            self::COMPOSITE_MAPPING,
            self::MULTI_OUTPUT_MAPPING,
        ]);
    }
}
