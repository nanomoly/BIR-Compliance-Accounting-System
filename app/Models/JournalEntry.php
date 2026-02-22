<?php

namespace App\Models;

use App\Enums\JournalStatus;
use App\Enums\JournalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class JournalEntry extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (JournalEntry $entry): void {
            if ($entry->status === JournalStatus::POSTED) {
                throw new RuntimeException('Posted entries are immutable and cannot be deleted. Use reversal entry.');
            }
        });
    }

    use HasFactory;

    protected $fillable = [
        'branch_id',
        'created_by',
        'approved_by',
        'journal_type',
        'entry_number',
        'control_number',
        'entry_date',
        'reference_no',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'posted_at',
        'locked_at',
        'reversed_from_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'locked_at' => 'datetime',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'journal_type' => JournalType::class,
            'status' => JournalStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
