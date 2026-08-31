<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PreCoolingModulePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'prefrio.view',
            'prefrio.manage',
            'prefrio.loads.manage',
            'prefrio.saldos.manage',
            'prefrio.reports.view',
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
                'prefrio.view',
                'prefrio.reports.view',
            ],
            'Gerencia Planta' => [
                'prefrio.view',
                'prefrio.loads.manage',
                'prefrio.saldos.manage',
                'prefrio.reports.view',
            ],
            'Calidad' => [
                'prefrio.view',
                'prefrio.reports.view',
            ],
            'Planificador' => [
                'prefrio.view',
                'prefrio.loads.manage',
                'prefrio.saldos.manage',
            ],
            'Inventario' => [
                'prefrio.view',
                'prefrio.loads.manage',
                'prefrio.saldos.manage',
            ],
            'Supervisor de Producción' => [
                'prefrio.view',
                'prefrio.loads.manage',
            ],
            'Operador de Producción' => [
                'prefrio.view',
                'prefrio.loads.manage',
            ],
            'Auditor / Control de Gestión' => [
                'prefrio.view',
                'prefrio.reports.view',
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
