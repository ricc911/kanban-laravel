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

    /** @param array<int, string>|null $providedFields */
    public function execute(User $user, Board $board, string $name, ?string $description = null, ?string $color = null, ?array $providedFields = null): Board
    {
        if (! $board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $updates = [];
        $changes = [];
        $values = ['name' => trim($name), 'description' => $description, 'color' => $color];
        foreach ($providedFields ?? array_keys($values) as $field) {
            $old = $board->getAttribute($field);
            $new = $values[$field];
            $updates[$field] = $new;
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return DB::transaction(function () use ($user, $board, $updates, $changes): Board {
            $board->update($updates);
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
