<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyAiTaskBreakdownRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['column_id' => ['required', 'integer'], 'tasks' => ['required', 'array', 'min:1', 'max:10'], 'tasks.*.title' => ['required', 'string', 'max:255'], 'tasks.*.description' => ['nullable', 'string'], 'tasks.*.priority' => ['nullable', 'in:low,medium,high']];
    }
}
