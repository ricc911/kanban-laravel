<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdatePassword
{
    public function execute(User $user, string $currentPassword, string $password): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'La password attuale non è corretta.']);
        }

        $user->update(['password' => $password]);
    }
}
