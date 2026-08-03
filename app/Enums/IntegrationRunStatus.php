<?php

namespace App\Enums;

enum IntegrationRunStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case PROCESSING = 'processing';
    case PARTIALLY_COMPLETED = 'partially_completed';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PREPARING => 'Preparando',
            self::PROCESSING => 'Procesando',
            self::PARTIALLY_COMPLETED => 'Parcialmente completada',
            self::COMPLETED => 'Completada',
            self::FAILED => 'Fallida',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-gray-100 text-gray-700',
            self::PREPARING => 'bg-blue-100 text-blue-700',
            self::PROCESSING => 'bg-yellow-100 text-yellow-700',
            self::PARTIALLY_COMPLETED => 'bg-orange-100 text-orange-700',
            self::COMPLETED => 'bg-green-100 text-green-700',
            self::FAILED => 'bg-red-100 text-red-700',
            self::CANCELLED => 'bg-slate-100 text-slate-600',
        };
    }
}
