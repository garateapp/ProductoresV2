<?php

namespace App\Http\Controllers\Inventory\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait AuthorizesInventory
{
    protected function authorizeInventory(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No tienes permisos para acceder al módulo de Inventario.');
        }

        $allowedRoles = [
            'Admin',
            'Administrador',
            'Gerencia',
            'Gerencia Planta',
            'Calidad',
            'Supervisor de Bodega',
            'Operador de Bodega',
            'Supervisor de Producción',
            'Operador de Producción',
            'Supervisor Armado de Cajas',
            'Auditor / Control de Gestión',
        ];
        $allowedPermissions = [
            'inventory.view',
            'inventory.manage',
            'inventory.movements',
            'inventory.reports',
        ];

        $hasRole = method_exists($user, 'hasRole') && collect($allowedRoles)->contains(fn (string $role) => $user->hasRole($role));
        $userPermissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')
            : collect();
        $hasPermission = $userPermissions instanceof Collection
            && $userPermissions->intersect($allowedPermissions)->isNotEmpty();

        abort_unless($hasRole || $hasPermission, 403, 'No tienes permisos para acceder al módulo de Inventario.');
    }
}
