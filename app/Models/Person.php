<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $fillable = ['name', 'email'];

    public function accounts(): HasMany
    {
        return $this->hasMany(SystemAccount::class);
    }
}
