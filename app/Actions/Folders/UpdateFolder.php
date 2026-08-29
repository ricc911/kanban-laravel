<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Events\WorkspaceChanged;
use App\Models\Folder;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Folder $folder, string $name, ?string $color = null): Folder
    {
        if (! $folder->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'folder' => 'Non hai accesso a questa cartella.',
            ]);
        }

        $changes = [];
        foreach (['name' => [$folder->name, trim($name)], 'color' => [$folder->color, $color]] as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return DB::transaction(function () use ($user, $folder, $name, $color, $changes): Folder {
            $folder->update([
                'name' => trim($name),
                'color' => $color,
            ]);
            $freshFolder = $folder->fresh();
            if ($changes) {
                $this->logger->execute($user, $folder->workspace, 'folder.updated', null, $freshFolder, ['folder_name' => $freshFolder->name, 'changes' => $changes]);
                WorkspaceChanged::dispatch('folder.updated', (int) $folder->workspace_id, [
                    'folder' => RealtimePayload::folder($freshFolder),
                ]);
            }

            return $freshFolder;
        });
    }
}
