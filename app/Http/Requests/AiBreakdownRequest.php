<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AiBreakdownRequest extends FormRequest
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
        return ['request_id' => ['required', 'uuid'], 'reasoning_level' => ['required', 'in:low,medium,high'], 'objective' => ['required', 'string', 'max:2000'], 'desired_count' => ['required', 'integer', 'min:3', 'max:10']];
    }
}
