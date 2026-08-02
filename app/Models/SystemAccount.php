<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAccount extends Model
{
    protected $fillable = [
        'person_id', 'system_id', 'remote_user_id',
        'role_name', 'role_id', 'status', 'message',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(SystemEntry::class, 'system_id');
    }
}
