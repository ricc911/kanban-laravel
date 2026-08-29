<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\Category;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Board $board,
        string $name,
        ?string $color = null
    ): Category {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        return DB::transaction(function () use ($user, $board, $name, $color): Category {
            $position = ($board->categories()->max('position') ?? 0) + 1000;

            $category = $board->categories()->create([
                'name' => trim($name),
                'color' => $color,
                'position' => $position,
            ]);
            $this->logger->execute($user, $board->workspace, 'category.created', $board, $category, ['category_name' => $category->name]);
            BoardChanged::dispatch('category.created', (int) $board->workspace_id, (int) $board->id, [
                'category' => RealtimePayload::category($category),
            ]);

            return $category;
        });
    }
}
