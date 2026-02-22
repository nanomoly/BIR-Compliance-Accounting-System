<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EInvoiceTransmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'provider',
        'status',
        'reference_number',
        'request_payload',
        'response_payload',
        'transmitted_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'transmitted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
