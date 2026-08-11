<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría del propio IAM (no de los sistemas externos, eso lo cubre
 * SystemAuditService). Se escribe con App\Support\Audit::log()/logAs(),
 * incluida la visita de página vía App\Http\Middleware\LogPageVisits.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'actor_name', 'actor_email',
        'action', 'description',
        'subject_type', 'subject_id',
        'properties', 'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
