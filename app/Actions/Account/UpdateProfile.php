<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateProfile
{
    public function execute(User $user, string $name, string $lastName, string $username): User
    {
        $user->update([
            'name' => $name,
            'last_name' => $lastName,
            'username' => $username,
        ]);

        return $user->fresh();
    }
}
