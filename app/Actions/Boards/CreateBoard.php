<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\PlanLimitService;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBoard
{
    public function __construct(private LogActivity $logger, private PlanLimitService $planLimits) {}

    public function execute(
        User $user,
        Workspace $workspace,
        string $name,
        ?Folder $folder = null,
        ?string $color = null
    ): Board {
        if (! $workspace->canEditContent($user)) {
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

        return DB::transaction(function () use ($user, $workspace, $folder, $name, $color) {
            $owner = User::query()->lockForUpdate()->findOrFail($workspace->owner_id);
            $workspace->setRelation('owner', $owner);

            if (! $this->planLimits->canCreateProject($workspace)) {
                throw ValidationException::withMessages([
                    'board' => 'Hai raggiunto il limite di progetti del piano.',
                ]);
            }

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
            BoardChanged::dispatch('board.created', (int) $workspace->id, (int) $board->id, [
                'board' => RealtimePayload::board($board),
            ], true);

            return $board->load('columns');
        });
    }
}
