<?php

namespace App\Enums;

enum IntegrationProfileStatus: string
{
    case BORRADOR = 'borrador';
    case EN_PRUEBAS = 'en_pruebas';
    case PUBLICADO = 'publicado';
    case INACTIVO = 'inactivo';
    case ARCHIVADO = 'archivado';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EN_PRUEBAS => 'En pruebas',
            self::PUBLICADO => 'Publicado',
            self::INACTIVO => 'Inactivo',
            self::ARCHIVADO => 'Archivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BORRADOR => 'bg-gray-100 text-gray-700',
            self::EN_PRUEBAS => 'bg-yellow-100 text-yellow-700',
            self::PUBLICADO => 'bg-green-100 text-green-700',
            self::INACTIVO => 'bg-red-100 text-red-700',
            self::ARCHIVADO => 'bg-slate-100 text-slate-600',
        };
    }
}
