<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function execute(
        User $user,
        Category $category
    ): void {
        if (! $category->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'category' => 'Non hai accesso a questa categoria.',
            ]);
        }

        $category->delete();
    }
}
