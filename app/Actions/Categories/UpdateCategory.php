<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateCategory
{
    public function execute(
        User $user,
        Category $category,
        string $name,
        ?string $color = null
    ): Category {
        if (! $category->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'category' => 'Non hai accesso a questa categoria.',
            ]);
        }

        $category->update([
            'name' => trim($name),
            'color' => $color,
        ]);

        return $category->fresh();
    }
}
