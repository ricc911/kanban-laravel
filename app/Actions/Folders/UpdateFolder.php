<?php

namespace App\Actions\Folders;

use App\Actions\Activity\LogActivity;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateFolder
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Folder $folder, string $name, ?string $color = null): Folder
    {
        if (! $folder->workspace->hasMember($user)) {
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
        $folder->update([
            'name' => trim($name),
            'color' => $color,
        ]);
        if ($changes) {
            $this->logger->execute($user, $folder->workspace, 'folder.updated', null, $folder, ['folder_name' => $folder->name, 'changes' => $changes]);
        }

        return $folder->fresh();
    }
}
