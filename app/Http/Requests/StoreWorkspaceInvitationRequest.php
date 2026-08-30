<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identifier = trim((string) $this->input('email'));
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'email' => $isEmail
                ? ['required', 'email', 'max:255']
                : ['required', 'string', 'regex:/\A[a-zA-Z0-9_-]{3,30}\z/'],
            'role' => ['nullable', 'in:admin,member,viewer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => trim((string) $this->input('email'))]);
    }
}
