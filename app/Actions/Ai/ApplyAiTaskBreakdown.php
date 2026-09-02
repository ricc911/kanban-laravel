<?php

namespace App\Actions\Ai;

use App\Actions\Tasks\CreateTask;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplyAiTaskBreakdown
{
    public function execute(User $user, Board $board, int $columnId, array $tasks, CreateTask $create): array
    {
        $column = $board->columns()->findOrFail($columnId);

        return DB::transaction(function () use ($user, $board, $column, $tasks, $create): array {
            $created = [];
            foreach ($tasks as $task) {
                $created[] = $create->execute($user, $board, $column, $task['title'], $task['description'] ?? null, null, $task['priority'] ?? null);
            }

            return $created;
        });
    }
}
