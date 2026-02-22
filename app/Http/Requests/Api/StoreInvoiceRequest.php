<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('e_invoices.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => [
                Rule::requiredIf(fn () => in_array((string) $this->input('invoice_type'), ['sales', 'service'], true)),
                'nullable',
                'exists:customers,id',
            ],
            'supplier_id' => [
                Rule::requiredIf(fn () => (string) $this->input('invoice_type') === 'purchase'),
                'nullable',
                'exists:suppliers,id',
            ],
            'journal_entry_id' => ['nullable', 'exists:journal_entries,id'],
            'invoice_type' => ['required', 'in:sales,service,purchase'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
