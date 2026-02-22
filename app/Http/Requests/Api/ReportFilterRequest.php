<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $format = (string) $this->input('format', 'json');

        if (in_array($format, ['pdf', 'excel'], true)) {
            return $user->can('reports.export');
        }

        return $user->can('reports.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'period' => ['nullable', 'in:monthly,quarterly,annually'],
            'format' => ['nullable', 'in:json,pdf,excel'],
        ];
    }
}
