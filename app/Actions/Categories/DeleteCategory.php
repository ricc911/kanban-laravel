<?php

namespace App\Actions\Categories;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Category;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Category $category
    ): void {
        if (! $category->board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'category' => 'Non hai accesso a questa categoria.',
            ]);
        }

        DB::transaction(function () use ($user, $category): void {
            $board = $category->board;
            $payload = RealtimePayload::category($category);
            $this->logger->execute($user, $board->workspace, 'category.deleted', $board, $category, ['category_name' => $category->name]);
            $category->delete();
            BoardChanged::dispatch('category.deleted', (int) $board->workspace_id, (int) $board->id, [
                'category_id' => (int) $category->id,
                'category' => $payload,
            ]);
        });
    }
}
