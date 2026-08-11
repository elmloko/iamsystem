<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /**
     * Registra una acción hecha por el usuario autenticado actual.
     */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = []): AuditLog
    {
        return self::logAs(null, null, $action, $description, $subject, $properties);
    }

    /**
     * Registra una acción, usando el usuario autenticado si existe o, si no
     * hay sesión (ej. formulario público de solicitud de acceso), el nombre
     * y correo indicados a mano.
     */
    public static function logAs(?string $actorName, ?string $actorEmail, string $action, string $description, ?Model $subject = null, array $properties = []): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? $actorName,
            'actor_email' => $user?->email ?? $actorEmail,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
