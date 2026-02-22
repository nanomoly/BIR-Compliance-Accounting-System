<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'supplier_id',
        'journal_entry_id',
        'created_by',
        'invoice_number',
        'control_number',
        'invoice_type',
        'invoice_date',
        'due_date',
        'subtotal',
        'vat_amount',
        'total_amount',
        'status',
        'issued_at',
        'locked_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'locked_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(EInvoiceTransmission::class);
    }
}
