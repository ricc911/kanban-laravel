<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderBoardColumns
{
    public function execute(
        User $user,
        Board $board,
        array $columnIds
    ): void {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $actualIds = $board->columns()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $requestedIds = collect($columnIds)
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($actualIds !== $requestedIds) {
            throw ValidationException::withMessages([
                'columns' => 'L’elenco delle colonne non è valido.',
            ]);
        }

        DB::transaction(function () use ($board, $columnIds) {
            foreach ($columnIds as $index => $columnId) {
                $board->columns()
                    ->whereKey($columnId)
                    ->update([
                        'position' => ($index + 1) * 1000,
                    ]);
            }
        });
    }
}
