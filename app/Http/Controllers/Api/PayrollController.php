<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateControlNumberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GeneratePayrollRunRequest;
use App\Http\Requests\Api\StorePayrollPeriodRequest;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct(private readonly GenerateControlNumberAction $generateControlNumber)
    {
    }

    public function periods(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('payroll.view'), 403);

        return response()->json(
            PayrollPeriod::query()
                ->latest('start_date')
                ->paginate((int) $request->integer('per_page', 15)),
        );
    }

    public function storePeriod(StorePayrollPeriodRequest $request): JsonResponse
    {
        $period = PayrollPeriod::query()->create([
            ...$request->validated(),
            'status' => 'open',
        ]);

        return response()->json($period->refresh(), 201);
    }

    public function runs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('payroll.view'), 403);

        $query = PayrollRun::query()
            ->with([
                'period:id,name,start_date,end_date,pay_date,status',
                'creator:id,name',
                'approver:id,name',
            ])
            ->latest('id');

        if ($request->filled('payroll_period_id')) {
            $query->where('payroll_period_id', (int) $request->integer('payroll_period_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function showRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($request->user()?->can('payroll.view'), 403);

        return response()->json($payrollRun->load([
            'period:id,name,start_date,end_date,pay_date,status',
            'lines.employee:id,employee_no,first_name,last_name,department,position',
            'creator:id,name',
            'approver:id,name',
        ]));
    }

    public function generateRun(GeneratePayrollRunRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $run = DB::transaction(function () use ($payload, $request): PayrollRun {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail((int) $payload['payroll_period_id']);

            if ($period->status !== 'open') {
                abort(422, 'Payroll period is not open.');
            }

            $existingDraft = PayrollRun::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', 'draft')
                ->exists();

            if ($existingDraft) {
                abort(422, 'A draft payroll run already exists for this period.');
            }

            $periodStartDate = CarbonImmutable::parse((string) $period->start_date);
            $periodEndDate = CarbonImmutable::parse((string) $period->end_date);
            $daysInPeriod = max(1, $periodStartDate->diffInDays($periodEndDate) + 1);
            $proration = $daysInPeriod / 30;
            $sssEmployeeRate = (float) ($payload['sss_employee_rate'] ?? 0.045);
            $sssEmployeeCap = (float) ($payload['sss_employee_cap'] ?? 1125);
            $philhealthRate = (float) ($payload['philhealth_rate'] ?? 0.05);
            $philhealthEmployeeCap = (float) ($payload['philhealth_employee_cap'] ?? 2500);
            $pagibigEmployeeRate = (float) ($payload['pagibig_employee_rate'] ?? 0.02);
            $pagibigEmployeeCap = (float) ($payload['pagibig_employee_cap'] ?? 100);
            $withholdingTaxRate = (float) ($payload['withholding_tax_rate'] ?? 0);

            $employees = Employee::query()
                ->where('is_active', true)
                ->orderBy('employee_no')
                ->get(['id', 'employee_no', 'first_name', 'last_name', 'monthly_rate']);

            $run = PayrollRun::query()->create([
                'payroll_period_id' => $period->id,
                'run_number' => $this->generateControlNumber->execute('PAYRUN'),
                'status' => 'draft',
                'gross_total' => 0,
                'deduction_total' => 0,
                'net_total' => 0,
                'created_by' => $request->user()?->id,
            ]);

            $grossTotal = 0.0;
            $deductionTotal = 0.0;
            $netTotal = 0.0;

            foreach ($employees as $employee) {
                $monthlyRate = (float) ($employee->monthly_rate ?? 0);
                $grossAmount = round($monthlyRate * $proration, 2);

                $sssEmployee = round(min($grossAmount * $sssEmployeeRate, $sssEmployeeCap), 2);
                $philhealthEmployee = round(min(($grossAmount * $philhealthRate) / 2, $philhealthEmployeeCap), 2);
                $pagibigEmployee = round(min($grossAmount * $pagibigEmployeeRate, $pagibigEmployeeCap), 2);

                $taxablePay = round(max(0, $grossAmount - ($sssEmployee + $philhealthEmployee + $pagibigEmployee)), 2);
                $withholdingTax = round($taxablePay * $withholdingTaxRate, 2);

                $deductionAmount = round($sssEmployee + $philhealthEmployee + $pagibigEmployee + $withholdingTax, 2);
                $netAmount = round($grossAmount - $deductionAmount, 2);

                $run->lines()->create([
                    'employee_id' => $employee->id,
                    'gross_amount' => $grossAmount,
                    'deduction_amount' => $deductionAmount,
                    'net_amount' => $netAmount,
                    'breakdown' => [
                        'country' => 'PH',
                        'currency' => 'PHP',
                        'monthly_rate' => $monthlyRate,
                        'proration_days' => $daysInPeriod,
                        'proration_factor' => $proration,
                        'sss_employee' => $sssEmployee,
                        'philhealth_employee' => $philhealthEmployee,
                        'pagibig_employee' => $pagibigEmployee,
                        'taxable_pay' => $taxablePay,
                        'withholding_tax' => $withholdingTax,
                        'rates' => [
                            'sss_employee_rate' => $sssEmployeeRate,
                            'philhealth_rate' => $philhealthRate,
                            'pagibig_employee_rate' => $pagibigEmployeeRate,
                            'withholding_tax_rate' => $withholdingTaxRate,
                        ],
                    ],
                ]);

                $grossTotal += $grossAmount;
                $deductionTotal += $deductionAmount;
                $netTotal += $netAmount;
            }

            $run->update([
                'gross_total' => round($grossTotal, 2),
                'deduction_total' => round($deductionTotal, 2),
                'net_total' => round($netTotal, 2),
            ]);

            return $run;
        });

        return response()->json($run->load('period:id,name,start_date,end_date,pay_date,status'), 201);
    }

    public function approveRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($request->user()?->can('payroll.update'), 403);

        $userId = (int) $request->user()->id;

        if ($payrollRun->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft payroll runs can be approved.',
            ], 422);
        }

        if ((int) $payrollRun->created_by === $userId) {
            return response()->json([
                'message' => 'Maker-checker violation: you cannot approve your own payroll run.',
            ], 422);
        }

        $payrollRun->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return response()->json($payrollRun->refresh()->load('period:id,name,start_date,end_date,pay_date,status'));
    }

    public function postRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($request->user()?->can('payroll.update'), 403);

        if ($payrollRun->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved payroll runs can be posted.',
            ], 422);
        }

        DB::transaction(function () use ($payrollRun): void {
            $payrollRun->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $payrollRun->period()->update([
                'status' => 'closed',
            ]);
        });

        return response()->json($payrollRun->refresh()->load('period:id,name,start_date,end_date,pay_date,status'));
    }
}
