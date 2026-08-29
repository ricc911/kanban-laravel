<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Events\WorkspaceChanged;
use App\Models\Folder;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Folder $folder): void
    {
        if (! $folder->workspace->canEditContent($user)) {
            throw ValidationException::withMessages(['folder' => 'Non hai accesso a questa cartella.']);
        }

        DB::transaction(function () use ($user, $folder): void {
            $workspace = $folder->workspace;
            $folderPayload = RealtimePayload::folder($folder);
            $folderId = (int) $folder->id;

            $this->logger->execute($user, $workspace, 'folder.deleted', null, $folder, ['folder_name' => $folder->name]);

            $folder->boards()
                ->update(['folder_id' => null]);

            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->where('parent_id', $folder->id)
                ->update(['parent_id' => null]);

            $folder->delete();
            WorkspaceChanged::dispatch('folder.deleted', (int) $workspace->id, [
                'folder_id' => $folderId,
                'folder' => $folderPayload,
            ]);
        });
    }
}
