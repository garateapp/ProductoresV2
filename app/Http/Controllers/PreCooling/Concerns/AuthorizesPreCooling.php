<?php

namespace App\Http\Controllers\PreCooling\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait AuthorizesPreCooling
{
    protected function authorizePreCooling(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No tienes permisos para acceder al módulo de Prefrío.');
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
            'prefrio.view',
            'prefrio.manage',
            'prefrio.loads.manage',
            'prefrio.saldos.manage',
            'prefrio.reports.view',
        ];

        $hasRole = method_exists($user, 'hasRole') && collect($allowedRoles)->contains(fn (string $role) => $user->hasRole($role));
        $userPermissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')
            : collect();
        $hasPermission = $userPermissions instanceof Collection
            && $userPermissions->intersect($allowedPermissions)->isNotEmpty();

        abort_unless($hasRole || $hasPermission, 403, 'No tienes permisos para acceder al módulo de Prefrío.');
    }

    protected function authorizePreCoolingPermission(Request $request, string $permission): void
    {
        $this->authorizePreCooling($request);

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
