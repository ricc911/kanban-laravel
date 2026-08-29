<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Category;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($user, $category, $name, $color, $changes): Category {
            $category->update([
                'name' => trim($name),
                'color' => $color,
            ]);
            $freshCategory = $category->fresh();
            if ($changes) {
                $this->logger->execute($user, $category->board->workspace, 'category.updated', $category->board, $freshCategory, ['category_name' => $freshCategory->name, 'changes' => $changes]);
                BoardChanged::dispatch('category.updated', (int) $category->board->workspace_id, (int) $category->board_id, [
                    'category' => RealtimePayload::category($freshCategory),
                ]);
            }

            return $freshCategory;
        });
    }
}
