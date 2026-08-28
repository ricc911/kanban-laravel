<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBoard
{
    public function execute(
        User $user,
        Workspace $workspace,
        string $name,
        ?Folder $folder = null
    ): Board {
        if (!$workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'workspace' => 'Non fai parte di questo workspace.',
            ]);
        }

        if (
            $folder !== null &&
            $folder->workspace_id !== $workspace->id
        ) {
            throw ValidationException::withMessages([
                'folder' => 'La cartella appartiene a un altro workspace.',
            ]);
        }

        $workspace->loadMissing('owner.subscription.plan');

        $plan = $workspace->owner->subscription->plan;

        $projectsCount = $workspace->boards()->count();

        if (
            $plan->max_projects !== null &&
            $projectsCount >= $plan->max_projects
        ) {
            throw ValidationException::withMessages([
                'board' => 'Hai raggiunto il limite di progetti del piano.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $folder, $name) {
            $board = Board::create([
                'workspace_id' => $workspace->id,
                'folder_id' => $folder?->id,
                'name' => trim($name),
            ]);

            $board->columns()->createMany([
                [
                    'name' => 'To do',
                    'position' => 1000,
                ],
                [
                    'name' => 'Doing',
                    'position' => 2000,
                ],
                [
                    'name' => 'Done',
                    'position' => 3000,
                ],
            ]);

            return $board->load('columns');
        });
    }
}
