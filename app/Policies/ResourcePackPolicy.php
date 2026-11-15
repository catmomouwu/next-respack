<?php

namespace App\Policies;

use App\Models\ResourcePack;
use App\Models\User;

class ResourcePackPolicy
{
    public function view(User $user, ResourcePack $pack): bool
    {
        return $user->isAdmin() || $pack->user_id === $user->id;
    }

    public function update(User $user, ResourcePack $pack): bool
    {
        return $user->isAdmin() || $pack->user_id === $user->id;
    }

    public function delete(User $user, ResourcePack $pack): bool
    {
        return $user->isAdmin() || $pack->user_id === $user->id;
    }
}
