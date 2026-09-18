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

    /** @param array<int, string>|null $providedFields */
    public function execute(
        User $user,
        Category $category,
        string $name,
        ?string $color = null,
        ?array $providedFields = null
    ): Category {
        if (! $category->board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'category' => 'Non hai accesso a questa categoria.',
            ]);
        }

        $updates = [];
        $changes = [];
        $values = ['name' => trim($name), 'color' => $color];
        foreach ($providedFields ?? array_keys($values) as $field) {
            $old = $category->getAttribute($field);
            $new = $values[$field];
            $updates[$field] = $new;
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return DB::transaction(function () use ($user, $category, $updates, $changes): Category {
            $category->update($updates);
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
