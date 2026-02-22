<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'file_path',
        'status',
        'backup_at',
        'restore_at',
    ];

    protected function casts(): array
    {
        return [
            'backup_at' => 'datetime',
            'restore_at' => 'datetime',
        ];
    }
}
