<?php

namespace App\Enums;

enum IntegrationRunRecordStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case PENDING_MAPPING = 'pending_mapping';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case DUPLICATE = 'duplicate';
    case REPROCESSED = 'reprocessed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PROCESSING => 'Procesando',
            self::SUCCESS => 'Exitoso',
            self::PENDING_MAPPING => 'Pendiente de homologación',
            self::FAILED => 'Fallido',
            self::SKIPPED => 'Omitido',
            self::DUPLICATE => 'Duplicado',
            self::REPROCESSED => 'Reprocesado',
        };
    }
}
