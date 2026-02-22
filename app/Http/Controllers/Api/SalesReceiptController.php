<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateControlNumberAction;
use App\DTOs\JournalEntryLineData;
use App\DTOs\StoreJournalEntryData;
use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\JournalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSalesReceiptRequest;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\SalesReceipt;
use App\Services\Accounting\JournalEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReceiptController extends Controller
{
    public function __construct(
        private readonly GenerateControlNumberAction $generateControlNumber,
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('collections.view'), 403);

        $query = SalesReceipt::query()
            ->with([
                'invoice:id,invoice_number,total_amount',
                'customer:id,code,name',
                'branch:id,code,name',
                'journalEntry:id,entry_number,status,posted_at',
            ])
            ->latest('receipt_date')
            ->latest('id');

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('collections.view'), 403);

        $invoices = Invoice::query()
            ->with(['customer:id,code,name', 'branch:id,code,name'])
            ->whereIn('invoice_type', [InvoiceType::SALES->value, InvoiceType::SERVICE->value])
            ->where('status', InvoiceStatus::ISSUED->value)
            ->latest('invoice_date')
            ->get(['id', 'invoice_number', 'invoice_date', 'branch_id', 'customer_id', 'total_amount']);

        $invoiceIds = $invoices->pluck('id')->all();
        $paidTotals = SalesReceipt::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->selectRaw('invoice_id, SUM(amount) as paid_total')
            ->groupBy('invoice_id')
            ->pluck('paid_total', 'invoice_id');

        $mapped = $invoices->map(function (Invoice $invoice) use ($paidTotals): array {
            $paid = (float) ($paidTotals[$invoice->id] ?? 0);
            $total = (float) $invoice->total_amount;

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => optional($invoice->invoice_date)->toDateString(),
                'total_amount' => $total,
                'paid_amount' => $paid,
                'balance_due' => max(0, round($total - $paid, 2)),
                'customer' => $invoice->customer,
                'branch' => $invoice->branch,
            ];
        })->values();

        return response()->json([
            'invoices' => $mapped,
        ]);
    }

    public function store(StoreSalesReceiptRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $receipt = DB::transaction(function () use ($payload, $request): SalesReceipt {
            $invoice = Invoice::query()
                ->with('customer:id,code,name', 'branch:id,code,name', 'journalEntry.lines')
                ->lockForUpdate()
                ->findOrFail((int) $payload['invoice_id']);

            if (! in_array($invoice->invoice_type->value, [InvoiceType::SALES->value, InvoiceType::SERVICE->value], true)) {
                abort(422, 'Collections are only applicable to sales/service invoices.');
            }

            if ($invoice->status !== InvoiceStatus::ISSUED) {
                abort(422, 'Only issued invoices can receive collections.');
            }

            if (! $invoice->customer_id || ! $invoice->branch_id) {
                abort(422, 'Invoice must have customer and branch before collection.');
            }

            $paidTotal = (float) SalesReceipt::query()->where('invoice_id', $invoice->id)->sum('amount');
            $totalAmount = (float) $invoice->total_amount;
            $balanceDue = round($totalAmount - $paidTotal, 2);
            $amount = (float) $payload['amount'];

            if ($amount > $balanceDue) {
                abort(422, 'Receipt amount exceeds invoice balance due.');
            }

            $userId = (int) $request->user()->id;

            [$cashOrBankAccount, $accountsReceivableAccount] = $this->resolvePostingAccounts(
                $invoice,
                (string) $payload['payment_method'],
            );

            $receipt = SalesReceipt::query()->create([
                'invoice_id' => $invoice->id,
                'branch_id' => $invoice->branch_id,
                'customer_id' => $invoice->customer_id,
                'receipt_number' => $this->generateControlNumber->execute('RCPT'),
                'receipt_date' => $payload['receipt_date'],
                'amount' => $amount,
                'payment_method' => $payload['payment_method'],
                'reference_no' => $payload['reference_no'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
                'created_by' => $userId,
            ]);

            $journalEntry = $this->journalEntryService->create(new StoreJournalEntryData(
                branchId: (int) $invoice->branch_id,
                journalType: JournalType::CASH_RECEIPTS,
                entryDate: CarbonImmutable::parse((string) $payload['receipt_date']),
                description: 'Collection receipt '.$receipt->receipt_number.' for '.$invoice->invoice_number,
                referenceNo: $receipt->receipt_number,
                lines: [
                    new JournalEntryLineData(
                        accountId: $cashOrBankAccount->id,
                        debit: $amount,
                        credit: 0,
                        customerId: (int) $invoice->customer_id,
                        particulars: 'Collection from customer',
                    ),
                    new JournalEntryLineData(
                        accountId: $accountsReceivableAccount->id,
                        debit: 0,
                        credit: $amount,
                        customerId: (int) $invoice->customer_id,
                        particulars: 'Apply against invoice '.$invoice->invoice_number,
                    ),
                ],
            ), $userId);

            $postedJournalEntry = $this->journalEntryService->post($journalEntry, $userId);

            $receipt->journal_entry_id = $postedJournalEntry->id;
            $receipt->save();

            return $receipt;
        });

        return response()->json($receipt->load([
            'invoice:id,invoice_number,total_amount',
            'customer:id,code,name',
            'branch:id,code,name',
            'journalEntry:id,entry_number,status,posted_at',
        ]), 201);
    }

    /**
     * @return array{0: Account, 1: Account}
     */
    private function resolvePostingAccounts(Invoice $invoice, string $paymentMethod): array
    {
        $cashOrBank = $this->resolveCashOrBankAccount((int) $invoice->branch_id, $paymentMethod);
        $accountsReceivable = $this->resolveAccountsReceivableAccount($invoice);

        return [$cashOrBank, $accountsReceivable];
    }

    private function resolveCashOrBankAccount(int $branchId, string $paymentMethod): Account
    {
        if ($paymentMethod === 'bank' || $paymentMethod === 'check' || $paymentMethod === 'online') {
            $bankAccount = Account::query()
                ->where('branch_id', $branchId)
                ->where('type', AccountType::ASSET->value)
                ->where('is_active', true)
                ->where('name', 'like', '%Bank%')
                ->orderBy('id')
                ->first();

            if ($bankAccount) {
                return $bankAccount;
            }
        }

        $cashAccount = Account::query()
            ->where('branch_id', $branchId)
            ->where('type', AccountType::ASSET->value)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('code', '1-1000')
                    ->orWhere('name', 'like', '%Cash%');
            })
            ->orderBy('id')
            ->first();

        if ($cashAccount) {
            return $cashAccount;
        }

        throw ValidationException::withMessages([
            'account' => 'No active Cash/Bank asset account found for this branch. Set up Chart of Accounts first.',
        ]);
    }

    private function resolveAccountsReceivableAccount(Invoice $invoice): Account
    {
        $arLine = $invoice->journalEntry?->lines
            ?->first(fn ($line): bool => (int) $line->customer_id === (int) $invoice->customer_id && (float) $line->debit > 0);

        if ($arLine) {
            $account = Account::query()
                ->where('id', (int) $arLine->account_id)
                ->where('branch_id', (int) $invoice->branch_id)
                ->where('is_active', true)
                ->first();

            if ($account) {
                return $account;
            }
        }

        $fallback = Account::query()
            ->where('branch_id', (int) $invoice->branch_id)
            ->where('type', AccountType::ASSET->value)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('code', '1-1100')
                    ->orWhere('name', 'like', '%Receivable%');
            })
            ->orderBy('id')
            ->first();

        if ($fallback) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'account' => 'No active Accounts Receivable account found for this branch or linked invoice journal.',
        ]);
    }
}
