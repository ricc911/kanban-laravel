<?php

namespace App\Services\Ai;

use App\Models\Board;

class AiProjectContextBuilder
{
    public function build(Board $board): array
    {
        $board->load(['columns.tasks' => fn ($query) => $query->withCount('comments')->with('assignees')->where('archived', false), 'categories']);

        $tasks = $board->columns
            ->flatMap(fn ($column) => $column->tasks->map(fn ($task): array => ['id' => $task->id, 'title' => $task->title, 'description' => $task->description, 'column' => $column->name, 'priority' => $task->priority, 'due_at' => $task->due_at?->toISOString(), 'comments_count' => $task->comments_count, 'assignees_count' => $task->assignees->count()]))
            ->sortByDesc(fn (array $task): array => [$task['priority'] === 'high' ? 2 : ($task['priority'] === 'medium' ? 1 : 0), $task['due_at'] !== null ? 1 : 0, $task['id']])
            ->take(200)
            ->values()
            ->all();

        return ['board' => ['id' => $board->id, 'name' => $board->name, 'description' => $board->description], 'columns' => $board->columns->map(fn ($column): array => ['id' => $column->id, 'name' => $column->name, 'position' => $column->position])->values()->all(), 'categories' => $board->categories->map(fn ($category): array => ['id' => $category->id, 'name' => $category->name])->values()->all(), 'tasks' => $tasks];
    }
}
