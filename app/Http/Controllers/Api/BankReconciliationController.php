<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportBankStatementRequest;
use App\Http\Requests\Api\MatchBankReconciliationRequest;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    public function importStatement(ImportBankStatementRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = DB::transaction(function () use ($payload, $request): array {
            $statement = BankStatement::query()->create([
                'bank_account_id' => (int) $payload['bank_account_id'],
                'statement_date' => $payload['statement_date'],
                'opening_balance' => (float) $payload['opening_balance'],
                'closing_balance' => (float) $payload['closing_balance'],
                'created_by' => $request->user()?->id,
            ]);

            $lines = $this->parseCsvLines($payload['statement_csv']);
            foreach ($lines as $line) {
                $statement->lines()->create($line);
            }

            $reconciliation = BankReconciliation::query()->create([
                'bank_account_id' => $statement->bank_account_id,
                'bank_statement_id' => $statement->id,
                'status' => 'open',
                'statement_opening_balance' => (float) $statement->opening_balance,
                'statement_closing_balance' => (float) $statement->closing_balance,
                'cleared_balance' => (float) $statement->opening_balance,
                'difference' => round((float) $statement->closing_balance - (float) $statement->opening_balance, 2),
            ]);

            return [$statement, $reconciliation];
        });

        return response()->json([
            'statement' => $result[0]->load('lines'),
            'reconciliation' => $result[1]->load('statement'),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('banking.view'), 403);

        $query = BankReconciliation::query()
            ->with([
                'bankAccount:id,bank_name,account_number',
                'statement:id,bank_account_id,statement_date,opening_balance,closing_balance',
            ])
            ->latest('id');

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', (int) $request->integer('bank_account_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        abort_unless($request->user()?->can('banking.view'), 403);

        $bankReconciliation->load([
            'bankAccount:id,bank_name,account_number',
            'statement:id,bank_account_id,statement_date,opening_balance,closing_balance',
            'statement.lines' => fn ($query) => $query->orderBy('transaction_date')->orderBy('id'),
            'matches.bankTransaction:id,transaction_date,transaction_type,amount,reference_no,description',
            'matches.statementLine:id,transaction_date,transaction_type,amount,reference_no',
        ]);

        $matchedTransactionIds = $bankReconciliation->matches->pluck('bank_transaction_id')->all();

        $availableTransactions = BankTransaction::query()
            ->where('bank_account_id', $bankReconciliation->bank_account_id)
            ->whereNotIn('id', $matchedTransactionIds)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get([
                'id',
                'transaction_date',
                'transaction_type',
                'amount',
                'reference_no',
                'description',
            ]);

        $suggestedMatches = $this->buildSuggestedMatches(
            collect($bankReconciliation->statement?->lines ?? []),
            $availableTransactions,
        );

        return response()->json([
            'reconciliation' => $bankReconciliation,
            'available_transactions' => $availableTransactions,
            'suggested_matches' => $suggestedMatches,
        ]);
    }

    public function match(MatchBankReconciliationRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $payload = $request->validated();

        if ($bankReconciliation->status !== 'open') {
            return response()->json(['message' => 'Only open reconciliation can be matched.'], 422);
        }

        $result = DB::transaction(function () use ($payload, $bankReconciliation, $request): BankReconciliation {
            $line = BankStatementLine::query()
                ->where('bank_statement_id', $bankReconciliation->bank_statement_id)
                ->lockForUpdate()
                ->findOrFail((int) $payload['bank_statement_line_id']);

            if ($line->is_matched) {
                abort(422, 'Statement line is already matched.');
            }

            $transaction = BankTransaction::query()
                ->where('bank_account_id', $bankReconciliation->bank_account_id)
                ->findOrFail((int) $payload['bank_transaction_id']);

            $existingMatch = BankReconciliationMatch::query()
                ->where('bank_reconciliation_id', $bankReconciliation->id)
                ->where('bank_transaction_id', $transaction->id)
                ->exists();

            if ($existingMatch) {
                abort(422, 'Selected bank transaction is already matched in this reconciliation.');
            }

            if ($transaction->transaction_type !== $line->transaction_type) {
                abort(422, 'Transaction type mismatch between statement line and bank transaction.');
            }

            if (round((float) $transaction->amount, 2) !== round((float) $line->amount, 2)) {
                abort(422, 'Amount mismatch between statement line and bank transaction.');
            }

            BankReconciliationMatch::query()->create([
                'bank_reconciliation_id' => $bankReconciliation->id,
                'bank_statement_line_id' => $line->id,
                'bank_transaction_id' => $transaction->id,
                'matched_amount' => (float) $line->amount,
                'created_by' => $request->user()?->id,
            ]);

            $line->update([
                'is_matched' => true,
                'unmatched_reason' => null,
            ]);

            return $this->recompute($bankReconciliation->refresh());
        });

        return response()->json($result->load('statement'));
    }

    public function tagUnmatchedReason(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        abort_unless($request->user()?->can('banking.update'), 403);

        if ($bankReconciliation->status !== 'open') {
            return response()->json(['message' => 'Only open reconciliation can be updated.'], 422);
        }

        $payload = $request->validate([
            'bank_statement_line_id' => ['required', 'integer'],
            'unmatched_reason' => ['required', 'string', 'max:255'],
        ]);

        $line = BankStatementLine::query()
            ->where('bank_statement_id', $bankReconciliation->bank_statement_id)
            ->findOrFail((int) $payload['bank_statement_line_id']);

        if ($line->is_matched) {
            return response()->json(['message' => 'Cannot tag reason for matched statement line.'], 422);
        }

        $line->update([
            'unmatched_reason' => (string) $payload['unmatched_reason'],
        ]);

        return response()->json($line->refresh());
    }

    public function unmatch(Request $request, BankReconciliation $bankReconciliation, BankReconciliationMatch $bankReconciliationMatch): JsonResponse
    {
        abort_unless($request->user()?->can('banking.update'), 403);

        if ($bankReconciliation->status !== 'open') {
            return response()->json(['message' => 'Only open reconciliation can be updated.'], 422);
        }

        if ($bankReconciliationMatch->bank_reconciliation_id !== $bankReconciliation->id) {
            return response()->json(['message' => 'Match does not belong to selected reconciliation.'], 422);
        }

        $reconciliation = DB::transaction(function () use ($bankReconciliation, $bankReconciliationMatch): BankReconciliation {
            $line = BankStatementLine::query()->lockForUpdate()->findOrFail($bankReconciliationMatch->bank_statement_line_id);

            $bankReconciliationMatch->delete();

            $line->update([
                'is_matched' => false,
            ]);

            return $this->recompute($bankReconciliation->refresh());
        });

        return response()->json($reconciliation->load('statement'));
    }

    public function close(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        abort_unless($request->user()?->can('banking.update'), 403);

        if ($bankReconciliation->status !== 'open') {
            return response()->json(['message' => 'Reconciliation is already closed.'], 422);
        }

        $bankReconciliation = $this->recompute($bankReconciliation);

        if (abs((float) $bankReconciliation->difference) > 0.01) {
            return response()->json([
                'message' => 'Cannot close reconciliation with non-zero difference.',
                'difference' => $bankReconciliation->difference,
            ], 422);
        }

        $bankReconciliation->update([
            'status' => 'closed',
            'closed_by' => $request->user()?->id,
            'closed_at' => now(),
        ]);

        return response()->json($bankReconciliation->refresh()->load('statement'));
    }

    public function reopen(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        abort_unless($request->user()?->can('banking.update'), 403);

        if ($bankReconciliation->status !== 'closed') {
            return response()->json(['message' => 'Only closed reconciliation can be reopened.'], 422);
        }

        $bankReconciliation->update([
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
        ]);

        return response()->json($bankReconciliation->refresh()->load('statement'));
    }

    private function recompute(BankReconciliation $reconciliation): BankReconciliation
    {
        $reconciliation->loadMissing('matches.statementLine');

        $signedMatchedTotal = $reconciliation->matches->sum(function ($match): float {
            $line = $match->statementLine;
            $amount = (float) ($match->matched_amount ?? 0);

            return $line && $line->transaction_type === 'debit' ? -$amount : $amount;
        });

        $clearedBalance = (float) $reconciliation->statement_opening_balance + $signedMatchedTotal;
        $difference = round((float) $reconciliation->statement_closing_balance - $clearedBalance, 2);

        $reconciliation->update([
            'cleared_balance' => round($clearedBalance, 2),
            'difference' => $difference,
        ]);

        return $reconciliation->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseCsvLines(string $csv): array
    {
        $rows = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        $parsed = [];
        foreach ($rows as $index => $row) {
            if (trim($row) === '') {
                continue;
            }

            $columns = str_getcsv($row);

            if ($index === 0 && isset($columns[0]) && str_contains(strtolower((string) $columns[0]), 'date')) {
                continue;
            }

            if (count($columns) < 5) {
                continue;
            }

            $parsed[] = [
                'transaction_date' => trim((string) $columns[0]),
                'description' => trim((string) ($columns[1] ?? '')) ?: null,
                'reference_no' => trim((string) ($columns[2] ?? '')) ?: null,
                'transaction_type' => strtolower(trim((string) ($columns[3] ?? 'debit'))) === 'credit' ? 'credit' : 'debit',
                'amount' => (float) ($columns[4] ?? 0),
                'balance' => isset($columns[5]) && $columns[5] !== '' ? (float) $columns[5] : null,
                'is_matched' => false,
                'unmatched_reason' => null,
            ];
        }

        return $parsed;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildSuggestedMatches(Collection $statementLines, Collection $availableTransactions): array
    {
        $suggestions = [];

        foreach ($statementLines as $line) {
            if (! $line instanceof BankStatementLine || $line->is_matched) {
                continue;
            }

            $ranked = $availableTransactions
                ->map(function ($transaction) use ($line): ?array {
                    if (! $transaction instanceof BankTransaction) {
                        return null;
                    }

                    $score = $this->scoreSuggestion($line, $transaction);
                    if ($score <= 0) {
                        return null;
                    }

                    return [
                        'id' => $transaction->id,
                        'transaction_date' => $transaction->transaction_date,
                        'transaction_type' => $transaction->transaction_type,
                        'amount' => (float) $transaction->amount,
                        'reference_no' => $transaction->reference_no,
                        'description' => $transaction->description,
                        'score' => $score,
                    ];
                })
                ->filter()
                ->sortByDesc('score')
                ->take(3)
                ->values()
                ->all();

            $suggestions[$line->id] = $ranked;
        }

        return $suggestions;
    }

    private function scoreSuggestion(BankStatementLine $line, BankTransaction $transaction): float
    {
        if ($line->transaction_type !== $transaction->transaction_type) {
            return 0;
        }

        $score = 20.0;

        $amountDiff = abs((float) $line->amount - (float) $transaction->amount);
        if ($amountDiff < 0.005) {
            $score += 60;
        } elseif ($amountDiff <= 1) {
            $score += 40;
        } elseif ($amountDiff <= 5) {
            $score += 20;
        }

        $lineDate = Carbon::parse($line->transaction_date);
        $transactionDate = Carbon::parse($transaction->transaction_date);
        $dayDiff = abs($lineDate->diffInDays($transactionDate));

        if ($dayDiff === 0) {
            $score += 20;
        } elseif ($dayDiff <= 2) {
            $score += 12;
        } elseif ($dayDiff <= 7) {
            $score += 6;
        }

        $lineReference = strtolower(trim((string) ($line->reference_no ?? '')));
        $transactionReference = strtolower(trim((string) ($transaction->reference_no ?? '')));

        if ($lineReference !== '' && $transactionReference !== '') {
            if ($lineReference === $transactionReference) {
                $score += 25;
            } elseif (str_contains($transactionReference, $lineReference) || str_contains($lineReference, $transactionReference)) {
                $score += 12;
            }
        }

        return round($score, 2);
    }
}
