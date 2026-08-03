<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventoryModulePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'inventory.view',
            'inventory.manage',
            'inventory.movements',
            'inventory.reports',
            'inventory.logistic-units.view',
            'inventory.logistic-units.manage',
            'inventory.waste.view',
            'inventory.waste.manage',
            'inventory.waste.review',
            'inventory.scans.use',
            'inventory.ledger.verify',
            'inventory.materials.sync-sap',
            'inventory.technical-sheets.manage',
            'inventory.productions.manage',
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
                'inventory.view',
                'inventory.reports',
                'inventory.waste.view',
                'inventory.ledger.verify',
            ],
            'Gerencia Planta' => [
                'inventory.view',
                'inventory.reports',
                'inventory.waste.view',
            ],
            'Calidad' => [
                'inventory.view',
                'inventory.reports',
                'inventory.waste.view',
            ],
            'Supervisor de Bodega' => [
                'inventory.view',
                'inventory.manage',
                'inventory.movements',
                'inventory.logistic-units.view',
                'inventory.logistic-units.manage',
                'inventory.waste.view',
                'inventory.waste.manage',
                'inventory.waste.review',
                'inventory.scans.use',
                'inventory.ledger.verify',
                'inventory.materials.sync-sap',
            ],
            'Operador de Bodega' => [
                'inventory.view',
                'inventory.movements',
                'inventory.logistic-units.view',
                'inventory.waste.view',
                'inventory.waste.manage',
                'inventory.scans.use',
            ],
            'Supervisor de Producción' => [
                'inventory.view',
                'inventory.movements',
                'inventory.productions.manage',
                'inventory.reports',
                'inventory.waste.view',
            ],
            'Operador de Producción' => [
                'inventory.view',
                'inventory.movements',
                'inventory.productions.manage',
            ],
            'Supervisor Armado de Cajas' => [
                'inventory.view',
                'inventory.movements',
                'inventory.productions.manage',
            ],
            'Auditor / Control de Gestión' => [
                'inventory.view',
                'inventory.reports',
                'inventory.waste.view',
                'inventory.ledger.verify',
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
