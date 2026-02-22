<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_profile_id',
        'code',
        'name',
        'tin',
        'address',
        'is_main',
    ];

    protected function casts(): array
    {
        return ['is_main' => 'bool'];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
