<?php

namespace App\DTOs;

class JournalEntryLineData
{
    public function __construct(
        public readonly int $accountId,
        public readonly float $debit,
        public readonly float $credit,
        public readonly ?int $customerId = null,
        public readonly ?int $supplierId = null,
        public readonly ?string $particulars = null,
    ) {}

    /**
     * @return array<string, int|float|string|null>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'customer_id' => $this->customerId,
            'supplier_id' => $this->supplierId,
            'particulars' => $this->particulars,
        ];
    }
}
