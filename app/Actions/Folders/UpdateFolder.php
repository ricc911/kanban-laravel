<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateFolder
{
    public function execute(User $user, Folder $folder, string $name, ?string $color = null): Folder
    {
        if (! $folder->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'folder' => 'Non hai accesso a questa cartella.',
            ]);
        }

        $folder->update([
            'name' => trim($name),
            'color' => $color,
        ]);

        return $folder->fresh();
    }
}
