<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board, string $name, ?string $description = null, ?string $color = null): Board
    {
        if (! $board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $changes = [];
        foreach (['name' => [$board->name, trim($name)], 'description' => [$board->description, $description], 'color' => [$board->color, $color]] as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return DB::transaction(function () use ($user, $board, $name, $description, $color, $changes): Board {
            $board->update([
                'name' => trim($name),
                'description' => $description,
                'color' => $color,
            ]);
            $freshBoard = $board->fresh();
            if ($changes) {
                $this->logger->execute($user, $board->workspace, 'board.updated', $freshBoard, $freshBoard, ['board_name' => $freshBoard->name, 'changes' => $changes]);
                BoardChanged::dispatch('board.updated', (int) $board->workspace_id, (int) $freshBoard->id, [
                    'board' => RealtimePayload::board($freshBoard),
                ], true);
            }

            return $freshBoard;
        });
    }
}
