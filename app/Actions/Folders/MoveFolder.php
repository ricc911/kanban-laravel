<?php

namespace App\Actions\Folders;

use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveFolder
{
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

        $archived = $parent?->archived ?? false;
        $folderIds = $this->folderTreeIds($folder);

        DB::transaction(function () use ($folder, $parent, $folderIds, $archived): void {
            $folder->update(['parent_id' => $parent?->id]);

            Folder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('id', $folderIds)
                ->update(['archived' => $archived]);

            Board::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereIn('folder_id', $folderIds)
                ->update(['archived' => $archived]);
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
