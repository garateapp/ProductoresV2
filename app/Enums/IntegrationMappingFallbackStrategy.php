<?php

namespace App\Enums;

enum IntegrationMappingFallbackStrategy: string
{
    case ERROR = 'error';
    case PENDING = 'pending';
    case DEFAULT = 'default';
    case KEEP_ORIGINAL = 'keep_original';
    case NULL = 'null';
    case WARNING = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::ERROR => 'Error',
            self::PENDING => 'Pendiente de homologación',
            self::DEFAULT => 'Valor por defecto',
            self::KEEP_ORIGINAL => 'Mantener valor original',
            self::NULL => 'Nulo',
            self::WARNING => 'Advertencia',
        };
    }
}
