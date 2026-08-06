<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessRequest extends Model
{
    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AccessRequestItem::class);
    }
}
