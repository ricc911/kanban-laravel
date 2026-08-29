<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Workspace $workspace,
        string $name,
        ?Folder $folder = null,
        ?string $color = null
    ): Board {
        if (! $workspace->hasMember($user)) {
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

        return DB::transaction(function () use ($user, $workspace, $folder, $name, $color) {
            $board = Board::create([
                'workspace_id' => $workspace->id,
                'folder_id' => $folder?->id,
                'name' => trim($name),
                'color' => $color,
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

            $this->logger->execute($user, $workspace, 'board.created', $board, $board, ['board_name' => $board->name]);

            return $board->load('columns');
        });
    }
}
