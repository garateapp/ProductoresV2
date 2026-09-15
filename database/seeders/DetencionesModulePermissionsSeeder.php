<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DetencionesModulePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'detenciones.view',
            'detenciones.registros.manage',
            'detenciones.maquinas.manage',
            'detenciones.motivos.manage',
            'detenciones.dashboard.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissions = [
            'Admin' => $permissions,
            'Administrador' => $permissions,
            'Gerencia' => [
                'detenciones.view',
                'detenciones.dashboard.view',
            ],
            'Gerencia Planta' => [
                'detenciones.view',
                'detenciones.registros.manage',
                'detenciones.dashboard.view',
            ],
            'Calidad' => [
                'detenciones.view',
                'detenciones.dashboard.view',
            ],
            'Planificador' => [
                'detenciones.view',
                'detenciones.dashboard.view',
            ],
            'Inventario' => [
                'detenciones.view',
                'detenciones.dashboard.view',
            ],
            'Supervisor de Producción' => [
                'detenciones.view',
                'detenciones.registros.manage',
            ],
            'Operador de Producción' => [
                'detenciones.view',
                'detenciones.registros.manage',
            ],
            'Auditor / Control de Gestión' => [
                'detenciones.view',
                'detenciones.dashboard.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $role->givePermissionTo($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}