<?php

namespace App\Policies;

use App\Models\User;
use App\Models\IntegrationProfile;
use Illuminate\Auth\Access\HandlesAuthorization;

class IntegrationProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function view(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('integrations.manage');
    }

    public function update(User $user, IntegrationProfile $profile): bool
    {
        if ($profile->estado?->value === 'publicado') {
            return false;
        }

        return $user->hasPermissionTo('integrations.manage');
    }

    public function delete(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.manage')
            && $profile->estado?->value !== 'publicado';
    }

    public function publish(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.publish');
    }

    public function execute(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.execute');
    }

    public function duplicate(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.manage');
    }

    public function archive(User $user, IntegrationProfile $profile): bool
    {
        return $user->hasPermissionTo('integrations.manage');
    }
}
