<?php

namespace App\Policies;

use App\Models\Build;
use App\Models\User;

class BuildPolicy
{
    public function view(User $user, Build $build): bool
    {
        return $user->role === 'buyer' && $user->id === $build->user_id;
    }

    public function update(User $user, Build $build): bool
    {
        return $this->view($user, $build);
    }

    public function delete(User $user, Build $build): bool
    {
        return $this->view($user, $build);
    }
}
