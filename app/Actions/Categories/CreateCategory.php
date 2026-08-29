<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\Category;
use App\Models\User;
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

        $position = ($board->categories()->max('position') ?? 0) + 1000;

        $category = $board->categories()->create([
            'name' => trim($name),
            'color' => $color,
            'position' => $position,
        ]);
        $this->logger->execute($user, $board->workspace, 'category.created', $board, $category, ['category_name' => $category->name]);

        return $category;
    }
}
