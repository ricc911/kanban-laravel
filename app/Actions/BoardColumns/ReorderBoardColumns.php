<?php

namespace App\Actions\BoardColumns;

use App\Events\BoardChanged;
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
        if (! $board->workspace->canEditContent($user)) {
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

        DB::transaction(function () use ($board, $columnIds): void {
            $currentOrder = $board->columns()
                ->orderBy('position')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $requestedOrder = array_map('intval', $columnIds);

            if ($currentOrder === $requestedOrder) {
                return;
            }

            $positions = [];
            foreach ($columnIds as $index => $columnId) {
                $position = ($index + 1) * 1000;
                $board->columns()
                    ->whereKey($columnId)
                    ->update(['position' => $position]);
                $positions[] = ['id' => (int) $columnId, 'position' => $position];
            }
            BoardChanged::dispatch('columns.reordered', (int) $board->workspace_id, (int) $board->id, [
                'columns' => $positions,
            ]);
        });
    }
}
