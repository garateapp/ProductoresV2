<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class IntegrationModulePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'integrations.view',
            'integrations.manage',
            'integrations.publish',
            'integrations.execute',
            'integrations.cancel',
            'integrations.reprocess',
            'integrations.view_payloads',
            'integrations.manage_mappings',
            'integrations.manage_pending',
            'integrations.manage_failures',
            'integrations.audit',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $adminRoles = Role::whereIn('name', ['Admin', 'Administrador'])->where('guard_name', 'web')->get();

        foreach ($adminRoles as $role) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
