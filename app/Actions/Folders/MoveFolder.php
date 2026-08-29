<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Folder $folder, ?Folder $parent = null): Folder
    {
        if (! $folder->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'folder' => 'Non hai accesso a questa cartella.',
            ]);
        }

        if ($parent !== null && $parent->workspace_id !== $folder->workspace_id) {
            throw ValidationException::withMessages([
                'parent' => 'La cartella appartiene a un altro workspace.',
            ]);
        }

        if ($parent?->id === $folder->id) {
            throw ValidationException::withMessages([
                'parent' => 'Una cartella non può contenere se stessa.',
            ]);
        }

        $ancestor = $parent;
        while ($ancestor !== null) {
            if ($ancestor->parent_id === $folder->id) {
                throw ValidationException::withMessages([
                    'parent' => 'Una cartella non può essere spostata dentro una propria sottocartella.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }

        $folder->loadMissing('parent');
        $fromParent = $folder->parent;
        $archived = $parent?->archived ?? false;
        $folderIds = $this->folderTreeIds($folder);

        DB::transaction(function () use ($user, $folder, $parent, $folderIds, $archived, $fromParent): void {
            $folder->update(['parent_id' => $parent?->id]);

            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('id', $folderIds)
                ->update(['archived' => $archived]);

            Board::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('folder_id', $folderIds)
                ->update(['archived' => $archived]);

            if ($fromParent?->id !== $parent?->id) {
                $this->logger->execute($user, $folder->workspace, 'folder.moved', null, $folder, [
                    'folder_name' => $folder->name,
                    'from_parent_id' => $fromParent?->id,
                    'from_parent_name' => $fromParent?->name,
                    'to_parent_id' => $parent?->id,
                    'to_parent_name' => $parent?->name,
                ]);
            }
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
