<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingAuditLog;
use App\Models\User;

class AuditService
{
    public function log(
        User $usuario,
        string $accion,
        ?int $loadId = null,
        ?string $folio = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
    ): void {
        PreCoolingAuditLog::create([
            'load_id' => $loadId,
            'folio' => $folio,
            'usuario_id' => $usuario->id,
            'accion' => $accion,
            'datos_antes' => $datosAntes,
            'datos_despues' => $datosDespues,
            'ip' => request()->ip(),
        ]);
    }
}
