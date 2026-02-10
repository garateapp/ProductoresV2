<?php

namespace App\Enums;

enum PlanningProcessStatus: string
{
    case BORRADOR = 'BORRADOR';
    case CONFLICTO = 'CONFLICTO';
    case CONFIRMADO = 'CONFIRMADO';
    case EN_PROCESO = 'EN_PROCESO';
    case CERRADO = 'CERRADO';
}

