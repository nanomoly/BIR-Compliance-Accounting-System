<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'sss_employee_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'sss_employee_cap' => ['nullable', 'numeric', 'min:0'],
            'philhealth_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'philhealth_employee_cap' => ['nullable', 'numeric', 'min:0'],
            'pagibig_employee_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'pagibig_employee_cap' => ['nullable', 'numeric', 'min:0'],
            'withholding_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
