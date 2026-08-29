<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board, string $name, ?string $description = null, ?string $color = null): Board
    {
        if (! $board->workspace->hasMember($user)) {
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
        $board->update([
            'name' => trim($name),
            'description' => $description,
            'color' => $color,
        ]);
        if ($changes) {
            $this->logger->execute($user, $board->workspace, 'board.updated', $board, $board, ['board_name' => $board->name, 'changes' => $changes]);
        }

        return $board->fresh();
    }
}
