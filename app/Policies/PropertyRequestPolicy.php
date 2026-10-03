<?php

namespace App\Policies;

use App\Models\PropertyRequest;
use App\Models\User;

class PropertyRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAgent();
    }

    public function view(User $user, PropertyRequest $request): bool
    {
        return $this->ownsOrCanManage($user, $request);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PropertyRequest $request): bool
    {
        return $this->ownsOrCanManage($user, $request);
    }

    public function delete(User $user, PropertyRequest $request): bool
    {
        return $this->ownsOrCanManage($user, $request);
    }

    public function refresh(User $user, PropertyRequest $request): bool
    {
        return $this->ownsOrCanManage($user, $request);
    }

    private function ownsOrCanManage(User $user, PropertyRequest $request): bool
    {
        if ((int) $user->tenant_id !== (int) $request->tenant_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAgent() && (int) $request->agent_id === (int) $user->id;
    }
}
