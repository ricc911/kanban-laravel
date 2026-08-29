<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateCategory
{
    public function __construct(private LogActivity $logger) {}

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

        $changes = [];
        foreach (['name' => [$category->name, trim($name)], 'color' => [$category->color, $color]] as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }
        $category->update([
            'name' => trim($name),
            'color' => $color,
        ]);
        if ($changes) {
            $this->logger->execute($user, $category->board->workspace, 'category.updated', $category->board, $category, ['category_name' => $category->name, 'changes' => $changes]);
        }

        return $category->fresh();
    }
}
