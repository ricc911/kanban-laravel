<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateProfile
{
    public function execute(User $user, string $name): User
    {
        $user->update(['name' => $name]);

        return $user->fresh();
    }
}
