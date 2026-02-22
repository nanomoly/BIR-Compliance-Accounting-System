<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user_access.assign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
