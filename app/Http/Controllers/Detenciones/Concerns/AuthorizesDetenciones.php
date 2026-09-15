<?php

namespace App\Http\Controllers\Detenciones\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait AuthorizesDetenciones
{
    protected function authorizeDetenciones(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No tienes permisos para acceder al módulo de Detenciones de Máquinas.');
        }

        $allowedRoles = [
            'Admin',
            'Administrador',
            'Gerencia',
            'Gerencia Planta',
            'Calidad',
            'Planificador',
            'Inventario',
            'Supervisor de Producción',
            'Operador de Producción',
            'Auditor / Control de Gestión',
        ];
        $allowedPermissions = [
            'detenciones.view',
            'detenciones.registros.manage',
            'detenciones.maquinas.manage',
            'detenciones.motivos.manage',
            'detenciones.dashboard.view',
        ];

        $hasRole = method_exists($user, 'hasRole') && collect($allowedRoles)->contains(fn (string $role) => $user->hasRole($role));
        $userPermissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')
            : collect();
        $hasPermission = $userPermissions instanceof Collection
            && $userPermissions->intersect($allowedPermissions)->isNotEmpty();

        abort_unless($hasRole || $hasPermission, 403, 'No tienes permisos para acceder al módulo de Detenciones de Máquinas.');
    }

    protected function authorizeDetencionesPermission(Request $request, string $permission): void
    {
        $this->authorizeDetenciones($request);

        $user = $request->user();

        if (! $user) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        $isAdmin = method_exists($user, 'hasRole')
            && collect(['Admin', 'Administrador'])->contains(fn (string $role) => $user->hasRole($role));
        $hasPermission = method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission);

        abort_unless($isAdmin || $hasPermission, 403, 'No tienes permiso para realizar esta acción.');
    }
}