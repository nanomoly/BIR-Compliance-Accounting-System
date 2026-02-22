<?php

namespace App\Http\Requests\Api;

use App\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JournalEntry::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'journal_type' => ['required', 'in:general,sales,purchase,cash_receipts,cash_disbursements'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.customer_id' => ['nullable', 'exists:customers,id'],
            'lines.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'lines.*.particulars' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ];
    }
}
