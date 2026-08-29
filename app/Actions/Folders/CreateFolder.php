<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Events\WorkspaceChanged;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
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
        if (! $workspace->canEditContent($user)) {
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

        return DB::transaction(function () use ($user, $workspace, $name, $parent, $color): Folder {
            $folder = Folder::create([
                'workspace_id' => $workspace->id,
                'parent_id' => $parent?->id,
                'name' => trim($name),
                'color' => $color,
            ]);
            $this->logger->execute($user, $workspace, 'folder.created', null, $folder, ['folder_name' => $folder->name]);
            WorkspaceChanged::dispatch('folder.created', (int) $workspace->id, [
                'folder' => RealtimePayload::folder($folder),
            ]);

            return $folder;
        });
    }
}
