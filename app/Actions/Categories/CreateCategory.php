<?php

namespace App\Actions\Categories;

use App\Models\Board;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
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

        return $board->categories()->create([
            'name' => trim($name),
            'color' => $color,
            'position' => $position,
        ]);
    }
}
