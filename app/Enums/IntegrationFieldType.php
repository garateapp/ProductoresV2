<?php

namespace App\Enums;

enum IntegrationFieldType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case JSON = 'json';
    case ARRAY = 'array';

    public function label(): string
    {
        return match ($this) {
            self::STRING => 'Texto',
            self::INTEGER => 'Entero',
            self::DECIMAL => 'Decimal',
            self::BOOLEAN => 'Booleano',
            self::DATE => 'Fecha',
            self::DATETIME => 'Fecha y hora',
            self::JSON => 'JSON',
            self::ARRAY => 'Arreglo',
        };
    }
}
