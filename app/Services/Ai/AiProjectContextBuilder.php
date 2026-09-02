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

        $priority = ['high' => 0, 'medium' => 0, 'low' => 0, 'none' => 0];
        $columns = [];
        foreach ($board->columns as $column) {
            $columns[$column->name] = 0;
        }
        foreach ($tasks as $task) {
            $bucket = in_array($task['priority'], ['high', 'medium', 'low'], true) ? $task['priority'] : 'none';
            $priority[$bucket]++;
            $columns[$task['column']] = ($columns[$task['column']] ?? 0) + 1;
        }
        $stats = [
            'task_count' => count($tasks),
            'priority' => $priority,
            'without_assignees' => count(array_filter($tasks, fn (array $task): bool => $task['assignees_count'] === 0)),
            'without_due_date' => count(array_filter($tasks, fn (array $task): bool => $task['due_at'] === null)),
            'with_comments' => count(array_filter($tasks, fn (array $task): bool => (int) ($task['comments_count'] ?? 0) > 0)),
            'columns' => $columns,
        ];

        return ['board' => ['id' => $board->id, 'name' => $board->name, 'description' => $board->description], 'columns' => $board->columns->map(fn ($column): array => ['id' => $column->id, 'name' => $column->name, 'position' => $column->position])->values()->all(), 'categories' => $board->categories->map(fn ($category): array => ['id' => $category->id, 'name' => $category->name])->values()->all(), 'stats' => $stats, 'tasks' => $tasks];
    }
}
