<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('collections.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'exists:invoices,id'],
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank,check,online'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
