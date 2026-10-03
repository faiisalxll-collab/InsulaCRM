<?php

namespace App\Policies;

use App\Models\PropertyMatch;
use App\Models\User;

class PropertyMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function view(User $user, PropertyMatch $match): bool
    {
        if ((int) $user->tenant_id !== (int) $match->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAgent()
            && (int) $match->request?->agent_id === (int) $user->id;
    }
}
