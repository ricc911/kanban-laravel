<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAssigneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return $this->isMethod('delete')
            ? []
            : ['user_id' => ['required', 'integer', 'exists:users,id']];
    }
}
