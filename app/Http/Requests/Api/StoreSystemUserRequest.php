<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSystemUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', 'exists:roles,name'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }
}
