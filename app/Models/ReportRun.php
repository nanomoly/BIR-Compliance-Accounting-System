<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'generated_by',
        'branch_id',
        'report_type',
        'reference_number',
        'from_date',
        'to_date',
        'page_count',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'generated_at' => 'datetime',
        ];
    }
}
