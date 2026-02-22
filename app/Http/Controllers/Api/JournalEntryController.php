<?php

namespace App\Http\Controllers\Api;

use App\DTOs\JournalEntryLineData;
use App\DTOs\StoreJournalEntryData;
use App\Enums\JournalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        return response()->json($this->journalEntryService->list((int) $request->integer('per_page', 15)));
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $lines = array_map(
            fn (array $line): JournalEntryLineData => new JournalEntryLineData(
                accountId: (int) $line['account_id'],
                debit: (float) $line['debit'],
                credit: (float) $line['credit'],
                customerId: isset($line['customer_id']) ? (int) $line['customer_id'] : null,
                supplierId: isset($line['supplier_id']) ? (int) $line['supplier_id'] : null,
                particulars: $line['particulars'] ?? null,
            ),
            $validated['lines'],
        );

        $dto = new StoreJournalEntryData(
            branchId: (int) $validated['branch_id'],
            journalType: JournalType::from($validated['journal_type']),
            entryDate: CarbonImmutable::parse($validated['entry_date']),
            description: $validated['description'],
            referenceNo: $validated['reference_no'] ?? null,
            lines: $lines,
        );

        return response()->json($this->journalEntryService->create($dto, (int) $request->user()->id), 201);
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('view', $journalEntry);

        return response()->json($this->journalEntryService->find($journalEntry->id));
    }

    public function post(JournalEntry $journalEntry, Request $request): JsonResponse
    {
        $this->authorize('post', $journalEntry);

        $userId = (int) $request->user()->id;

        if ((int) $journalEntry->created_by === $userId) {
            return response()->json([
                'message' => 'Maker-checker violation: you cannot post your own journal entry.',
            ], 422);
        }

        return response()->json($this->journalEntryService->post($journalEntry->load('lines'), $userId));
    }

    public function reverse(JournalEntry $journalEntry, Request $request): JsonResponse
    {
        $this->authorize('reverse', $journalEntry);

        return response()->json($this->journalEntryService->reverse($journalEntry->load('lines'), (int) $request->user()->id), 201);
    }
}
