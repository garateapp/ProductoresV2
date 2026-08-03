<?php

namespace App\Policies;

use App\Models\User;

class IntegrationMappingSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('integrations.manage_mappings');
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo('integrations.manage_mappings');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('integrations.manage_mappings');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermissionTo('integrations.publish');
    }

    public function resolvePending(User $user): bool
    {
        return $user->hasPermissionTo('integrations.manage_pending');
    }
}
