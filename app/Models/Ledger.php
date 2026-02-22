<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ledger extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'account_id',
        'journal_entry_id',
        'journal_entry_line_id',
        'posting_date',
        'debit',
        'credit',
        'running_balance',
        'control_number',
    ];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
