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

    /** @param array<int, string>|null $providedFields */
    public function execute(User $user, Folder $folder, string $name, ?string $color = null, ?array $providedFields = null): Folder
    {
        if (! $folder->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'folder' => 'Non hai accesso a questa cartella.',
            ]);
        }

        $updates = [];
        $changes = [];
        $values = ['name' => trim($name), 'color' => $color];
        foreach ($providedFields ?? array_keys($values) as $field) {
            $old = $folder->getAttribute($field);
            $new = $values[$field];
            $updates[$field] = $new;
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return DB::transaction(function () use ($user, $folder, $updates, $changes): Folder {
            $folder->update($updates);
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
