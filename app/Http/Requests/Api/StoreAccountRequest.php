<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Account::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'exists:branches,id'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9\-\.]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_active' => ['sometimes', 'boolean'],
            'is_control_account' => ['sometimes', 'boolean'],
        ];
    }
}
