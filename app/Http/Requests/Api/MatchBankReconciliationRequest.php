<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MatchBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('banking.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_statement_line_id' => ['required', 'exists:bank_statement_lines,id'],
            'bank_transaction_id' => ['required', 'exists:bank_transactions,id'],
        ];
    }
}
