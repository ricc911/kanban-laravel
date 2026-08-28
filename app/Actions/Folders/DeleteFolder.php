<?php

namespace App\Actions\Folders;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteFolder
{
    public function execute(User $user, Folder $folder): void
    {
        if (! $folder->workspace->hasMember($user)) {
            throw ValidationException::withMessages(['folder' => 'Non hai accesso a questa cartella.']);
        }

        DB::transaction(function () use ($folder): void {
            $folder->boards()
                ->update(['folder_id' => null]);

            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->where('parent_id', $folder->id)
                ->update(['parent_id' => null]);

            $folder->delete();
        });
    }
}
