<?php

namespace App\Enums;

enum EstimationVersionStatus: string
{
    case ACTIVE = 'active';
    case SUPERSEDED = 'superseded';
    case REJECTED = 'rejected';
}
