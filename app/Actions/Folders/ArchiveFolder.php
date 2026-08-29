<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Events\WorkspaceChanged;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Folder $folder, bool $archived): Folder
    {
        if (! $folder->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'folder' => 'Non hai accesso a questa cartella.',
            ]);
        }

        $folderIds = $this->folderTreeIds($folder);

        DB::transaction(function () use ($user, $folder, $folderIds, $archived): void {
            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('id', $folderIds)
                ->update(['archived' => $archived]);

            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereKey($folder->id)
                ->update(['parent_id' => null]);

            Board::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('folder_id', $folderIds)
                ->update(['archived' => $archived]);
            $this->logger->execute($user, $folder->workspace, $archived ? 'folder.archived' : 'folder.restored', null, $folder, ['folder_name' => $folder->name]);
            WorkspaceChanged::dispatch($archived ? 'folder.archived' : 'folder.restored', (int) $folder->workspace_id, [
                'folder' => RealtimePayload::folder($folder->fresh()),
            ]);
        });

        return $folder->fresh();
    }

    /** @return list<int> */
    private function folderTreeIds(Folder $folder): array
    {
        $ids = [$folder->id];

        for ($index = 0; $index < count($ids); $index++) {
            $children = Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->where('parent_id', $ids[$index])
                ->pluck('id');

            foreach ($children as $childId) {
                $ids[] = (int) $childId;
            }
        }

        return $ids;
    }
}
