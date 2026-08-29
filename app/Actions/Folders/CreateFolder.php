<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class CreateFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Workspace $workspace,
        string $name,
        ?Folder $parent = null,
        ?string $color = null
    ): Folder {
        if (! $workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'workspace' => 'Non fai parte di questo workspace.',
            ]);
        }

        if (
            $parent !== null &&
            $parent->workspace_id !== $workspace->id
        ) {
            throw ValidationException::withMessages([
                'parent' => 'La cartella appartiene a un altro workspace.',
            ]);
        }

        $folder = Folder::create([
            'workspace_id' => $workspace->id,
            'parent_id' => $parent?->id,
            'name' => trim($name),
            'color' => $color,
        ]);
        $this->logger->execute($user, $workspace, 'folder.created', null, $folder, ['folder_name' => $folder->name]);

        return $folder;
    }
}
