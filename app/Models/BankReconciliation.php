<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'bank_statement_id',
        'status',
        'statement_opening_balance',
        'statement_closing_balance',
        'cleared_balance',
        'difference',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'statement_opening_balance' => 'float',
            'statement_closing_balance' => 'float',
            'cleared_balance' => 'float',
            'difference' => 'float',
            'closed_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class);
    }
}
