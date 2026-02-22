<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:15', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}
