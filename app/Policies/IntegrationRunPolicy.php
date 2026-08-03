<?php

namespace App\Policies;

use App\Models\User;
use App\Models\IntegrationRun;

class IntegrationRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function view(User $user, IntegrationRun $run): bool
    {
        return $user->hasPermissionTo('integrations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('integrations.execute');
    }

    public function cancel(User $user, IntegrationRun $run): bool
    {
        return $user->hasPermissionTo('integrations.cancel');
    }

    public function reprocess(User $user, IntegrationRun $run): bool
    {
        return $user->hasPermissionTo('integrations.reprocess');
    }

    public function viewPayloads(User $user, IntegrationRun $run): bool
    {
        return $user->hasPermissionTo('integrations.view_payloads');
    }
}
