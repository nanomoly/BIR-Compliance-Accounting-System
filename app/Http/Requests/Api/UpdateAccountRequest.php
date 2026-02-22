<?php

namespace App\Http\Requests\Api;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Account $account */
        $account = $this->route('account');

        return $this->user()?->can('update', $account) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_control_account' => ['sometimes', 'boolean'],
        ];
    }
}
