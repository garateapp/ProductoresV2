<?php

namespace App\Enums;

enum IntegrationRuleErrorPolicy: string
{
    case STOP_RECORD = 'stop_record';
    case MARK_PENDING = 'mark_pending';
    case USE_DEFAULT = 'use_default';
    case SKIP_FIELD = 'skip_field';
    case LOG_WARNING = 'log_warning';

    public function label(): string
    {
        return match ($this) {
            self::STOP_RECORD => 'Detener registro',
            self::MARK_PENDING => 'Marcar pendiente',
            self::USE_DEFAULT => 'Usar valor por defecto',
            self::SKIP_FIELD => 'Omitir campo',
            self::LOG_WARNING => 'Registrar advertencia',
        };
    }
}
