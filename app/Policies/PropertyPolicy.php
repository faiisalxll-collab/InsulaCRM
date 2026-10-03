<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent() || $user->isFieldScout();
    }

    public function view(User $user, Property $property): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isAdmin() || $user->isAcquisitionAgent() || $user->isFieldScout()) {
            return true;
        }

        return $property->lead_id === null || $property->lead?->agent_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function update(User $user, Property $property): bool
    {
        return $this->ownsOrCanManage($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->ownsOrCanManage($user, $property);
    }

    public function createFieldScout(User $user): bool
    {
        return $user->isAdmin() || $user->isFieldScout();
    }

    private function ownsOrCanManage(User $user, Property $property): bool
    {
        if ((int) $user->tenant_id !== (int) $property->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAgent()
            && $property->lead_id !== null
            && (int) $property->lead?->agent_id === (int) $user->id;
    }
}
