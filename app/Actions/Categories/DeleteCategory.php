<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Category $category
    ): void {
        if (! $category->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'category' => 'Non hai accesso a questa categoria.',
            ]);
        }

        $board = $category->board;
        $name = $category->name;
        $category->delete();
        $this->logger->execute($user, $board->workspace, 'category.deleted', $board, null, ['category_name' => $name]);
    }
}
