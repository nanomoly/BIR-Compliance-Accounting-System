<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'parent_id',
        'code',
        'name',
        'type',
        'normal_balance',
        'is_active',
        'is_control_account',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_active' => 'bool',
            'is_control_account' => 'bool',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
