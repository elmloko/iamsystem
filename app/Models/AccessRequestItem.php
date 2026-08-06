<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequestItem extends Model
{
    protected $fillable = [
        'access_request_id', 'system_id', 'role_id', 'role_name', 'alias', 'extra_fields',
        'status', 'remote_user_id', 'outcome_status', 'outcome_message', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'decided_at' => 'datetime',
    ];

    public function accessRequest(): BelongsTo
    {
        return $this->belongsTo(AccessRequest::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(SystemEntry::class, 'system_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
