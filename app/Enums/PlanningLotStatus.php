<?php

namespace App\Enums;

enum PlanningLotStatus: string
{
    case PROPUESTO = 'PROPUESTO';
    case CONFIRMADO = 'CONFIRMADO';
    case CONFLICTO = 'CONFLICTO';
}

