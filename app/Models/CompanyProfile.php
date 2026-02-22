<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tin',
        'registered_address',
        'software_version',
        'database_version',
        'developer_name',
        'developer_tin',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
