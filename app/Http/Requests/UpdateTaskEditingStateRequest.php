<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskEditingStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        if (! $this->user() || ! $task instanceof Task) {
            return false;
        }

        $workspace = $task->board->workspace;

        return $workspace->isOwner($this->user()) || $workspace->hasMember($this->user());
    }

    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
            'session_id' => ['required', 'uuid'],
        ];
    }
}
